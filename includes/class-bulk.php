<?php

if (!defined('ABSPATH')) exit;

class KashiwazakiSEODescription_Bulk {

    private $models;
    private $api;

    public function __construct($models, $api) {
        $this->models = $models;
        $this->api = $api;
        add_action('admin_menu', array($this, 'add_bulk_submenu'), 20);
        add_action('wp_ajax_bulk_generate_description', array($this, 'bulk_generate_description_ajax'));
        add_action('wp_ajax_bulk_register_excerpt', array($this, 'bulk_register_excerpt_ajax'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_bulk_scripts'));
    }

    public function add_bulk_submenu() {
        add_submenu_page(
            'kashiwazaki-seo-description',
            '一括ディスクリプション生成＆登録',
            '一括ディスクリプション生成＆登録',
            'manage_options',
            'kashiwazaki-seo-bulk-description',
            array($this, 'bulk_page')
        );
    }

    public function enqueue_bulk_scripts($hook) {
        // サブメニューページのhook名をチェック
        if (strpos($hook, 'kashiwazaki-seo-bulk-description') === false) {
            return;
        }

        wp_enqueue_script(
            'kashiwazaki-seo-bulk-description',
            KASHIWAZAKI_SEO_DESCRIPTION_PLUGIN_URL . 'js/bulk.js',
            array('jquery'),
            KASHIWAZAKI_SEO_DESCRIPTION_VERSION . '.' . time(),
            true
        );

        wp_localize_script('kashiwazaki-seo-bulk-description', 'kashiwazaki_bulk_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kashiwazaki_seo_bulk_nonce')
        ));
    }

