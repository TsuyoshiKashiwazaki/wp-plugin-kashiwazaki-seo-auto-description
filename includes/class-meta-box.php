<?php

if (!defined('ABSPATH')) exit;

class KashiwazakiSEODescription_MetaBox {

    private $api;

    public function __construct($api) {
        $this->api = $api;
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box_data'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function add_meta_box() {
        $enabled_post_types = get_option('kashiwazaki_seo_description_enabled_post_types', array('post', 'page'));

        if (empty($enabled_post_types)) {
            $enabled_post_types = array('post', 'page');
        }

        foreach ($enabled_post_types as $post_type) {
            add_meta_box(
                'kashiwazaki_seo_description',
                'Kashiwazaki SEO Auto Description',
                array($this, 'meta_box_html'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    public function meta_box_html($post) {
        wp_nonce_field('kashiwazaki_seo_description_nonce', 'kashiwazaki_seo_description_nonce');
        $description = get_post_meta($post->ID, '_kashiwazaki_seo_description', true);
        $description_length = get_option('kashiwazaki_seo_description_length', 150);
        ?>
        <div id="kashiwazaki-seo-description-container">
            <div style="margin-bottom: 10px; padding: 8px; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px;">
                <p style="margin: 0; font-size: 12px; color: #0073aa;">
                    <strong>📏 文字数設定:</strong> <?php echo $description_length; ?>文字<br>
                    <span style="font-size: 11px;">※文字数の変更は「設定」→「Kashiwazaki SEO Auto Description」で行えます</span>
                </p>
            </div>

            <button type="button" id="generate-description-btn" class="button button-primary" style="width: 100%; margin-bottom: 10px; height: 35px; font-size: 14px;">
                ✨ description生成
            </button>

            <div id="description-loading" style="display: none; text-align: center; margin: 10px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
                <div class="spinner" style="float: none; margin: 0 auto 10px; width: 20px; height: 20px;"></div>
                <p style="margin: 0; color: #666; font-size: 13px;">AIがdescriptionを生成中...</p>
            </div>

            <div id="description-result" style="margin-top: 10px;">
                <?php if ($description): ?>
                    <div class="description-display">
                        <div class="description-text" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9; font-size: 13px; line-height: 1.4;">
                            <?php echo esc_html($description); ?>
                        </div>
                        <div style="margin-top: 8px; font-size: 11px; color: #666;">
                            文字数: <span id="description-char-count"><?php echo mb_strlen($description); ?></span>文字
                        </div>
                    </div>
                    <div style="margin-top: 10px;">
                        <button type="button" id="copy-description-btn" style="padding: 4px 12px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">📋 descriptionをコピー</button>
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 10px;">
                <button type="button" id="toggle-debug" style="padding: 4px 8px; font-size: 11px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 3px; cursor: pointer;">🔧 デバッグログを表示</button>
            </div>
            <div id="debug-log" style="margin-top: 10px; padding: 8px; background: #f5f5f9; border: 1px solid #ddd; border-radius: 3px; font-size: 10px; max-height: 120px; overflow-y: auto; display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <strong style="color: #333;">デバッグログ:</strong>
                    <button type="button" id="copy-all-logs" style="padding: 2px 6px; font-size: 9px; background: #0073aa; color: white; border: none; border-radius: 2px; cursor: pointer;">全ログコピー</button>
                </div>
                <div id="debug-content" style="margin-top: 5px;"></div>
            </div>

            <textarea id="description-textarea" name="kashiwazaki_seo_description" style="display: none;"><?php echo esc_textarea($description); ?></textarea>
        </div>
        <style>
            .description-display {
                margin-top: 10px;
            }
            .description-text {
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            .spinner {
                border: 2px solid #f3f3f3;
                border-top: 2px solid #0073aa;
                border-radius: 50%;
                width: 20px;
                height: 20px;
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            #debug-content div {
                margin: 1px 0;
                padding: 1px 2px;
                border-bottom: 1px solid #ddd;
                word-break: break-all;
            }
        </style>
        <?php
    }

    public function save_meta_box_data($post_id) {
        if (!isset($_POST['kashiwazaki_seo_description_nonce']) ||
            !wp_verify_nonce($_POST['kashiwazaki_seo_description_nonce'], 'kashiwazaki_seo_description_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['kashiwazaki_seo_description'])) {
            update_post_meta($post_id, '_kashiwazaki_seo_description', sanitize_textarea_field($_POST['kashiwazaki_seo_description']));
        }
    }

    public function enqueue_admin_scripts($hook) {
        if (in_array($hook, array('post.php', 'post-new.php', 'upload.php', 'media.php'))) {
            $version = KASHIWAZAKI_SEO_DESCRIPTION_VERSION . '.' . time();
            wp_enqueue_script('kashiwazaki-seo-description', KASHIWAZAKI_SEO_DESCRIPTION_PLUGIN_URL . 'js/admin.js', array('jquery'), $version, true);
            wp_localize_script('kashiwazaki-seo-description', 'kashiwazaki_description_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kashiwazaki_seo_description_nonce'),
                'plugin_url' => KASHIWAZAKI_SEO_DESCRIPTION_PLUGIN_URL
            ));
        }
    }
}
