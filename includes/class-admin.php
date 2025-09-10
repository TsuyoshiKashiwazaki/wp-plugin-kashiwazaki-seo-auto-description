<?php

if (!defined('ABSPATH')) exit;

class KashiwazakiSEODescription_Admin {

    private $models;
    private $api;

    public function __construct($models, $api) {
        $this->models = $models;
        $this->api = $api;
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_get_filtered_models', array($this, 'get_filtered_models_ajax'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Kashiwazaki SEO Auto Description',
            'Kashiwazaki SEO Auto Description',
            'manage_options',
            'kashiwazaki-seo-description',
            array($this, 'admin_page'),
            'dashicons-admin-generic',
            82
        );
    }

    public function admin_page() {
        if (isset($_POST['test_api'])) {
            $test_api_key = sanitize_text_field($_POST['openai_api_key']);
            $test_result = $this->api->test_openai_api_key($test_api_key);

            if ($test_result['success']) {
                echo '<div class="notice notice-success"><p>✅ APIキーのテストが成功しました！</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ APIキーのテストが失敗しました: ' . esc_html($test_result['message']) . '</p></div>';
            }
        }

        if (isset($_POST['submit'])) {
            update_option('kashiwazaki_seo_description_api_provider', 'openai');
            update_option('kashiwazaki_seo_description_openai_api_key', sanitize_text_field($_POST['openai_api_key']));
            update_option('kashiwazaki_seo_description_model', sanitize_text_field($_POST['model']));
            update_option('kashiwazaki_seo_description_length', intval($_POST['description_length']));

            $enabled_post_types = isset($_POST['enabled_post_types']) ? array_map('sanitize_text_field', $_POST['enabled_post_types']) : array();
            update_option('kashiwazaki_seo_description_enabled_post_types', $enabled_post_types);


            echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
        }

        if (isset($_POST['restore_model'])) {
            $model_to_restore = sanitize_text_field($_POST['restore_model']);
            $this->models->remove_from_excluded_models($model_to_restore);
            echo '<div class="notice notice-success"><p>モデル「' . esc_html($model_to_restore) . '」を復活させました。</p></div>';
        }

        if (isset($_POST['restore_all_models'])) {
            $count = $this->models->restore_all_excluded_models();
            echo '<div class="notice notice-success"><p>🎉 除外中の' . $count . '個のモデルをすべて復活させました！</p></div>';
        }

        $api_provider = get_option('kashiwazaki_seo_description_api_provider', 'openai');
        $api_key = get_option('kashiwazaki_seo_description_api_key', '');
        $openai_api_key = get_option('kashiwazaki_seo_description_openai_api_key', '');
        $model = get_option('kashiwazaki_seo_description_model', '');
        $description_length = get_option('kashiwazaki_seo_description_length', 150);
        $enabled_post_types = get_option('kashiwazaki_seo_description_enabled_post_types', array('post', 'page'));

        $available_post_types = $this->get_available_post_types();
        $available_models = $this->models->load_models_from_file($api_provider);
        ?>
        <div class="wrap">
            <h1>Kashiwazaki SEO Auto Description 設定</h1>

            <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <h3 style="margin: 0 0 10px 0;">AI SEO Description生成</h3>
                <p style="margin: 0;">OpenAI GPTを使用してSEO最適化されたメタディスクリプションを自動生成します。</p>
            </div>

            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input type="text" name="openai_api_key" value="<?php echo esc_attr($openai_api_key); ?>" class="regular-text">
                            <p class="description">OpenAIのAPIキーを入力</p>
                            <button type="submit" name="test_api" class="button">APIキーテスト</button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">AIモデル</th>
                        <td>
                            <select name="model" class="regular-text" style="padding: 4px 8px; min-width: 400px; font-size: 13px;">
                                <option value="">デフォルト（GPT-4.1 Nano）</option>
                                <?php foreach ($available_models as $model_id => $model_name): ?>
                                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected($model, $model_id); ?>>
                                        <?php echo esc_html($model_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php echo $this->get_model_selection_help(); ?>

                            <p class="description">使用するAIモデルを選択。デフォルトはGPT-4.1 Nano（最も経済的）</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Description文字数</th>
                        <td>
                            <select name="description_length" style="padding: 4px 8px;">
                                <?php
                                $length_options = array(80, 100, 150, 200, 300, 500);
                                foreach ($length_options as $length):
                                ?>
                                    <option value="<?php echo $length; ?>" <?php selected($description_length, $length); ?>><?php echo $length; ?>文字</option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">生成するdescriptionの文字数</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">対応する投稿タイプ</th>
                        <td>
                            <div style="margin-bottom: 10px;">
                                <button type="button" id="select-all-post-types" class="button">全選択</button>
                                <button type="button" id="deselect-all-post-types" class="button">全解除</button>
                                <button type="button" id="select-common-post-types" class="button">基本のみ</button>
                            </div>

                            <fieldset>
                                <legend class="screen-reader-text"><span>対応する投稿タイプ</span></legend>

                                <div style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #f9f9f9;">
                                    <h4 style="margin: 0 0 8px 0;">標準投稿タイプ</h4>
                                    <?php
                                    $builtin_types = array('post', 'page', 'attachment');
                                    foreach ($builtin_types as $post_type):
                                        if (isset($available_post_types[$post_type])):
                                    ?>
                                        <label for="post_type_<?php echo esc_attr($post_type); ?>" style="display: inline-block; margin-right: 20px; margin-bottom: 5px;">
                                            <input type="checkbox"
                                                   name="enabled_post_types[]"
                                                   id="post_type_<?php echo esc_attr($post_type); ?>"
                                                   value="<?php echo esc_attr($post_type); ?>"
                                                   <?php checked(in_array($post_type, $enabled_post_types)); ?>>
                                            <?php echo esc_html($available_post_types[$post_type]); ?>
                                        </label>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </div>

                                <?php
                                $custom_types = array_diff_key($available_post_types, array_flip($builtin_types));
                                if (!empty($custom_types)):
                                ?>
                                <div style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; background: #f0f8ff;">
                                    <h4 style="margin: 0 0 8px 0;">カスタム投稿タイプ</h4>
                                    <?php foreach ($custom_types as $post_type => $post_type_label): ?>
                                        <label for="post_type_<?php echo esc_attr($post_type); ?>" style="display: inline-block; margin-right: 20px; margin-bottom: 5px;">
                                            <input type="checkbox"
                                                   name="enabled_post_types[]"
                                                   id="post_type_<?php echo esc_attr($post_type); ?>"
                                                   value="<?php echo esc_attr($post_type); ?>"
                                                   <?php checked(in_array($post_type, $enabled_post_types)); ?>>
                                            <?php echo esc_html($post_type_label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                            </fieldset>

                            <p class="description">編集画面でdescriptionを生成する投稿タイプを選択</p>

                            <script>
                            jQuery(document).ready(function($) {
                                $('#select-all-post-types').on('click', function() {
                                    $('input[name="enabled_post_types[]"]').prop('checked', true);
                                });

                                $('#deselect-all-post-types').on('click', function() {
                                    $('input[name="enabled_post_types[]"]').prop('checked', false);
                                });

                                $('#select-common-post-types').on('click', function() {
                                    $('input[name="enabled_post_types[]"]').prop('checked', false);
                                    $('#post_type_post, #post_type_page, #post_type_attachment').prop('checked', true);
                                });
                            });
                            </script>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>

            </form>

        </div>
        <?php
    }

    private function get_available_post_types() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $available_types = array();

        foreach ($post_types as $post_type) {
            if ($post_type->name === 'attachment') {
                $available_types[$post_type->name] = $post_type->label . ' (メディア)';
            } else {
                $available_types[$post_type->name] = $post_type->label;
            }
        }

        $custom_post_types = get_post_types(array('_builtin' => false), 'objects');
        foreach ($custom_post_types as $post_type) {
            if (!isset($available_types[$post_type->name])) {
                $available_types[$post_type->name] = $post_type->label . ' (カスタム投稿)';
            }
        }

        return $available_types;
    }

    private function get_model_selection_help() {
        return '';
    }

    public function get_filtered_models_ajax() {
        check_ajax_referer('kashiwazaki_seo_description_nonce', 'nonce');

        $api_provider = sanitize_text_field($_POST['api_provider']);
        $filtered_models = $this->models->load_models_from_file($api_provider);

        wp_send_json_success(array('models' => $filtered_models));
    }
}