    public function bulk_page() {
        // 全ての公開投稿タイプを取得
        $all_post_types = get_post_types(array('public' => true), 'objects');
        unset($all_post_types['attachment']); // メディアは除外

        $selected_post_type = isset($_GET['bulk_type']) ? sanitize_text_field($_GET['bulk_type']) : 'all';

        // 選択された投稿タイプが有効か検証
        if ($selected_post_type !== 'all' && !isset($all_post_types[$selected_post_type])) {
            $selected_post_type = 'all';
        }

        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page_option = isset($_GET['per_page']) ? sanitize_text_field($_GET['per_page']) : '20';
        $per_page = ($per_page_option === 'all') ? -1 : intval($per_page_option);
        if ($per_page <= 0 && $per_page !== -1) {
            $per_page = 20;
        }

        // ソート設定
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        $valid_orderby = array('date', 'title', 'ID', 'modified', 'description', 'excerpt', 'desc_status', 'excerpt_status');
        if (!in_array($orderby, $valid_orderby)) {
            $orderby = 'date';
        }
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // フィルター：ディスクリプション状態
        $description_filter = isset($_GET['description_filter']) ? sanitize_text_field($_GET['description_filter']) : '';
        // フィルター：抜粋状態
        $excerpt_filter = isset($_GET['excerpt_filter']) ? sanitize_text_field($_GET['excerpt_filter']) : '';

        // 投稿タイプの設定（「すべて」の場合は全投稿タイプを配列で指定）
        if ($selected_post_type === 'all') {
            $query_post_types = array_keys($all_post_types);
        } else {
            $query_post_types = $selected_post_type;
        }

        // 状態ソートの場合は全件取得してPHPでソート
        $php_sort = in_array($orderby, array('excerpt', 'desc_status', 'excerpt_status'));
        // フィルターの場合もPHPでフィルタリングするため全件取得
        $needs_all_posts = $php_sort || $excerpt_filter || $per_page === -1;

        // 投稿を取得
        $args = array(
            'post_type' => $query_post_types,
            'post_status' => 'publish',
            'posts_per_page' => $needs_all_posts ? -1 : $per_page,
            'paged' => $needs_all_posts ? 1 : $paged,
            'order' => $order
        );

        // ディスクリプションでソートする場合
        if ($orderby === 'description') {
            $args['meta_key'] = '_kashiwazaki_seo_description';
            $args['orderby'] = 'meta_value';
        } elseif (!$php_sort) {
            $args['orderby'] = $orderby;
        }

        // ディスクリプションフィルター
        if ($description_filter === 'has') {
            $args['meta_query'] = array(
                array(
                    'key' => '_kashiwazaki_seo_description',
                    'value' => '',
                    'compare' => '!='
                )
            );
        } elseif ($description_filter === 'none') {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_kashiwazaki_seo_description',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_kashiwazaki_seo_description',
                    'value' => '',
                    'compare' => '='
                )
            );
        }

        // 抜粋フィルター（PHPでフィルタリングするためフラグを設定）
        $filter_by_excerpt = ($excerpt_filter === 'has' || $excerpt_filter === 'none');

        $query = new WP_Query($args);

        // 抜粋フィルターをPHPで適用
        if ($filter_by_excerpt && $query->have_posts()) {
            $filtered_posts = array();
            foreach ($query->posts as $post) {
                $has_excerpt = !empty($post->post_excerpt);

                if ($excerpt_filter === 'has' && $has_excerpt) {
                    $filtered_posts[] = $post;
                } elseif ($excerpt_filter === 'none' && !$has_excerpt) {
                    $filtered_posts[] = $post;
                }
            }
            $query->posts = $filtered_posts;
            $query->post_count = count($filtered_posts);
            $query->found_posts = count($filtered_posts);
        }

        // 状態でソートする場合はPHPでソート
        if ($php_sort && $query->have_posts()) {
            $posts_array = $query->posts;

            usort($posts_array, function($a, $b) use ($orderby, $order) {
                if ($orderby === 'excerpt') {
                    $excerpt_a = !empty($a->post_excerpt) ? mb_strlen($a->post_excerpt) : 0;
                    $excerpt_b = !empty($b->post_excerpt) ? mb_strlen($b->post_excerpt) : 0;
                    $result = $excerpt_a - $excerpt_b;
                } elseif ($orderby === 'desc_status') {
                    $desc_a = get_post_meta($a->ID, '_kashiwazaki_seo_description', true);
                    $desc_b = get_post_meta($b->ID, '_kashiwazaki_seo_description', true);
                    $has_a = !empty($desc_a) ? 1 : 0;
                    $has_b = !empty($desc_b) ? 1 : 0;
                    $result = $has_a - $has_b;
                } else { // excerpt_status
                    $has_a = !empty($a->post_excerpt) ? 1 : 0;
                    $has_b = !empty($b->post_excerpt) ? 1 : 0;
                    $result = $has_a - $has_b;
                }
                return $order === 'ASC' ? $result : -$result;
            });

            // ページネーション用に配列をスライス（全件表示の場合はスライスしない）
            $total_posts = count($posts_array);
            if ($per_page === -1) {
                $total_pages = 1;
                $query->posts = $posts_array;
            } else {
                $total_pages = ceil($total_posts / $per_page);
                $offset = ($paged - 1) * $per_page;
                $query->posts = array_slice($posts_array, $offset, $per_page);
            }
            $query->post_count = count($query->posts);
        } elseif ($needs_all_posts) {
            // フィルターのみの場合もページネーション処理（全件表示の場合はスライスしない）
            $total_posts = $query->found_posts;
            if ($per_page === -1) {
                $total_pages = 1;
            } else {
                $total_pages = ceil($total_posts / $per_page);
                $offset = ($paged - 1) * $per_page;
                $query->posts = array_slice($query->posts, $offset, $per_page);
            }
            $query->post_count = count($query->posts);
        } else {
            $total_posts = $query->found_posts;
            $total_pages = $per_page === -1 ? 1 : ceil($total_posts / $per_page);
        }

        // ソートリンク生成用ヘルパー
        $current_url = admin_url('admin.php?page=kashiwazaki-seo-bulk-description&bulk_type=' . $selected_post_type);
        if ($description_filter) {
            $current_url .= '&description_filter=' . $description_filter;
        }
        if ($excerpt_filter) {
            $current_url .= '&excerpt_filter=' . $excerpt_filter;
        }
        if ($per_page_option !== '20') {
            $current_url .= '&per_page=' . $per_page_option;
        }
        ?>
        <div class="wrap">
            <h1>一括ディスクリプション生成＆登録</h1>

            <div style="background: #f0f8ff; border: 1px solid #b3d9ff; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <p style="margin: 0;">
                    <strong>📋 使い方:</strong> 記事を選択して「ディスクリプション生成」ボタンをクリックすると、選択した記事のディスクリプションを一括で生成・保存します。<br>
                    生成後、「抜粋に登録」ボタンで抜粋フィールドにコピーできます。
                </p>
            </div>

            <!-- フィルター -->
            <div style="margin-bottom: 20px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                <form method="get" style="display: inline-flex; align-items: center; gap: 10px;">
                    <input type="hidden" name="page" value="kashiwazaki-seo-bulk-description">

                    <label for="bulk_type"><strong>投稿タイプ:</strong></label>
                    <select name="bulk_type" id="bulk_type">
                        <option value="all" <?php selected($selected_post_type, 'all'); ?>>
                            すべて (<?php
                                $total_all = 0;
                                foreach ($all_post_types as $pt_slug => $pt_obj) {
                                    $total_all += wp_count_posts($pt_slug)->publish;
                                }
                                echo $total_all;
                            ?>)
                        </option>
                        <?php foreach ($all_post_types as $pt_slug => $pt_obj): ?>
                            <option value="<?php echo esc_attr($pt_slug); ?>" <?php selected($selected_post_type, $pt_slug); ?>>
                                <?php echo esc_html($pt_obj->labels->name); ?>
                                (<?php echo wp_count_posts($pt_slug)->publish; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="description_filter"><strong>Desc:</strong></label>
                    <select name="description_filter" id="description_filter">
                        <option value="" <?php selected($description_filter, ''); ?>>すべて</option>
                        <option value="has" <?php selected($description_filter, 'has'); ?>>生成済み</option>
                        <option value="none" <?php selected($description_filter, 'none'); ?>>未生成</option>
                    </select>

                    <label for="excerpt_filter"><strong>抜粋:</strong></label>
                    <select name="excerpt_filter" id="excerpt_filter">
                        <option value="" <?php selected($excerpt_filter, ''); ?>>すべて</option>
                        <option value="has" <?php selected($excerpt_filter, 'has'); ?>>あり</option>
                        <option value="none" <?php selected($excerpt_filter, 'none'); ?>>なし</option>
                    </select>

                    <label for="per_page"><strong>表示:</strong></label>
                    <select name="per_page" id="per_page">
                        <option value="20" <?php selected($per_page_option, '20'); ?>>20件</option>
                        <option value="50" <?php selected($per_page_option, '50'); ?>>50件</option>
                        <option value="100" <?php selected($per_page_option, '100'); ?>>100件</option>
                        <option value="all" <?php selected($per_page_option, 'all'); ?>>全件</option>
                    </select>

                    <button type="submit" class="button">絞り込み</button>
                </form>
            </div>

            <!-- 一括操作ボタン -->
            <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <button type="button" id="bulk-generate-btn" class="button button-primary" disabled>
                    ✨ ディスクリプション生成
                </button>
                <button type="button" id="bulk-excerpt-btn" class="button button-primary" disabled style="background: #00a32a; border-color: #00a32a;">
                    📝 ディスクリプション→抜粋に登録
                </button>
                <button type="button" id="select-all-posts" class="button">全選択</button>
                <button type="button" id="deselect-all-posts" class="button">全解除</button>
                <button type="button" id="select-no-description" class="button">Desc未生成を選択</button>
                <button type="button" id="select-has-description" class="button">Desc生成済みを選択</button>
                <span id="selected-count" style="color: #666;">0件選択中</span>
            </div>

            <!-- 進捗表示 -->
            <div id="bulk-progress" style="display: none; margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
                <div style="margin-bottom: 10px;">
                    <strong>処理中...</strong> <span id="progress-text">0 / 0</span>
                </div>
                <div style="background: #e0e0e0; border-radius: 5px; height: 20px; overflow: hidden;">
                    <div id="progress-bar" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>
                </div>
                <div id="progress-log" style="margin-top: 10px; max-height: 150px; overflow-y: auto; font-size: 12px;"></div>
            </div>

            <!-- 記事一覧テーブル -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column" style="width: 30px;">
                            <input type="checkbox" id="cb-select-all">
                        </td>
                        <th class="manage-column sortable <?php echo $orderby === 'ID' ? 'sorted' : ''; ?>" style="width: 50px;">
                            <a href="<?php echo esc_url($current_url . '&orderby=ID&order=' . ($orderby === 'ID' && $order === 'ASC' ? 'DESC' : 'ASC')); ?>">
                                <span>ID</span>
                                <span class="sorting-indicator <?php echo $orderby === 'ID' ? ($order === 'ASC' ? 'asc' : 'desc') : ''; ?>"></span>
                            </a>
                        </th>
                        <?php if ($selected_post_type === 'all'): ?>
                        <th class="manage-column" style="width: 80px;">タイプ</th>
                        <?php endif; ?>
                        <th class="manage-column sortable <?php echo $orderby === 'title' ? 'sorted' : ''; ?>">
                            <a href="<?php echo esc_url($current_url . '&orderby=title&order=' . ($orderby === 'title' && $order === 'ASC' ? 'DESC' : 'ASC')); ?>">
                                <span>タイトル</span>
                                <span class="sorting-indicator <?php echo $orderby === 'title' ? ($order === 'ASC' ? 'asc' : 'desc') : ''; ?>"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo $orderby === 'date' ? 'sorted' : ''; ?>" style="width: 100px;">
                            <a href="<?php echo esc_url($current_url . '&orderby=date&order=' . ($orderby === 'date' && $order === 'DESC' ? 'ASC' : 'DESC')); ?>">
                                <span>日付</span>
                                <span class="sorting-indicator <?php echo $orderby === 'date' ? ($order === 'ASC' ? 'asc' : 'desc') : ''; ?>"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo $orderby === 'excerpt' ? 'sorted' : ''; ?>" style="width: 180px;">
                            <a href="<?php echo esc_url($current_url . '&orderby=excerpt&order=' . ($orderby === 'excerpt' && $order === 'DESC' ? 'ASC' : 'DESC')); ?>">
                                <span>抜粋</span>
                                <span class="sorting-indicator <?php echo $orderby === 'excerpt' ? ($order === 'ASC' ? 'asc' : 'desc') : ''; ?>"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo $orderby === 'description' ? 'sorted' : ''; ?>" style="width: 220px;">
                            <a href="<?php echo esc_url($current_url . '&orderby=description&order=' . ($orderby === 'description' && $order === 'DESC' ? 'ASC' : 'DESC')); ?>">
                                <span>生成ディスクリプション</span>
                                <span class="sorting-indicator <?php echo $orderby === 'description' ? ($order === 'ASC' ? 'asc' : 'desc') : ''; ?>"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo $orderby === 'desc_status' ? 'sorted' : ''; ?>" style="width: 40px; text-align: center;" title="ディスクリプション生成状態">
                            <a href="<?php echo esc_url($current_url . '&orderby=desc_status&order=' . ($orderby === 'desc_status' && $order === 'DESC' ? 'ASC' : 'DESC')); ?>">
                                <span>Desc</span>
                                <span class="sorting-indicator <?php echo $orderby === 'desc_status' ? ($order === 'ASC' ? 'asc' : 'desc') : ''; ?>"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo $orderby === 'excerpt_status' ? 'sorted' : ''; ?>" style="width: 40px; text-align: center;" title="抜粋登録状態">
                            <a href="<?php echo esc_url($current_url . '&orderby=excerpt_status&order=' . ($orderby === 'excerpt_status' && $order === 'DESC' ? 'ASC' : 'DESC')); ?>">
                                <span>抜粋</span>
                                <span class="sorting-indicator <?php echo $orderby === 'excerpt_status' ? ($order === 'ASC' ? 'asc' : 'desc') : ''; ?>"></span>
                            </a>
                        </th>
                        <th class="manage-column" style="width: 30px; text-align: center;" title="ページを表示">🔗</th>
                    </tr>
                </thead>
                <tbody id="posts-table-body">
                    <?php if ($query->have_posts()): while ($query->have_posts()): $query->the_post();
                        $post_id = get_the_ID();
                        $description = get_post_meta($post_id, '_kashiwazaki_seo_description', true);
                        $excerpt = get_the_excerpt();
                        $post_type_obj = get_post_type_object(get_post_type());
                        $post_obj = get_post($post_id);
                    ?>
                    <tr data-post-id="<?php echo $post_id; ?>" data-has-description="<?php echo $description ? '1' : '0'; ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" class="post-checkbox" value="<?php echo $post_id; ?>">
                        </th>
                        <td><?php echo $post_id; ?></td>
                        <?php if ($selected_post_type === 'all'): ?>
                        <td>
                            <span class="post-type-badge post-type-<?php echo esc_attr(get_post_type()); ?>">
                                <?php echo esc_html($post_type_obj->labels->singular_name); ?>
                            </span>
                        </td>
                        <?php endif; ?>
                        <td>
                            <a href="<?php echo get_edit_post_link($post_id); ?>" target="_blank">
                                <?php echo esc_html(get_the_title()); ?>
                            </a>
                        </td>
                        <td><?php echo get_the_date('Y/m/d'); ?></td>
                        <td class="excerpt-cell">
                            <?php if (!empty($post_obj->post_excerpt)): ?>
                                <div class="excerpt-display-mini">
                                    <?php echo esc_html(mb_substr($post_obj->post_excerpt, 0, 50)); ?>
                                    <?php if (mb_strlen($post_obj->post_excerpt) > 50): ?>
                                        <span class="excerpt-more">...</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #999; font-size: 11px;">抜粋なし</span>
                            <?php endif; ?>
                        </td>
                        <td class="description-cell">
                            <?php if ($description): ?>
                                <div class="description-display-mini">
                                    <?php echo esc_html(mb_substr($description, 0, 50)); ?>
                                    <?php if (mb_strlen($description) > 50): ?>
                                        <span class="description-more">...</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #999;">未設定</span>
                            <?php endif; ?>
                        </td>
                        <td class="desc-status-cell" style="text-align: center;">
                            <span class="status-icon <?php echo $description ? 'status-ok' : 'status-none'; ?>" title="<?php echo $description ? 'ディスクリプション生成済み' : 'ディスクリプション未生成'; ?>">
                                <?php echo $description ? '✓' : '−'; ?>
                            </span>
                        </td>
                        <td class="excerpt-status-cell" style="text-align: center;">
                            <?php $has_excerpt = !empty($post_obj->post_excerpt); ?>
                            <span class="status-icon <?php echo $has_excerpt ? 'status-ok' : 'status-none'; ?>" title="<?php echo $has_excerpt ? '抜粋登録済み' : '抜粋未登録'; ?>">
                                <?php echo $has_excerpt ? '✓' : '−'; ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <a href="<?php echo get_permalink($post_id); ?>" target="_blank" title="ページを表示" style="text-decoration: none; font-size: 14px;">↗</a>
                        </td>
                    </tr>
                    <?php endwhile; wp_reset_postdata(); else: ?>
                    <tr>
                        <td colspan="<?php echo $selected_post_type === 'all' ? '10' : '9'; ?>" style="text-align: center; padding: 20px;">
                            記事が見つかりませんでした。
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- ページネーション -->
            <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo $total_posts; ?>件</span>
                    <span class="pagination-links">
                        <?php if ($paged > 1): ?>
                            <a class="first-page button" href="<?php echo add_query_arg(array('paged' => 1)); ?>">«</a>
                            <a class="prev-page button" href="<?php echo add_query_arg(array('paged' => $paged - 1)); ?>">‹</a>
                        <?php endif; ?>
                        <span class="paging-input">
                            <span class="current-page"><?php echo $paged; ?></span> / <span class="total-pages"><?php echo $total_pages; ?></span>
                        </span>
                        <?php if ($paged < $total_pages): ?>
                            <a class="next-page button" href="<?php echo add_query_arg(array('paged' => $paged + 1)); ?>">›</a>
                            <a class="last-page button" href="<?php echo add_query_arg(array('paged' => $total_pages)); ?>">»</a>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <style>
            .description-display-mini, .excerpt-display-mini {
                font-size: 11px;
                line-height: 1.3;
                color: #333;
            }
            .description-more, .excerpt-more {
                color: #666;
            }
            .status-badge {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
            }
            .status-badge.has-description {
                background: #d4edda;
                color: #155724;
            }
            .status-badge.no-description {
                background: #f8d7da;
                color: #721c24;
            }
            .status-badge.processing {
                background: #fff3cd;
                color: #856404;
            }
            .status-badge.success {
                background: #d4edda;
                color: #155724;
            }
            .status-badge.error {
                background: #f8d7da;
                color: #721c24;
            }
            .status-icon {
                font-weight: bold;
                font-size: 14px;
            }
            .status-icon.status-ok {
                color: #28a745;
            }
            .status-icon.status-none {
                color: #ccc;
            }
            #progress-log div {
                padding: 2px 5px;
                border-bottom: 1px solid #eee;
            }
            #progress-log div.success { color: #155724; }
            #progress-log div.error { color: #721c24; }
            /* ソートインジケーター */
            .wp-list-table th.sortable a,
            .wp-list-table th.sorted a {
                display: flex;
                align-items: center;
                text-decoration: none;
            }
            .sorting-indicator {
                margin-left: 5px;
            }
            .sorting-indicator.asc::after {
                content: "▲";
                font-size: 10px;
            }
            .sorting-indicator.desc::after {
                content: "▼";
                font-size: 10px;
            }
            /* 投稿タイプバッジ */
            .post-type-badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 10px;
                font-weight: bold;
            }
            .post-type-post {
                background: #0073aa;
                color: white;
            }
            .post-type-page {
                background: #00a32a;
                color: white;
            }
            .post-type-badge:not(.post-type-post):not(.post-type-page) {
                background: #9b59b6;
                color: white;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var selectedPosts = [];
            var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            var nonce = '<?php echo wp_create_nonce('kashiwazaki_seo_bulk_nonce'); ?>';

            function updateSelectedCount() {
                selectedPosts = [];
                $('.post-checkbox:checked').each(function() {
                    selectedPosts.push($(this).val());
                });
                $('#selected-count').text(selectedPosts.length + '件選択中');
                $('#bulk-generate-btn').prop('disabled', selectedPosts.length === 0);
                $('#bulk-excerpt-btn').prop('disabled', selectedPosts.length === 0);
            }

            // チェックボックス変更
            $('.post-checkbox').on('change', updateSelectedCount);
            $('#cb-select-all').on('change', function() {
                $('.post-checkbox').prop('checked', $(this).is(':checked'));
                updateSelectedCount();
            });

            // 全選択/全解除ボタン
            $('#select-all-posts').on('click', function() {
                $('.post-checkbox').prop('checked', true);
                $('#cb-select-all').prop('checked', true);
                updateSelectedCount();
            });
            $('#deselect-all-posts').on('click', function() {
                $('.post-checkbox').prop('checked', false);
                $('#cb-select-all').prop('checked', false);
                updateSelectedCount();
            });

            // Desc未生成を選択ボタン
            $('#select-no-description').on('click', function() {
                $('.post-checkbox').prop('checked', false);
                $('tr[data-has-description="0"] .post-checkbox').prop('checked', true);
                $('#cb-select-all').prop('checked', false);
                updateSelectedCount();
            });

            // Desc生成済みを選択ボタン
            $('#select-has-description').on('click', function() {
                $('.post-checkbox').prop('checked', false);
                $('tr[data-has-description="1"] .post-checkbox').prop('checked', true);
                $('#cb-select-all').prop('checked', false);
                updateSelectedCount();
            });

            // 一括ディスクリプション生成
            $('#bulk-generate-btn').on('click', function() {
                if (selectedPosts.length === 0) return;

                var btn = $(this);
                btn.prop('disabled', true).text('処理中...');
                $('#bulk-progress').show();
                $('#progress-log').empty();

                var total = selectedPosts.length;
                var current = 0;
                var success = 0;
                var failed = 0;

                function processNext() {
                    if (current >= total) {
                        btn.prop('disabled', false).html('✨ ディスクリプション生成');
                        $('#progress-log').prepend('<div class="success"><strong>完了: ' + success + '件成功, ' + failed + '件失敗</strong></div>');
                        return;
                    }

                    var postId = selectedPosts[current];
                    var row = $('tr[data-post-id="' + postId + '"]');
                    row.find('.desc-status-cell').html('<span class="status-badge processing">...</span>');

                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'bulk_generate_description',
                            post_id: postId,
                            nonce: nonce
                        },
                        success: function(response) {
                            current++;
                            var percent = Math.round((current / total) * 100);
                            $('#progress-bar').css('width', percent + '%');
                            $('#progress-text').text(current + ' / ' + total);

                            if (response.success) {
                                success++;
                                var description = response.data.description;
                                row.find('.desc-status-cell').html('<span class="status-icon status-ok" title="ディスクリプション生成済み">✓</span>');
                                row.attr('data-has-description', '1');

                                // ディスクリプション表示を更新
                                var displayText = description.substring(0, 50);
                                var html = '<div class="description-display-mini">' + displayText;
                                if (description.length > 50) {
                                    html += '<span class="description-more">...</span>';
                                }
                                html += '</div>';
                                row.find('.description-cell').html(html);

                                $('#progress-log').prepend('<div class="success">✓ ID:' + postId + ' - 成功</div>');
                            } else {
                                failed++;
                                row.find('.desc-status-cell').html('<span class="status-icon status-none" title="エラー">✗</span>');
                                $('#progress-log').prepend('<div class="error">✗ ID:' + postId + ' - ' + response.data + '</div>');
                            }

                            // 次の記事を処理（少し遅延を入れてAPI制限を回避）
                            setTimeout(processNext, 1000);
                        },
                        error: function() {
                            current++;
                            failed++;
                            row.find('.desc-status-cell').html('<span class="status-icon status-none" title="エラー">✗</span>');
                            $('#progress-log').prepend('<div class="error">✗ ID:' + postId + ' - 通信エラー</div>');
                            setTimeout(processNext, 1000);
                        }
                    });
                }

                processNext();
            });

            // 一括抜粋登録
            $('#bulk-excerpt-btn').on('click', function() {
                if (selectedPosts.length === 0) return;

                // ディスクリプションが設定されている記事のみをフィルタ
                var postsWithDescription = [];
                selectedPosts.forEach(function(postId) {
                    var row = $('tr[data-post-id="' + postId + '"]');
                    if (row.attr('data-has-description') === '1') {
                        postsWithDescription.push(postId);
                    }
                });

                if (postsWithDescription.length === 0) {
                    alert('ディスクリプションが生成されている記事が選択されていません。\n「Desc生成済みを選択」ボタンを使用して選択してください。');
                    return;
                }

                if (!confirm(postsWithDescription.length + '件の記事のディスクリプションを抜粋に登録します。よろしいですか？')) {
                    return;
                }

                var btn = $(this);
                btn.prop('disabled', true).text('処理中...');
                $('#bulk-progress').show();
                $('#progress-log').empty();

                var total = postsWithDescription.length;
                var current = 0;
                var success = 0;
                var failed = 0;

                function processNextExcerpt() {
                    if (current >= total) {
                        btn.prop('disabled', false).html('📝 ディスクリプション→抜粋に登録');
                        $('#progress-log').prepend('<div class="success"><strong>完了: ' + success + '件成功, ' + failed + '件失敗</strong></div>');
                        return;
                    }

                    var postId = postsWithDescription[current];
                    var row = $('tr[data-post-id="' + postId + '"]');
                    row.find('.excerpt-status-cell').html('<span class="status-badge processing">...</span>');

                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'bulk_register_excerpt',
                            post_id: postId,
                            nonce: nonce
                        },
                        success: function(response) {
                            current++;
                            var percent = Math.round((current / total) * 100);
                            $('#progress-bar').css('width', percent + '%');
                            $('#progress-text').text(current + ' / ' + total);

                            if (response.success) {
                                success++;
                                var excerpt = response.data.excerpt;
                                row.find('.excerpt-status-cell').html('<span class="status-icon status-ok" title="抜粋登録済み">✓</span>');

                                // 抜粋表示を更新
                                var displayText = excerpt.substring(0, 50);
                                var html = '<div class="excerpt-display-mini">' + displayText;
                                if (excerpt.length > 50) {
                                    html += '<span class="excerpt-more">...</span>';
                                }
                                html += '</div>';
                                row.find('.excerpt-cell').html(html);

                                $('#progress-log').prepend('<div class="success">✓ ID:' + postId + ' - ' + response.data.message + '</div>');
                            } else {
                                failed++;
                                row.find('.excerpt-status-cell').html('<span class="status-icon status-none" title="エラー">✗</span>');
                                $('#progress-log').prepend('<div class="error">✗ ID:' + postId + ' - ' + response.data + '</div>');
                            }

                            // 次の記事を処理
                            setTimeout(processNextExcerpt, 500);
                        },
                        error: function() {
                            current++;
                            failed++;
                            row.find('.excerpt-status-cell').html('<span class="status-icon status-none" title="エラー">✗</span>');
                            $('#progress-log').prepend('<div class="error">✗ ID:' + postId + ' - 通信エラー</div>');
                            setTimeout(processNextExcerpt, 500);
                        }
                    });
                }

                processNextExcerpt();
            });
        });
        </script>
        <?php
    }

    public function bulk_generate_description_ajax() {
        check_ajax_referer('kashiwazaki_seo_bulk_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error('投稿が見つかりません');
        }

        $api_key = get_option('kashiwazaki_seo_description_openai_api_key');
        $model = get_option('kashiwazaki_seo_description_model', $this->models->get_default_model('openai'));
        $description_length = get_option('kashiwazaki_seo_description_length', 150);

        if (empty($api_key)) {
            wp_send_json_error('APIキーが設定されていません。管理画面で設定してください。');
        }

        $scraped_data = $this->scrape_post_content($post);
        $description = $this->generate_description_with_ai($scraped_data, $api_key, $model, $description_length);

        if (is_wp_error($description)) {
            wp_send_json_error($description->get_error_message());
        }

        // ディスクリプションを保存
        update_post_meta($post_id, '_kashiwazaki_seo_description', sanitize_textarea_field($description));

        wp_send_json_success(array(
            'description' => $description,
            'message' => 'ディスクリプションを生成しました'
        ));
    }

    public function bulk_register_excerpt_ajax() {
        check_ajax_referer('kashiwazaki_seo_bulk_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error('投稿が見つかりません');
        }

        $description = get_post_meta($post_id, '_kashiwazaki_seo_description', true);

        if (empty($description)) {
            wp_send_json_error('ディスクリプションが生成されていません');
        }

        // 抜粋を更新
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_excerpt' => $description
        ));

        if (is_wp_error($result)) {
            wp_send_json_error('抜粋の更新に失敗しました: ' . $result->get_error_message());
        }

        wp_send_json_success(array(
            'excerpt' => $description,
            'message' => '抜粋に登録しました'
        ));
    }

    private function scrape_post_content($post) {
        $content = $post->post_title . "\n\n";

        if ($post->post_type === 'attachment') {
            $content .= "投稿タイプ: メディアファイル\n";
            $content .= "ファイル名: " . basename(get_attached_file($post->ID)) . "\n";

            if (!empty($post->post_content)) {
                $content .= "説明: " . $post->post_content . "\n";
            }

            $alt_text = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
            if (!empty($alt_text)) {
                $content .= "代替テキスト: " . $alt_text . "\n";
            }

            if (!empty($post->post_excerpt)) {
                $content .= "キャプション: " . $post->post_excerpt . "\n";
            }

            $mime_type = get_post_mime_type($post->ID);
            if ($mime_type) {
                $content .= "ファイルタイプ: " . $mime_type . "\n";
            }
        } else {
            $content .= "投稿タイプ: " . $post->post_type . "\n";
            $content .= $post->post_content;
        }

        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);

        return "=== ページ情報 ===\n" .
               "タイトル: {$post->post_title}\n\n" .
               "=== 詳細 ===\n" . mb_substr($content, 0, 3000);
    }

    private function generate_description_with_ai($scraped_data, $api_key, $model, $description_length) {
        $url = 'https://api.openai.com/v1/chat/completions';

        $prompt = "以下のコンテンツ情報から、SEOに最適化されたメタディスクリプション（description）を{$description_length}文字以内で生成してください。\n\n" .
                  "条件：\n" .
                  "- {$description_length}文字以内で正確に作成\n" .
                  "- SEO効果の高い自然な日本語\n" .
                  "- 検索ユーザーにとって魅力的で分かりやすい内容\n" .
                  "- タイトルや本文の重要な要素を含める\n" .
                  "- メディアファイルの場合は、ファイルの内容や用途を反映\n" .
                  "- カスタム投稿タイプの場合は、その特性を考慮\n" .
                  "- キーワードを自然に組み込む\n" .
                  "- 文末は完結した文章で終わる\n" .
                  "- 結果はdescription文のみ出力（説明や余計な文章は一切不要）\n\n" .
                  "コンテンツ情報：\n" . $scraped_data;

        $data = array(
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 300,
            'temperature' => 0.7
        );

        if (empty($model)) {
            $model = $this->models->get_default_model('openai');
        }
        $data['model'] = $model;

        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        );

        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => 30,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'API接続エラー: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            return new WP_Error('api_error', "APIエラー (HTTP {$status_code}): " . $body);
        }

        $json_result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'JSON解析エラー: ' . json_last_error_msg());
        }

        if (isset($json_result['choices'][0]['message']['content'])) {
            $content = trim($json_result['choices'][0]['message']['content']);
            if (empty($content)) {
                return new WP_Error('empty_response', 'AIからの応答が空でした');
            }
            return $content;
        } else {
            return new WP_Error('invalid_response', 'AIからの応答を解析できませんでした');
        }
    }
}
