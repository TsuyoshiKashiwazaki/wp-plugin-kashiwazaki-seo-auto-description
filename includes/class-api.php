<?php

if (!defined('ABSPATH')) exit;

class KashiwazakiSEODescription_API {

    private $models;

    public function __construct($models) {
        $this->models = $models;
        add_action('wp_ajax_generate_description', array($this, 'generate_description_ajax'));
        add_action('wp_ajax_check_api_settings', array($this, 'check_api_settings_ajax'));
    }

    public function generate_description_ajax() {
        check_ajax_referer('kashiwazaki_seo_description_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);

        $post = get_post($post_id);

        if (!$post) {
            wp_send_json_error('投稿が見つかりません');
        }

        $api_provider = get_option('kashiwazaki_seo_description_api_provider', 'openai');
        $api_key = get_option('kashiwazaki_seo_description_openai_api_key');
        $model = get_option('kashiwazaki_seo_description_model', $this->models->get_default_model('openai'));
        $description_length = get_option('kashiwazaki_seo_description_length', 150);

        if (empty($api_key)) {
            wp_send_json_error('APIキーが設定されていません。管理画面で設定してください。');
        }

        $scraped_data = $this->scrape_post_content($post);

        $description = $this->generate_description_with_ai($scraped_data, $api_key, $model, $description_length, 'openai');

        if (is_wp_error($description)) {
            $error_message = $description->get_error_message();

            if (!empty($model)) {
                $this->models->add_to_excluded_models($model);

                wp_send_json_error('選択されたモデルでエラーが発生しました: ' . $error_message);
            } else {
                wp_send_json_error($error_message);
            }
        }

        $actual_model = !empty($model) ? $model : $this->models->get_default_model();
        $model_display_name = $this->models->get_model_display_name($actual_model);

        if (!empty($model_display_name)) {
            wp_send_json_success(array(
                'description' => $description,
                'used_model' => $model_display_name,
                'model_id' => $actual_model
            ));
        } else {
            wp_send_json_success($description);
        }
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

    private function generate_description_with_ai($scraped_data, $api_key, $model, $description_length, $api_provider = 'openai') {
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


        $json_data = json_encode($data);

        $debug_info = array(
            'url' => $url,
            'api_key_preview' => substr($api_key, 0, 10) . '...' . substr($api_key, -10),
            'api_key_length' => strlen($api_key),
            'api_key_format_valid' => preg_match('/^sk-/', $api_key),
            'headers' => $headers,
            'data' => $data,
            'json_data' => $json_data,
            'request_time' => date('Y-m-d H:i:s'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION
        );

        $request_options = array(
            'headers' => $headers,
            'body' => $json_data,
            'timeout' => 30,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_site_url(),
            'sslverify' => true,
            'httpversion' => '1.1'
        );

        $response = wp_remote_post($url, $request_options);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return new WP_Error('api_error', "API接続エラー: {$error_message}");
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            return new WP_Error('api_error', "APIエラー (HTTP {$status_code}): " . $body . " | Debug: " . json_encode($debug_info));
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




    public function test_openai_api_key($api_key) {
        $url = 'https://api.openai.com/v1/models';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'ネットワークエラー: ' . $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200) {
            return array(
                'success' => true,
                'message' => 'OpenAI APIキーが正常に動作しています'
            );
        } else {
            $body = wp_remote_retrieve_body($response);
            return array(
                'success' => false,
                'message' => "HTTP {$status_code}: " . $body
            );
        }
    }


    public function check_api_settings_ajax() {
        check_ajax_referer('kashiwazaki_seo_description_nonce', 'nonce');

        $api_provider = 'openai';
        $api_key = get_option('kashiwazaki_seo_description_openai_api_key', '');
        $model = get_option('kashiwazaki_seo_description_model', '');
        $description_length = get_option('kashiwazaki_seo_description_length', 120);

        $settings = array(
            'api_provider' => $api_provider,
            'api_key_exists' => !empty($api_key),
            'api_key_preview' => !empty($api_key) ? substr($api_key, 0, 10) . '...' . substr($api_key, -10) : '未設定',
            'model' => $model,
            'description_length' => $description_length
        );

        wp_send_json_success($settings);
    }
}
