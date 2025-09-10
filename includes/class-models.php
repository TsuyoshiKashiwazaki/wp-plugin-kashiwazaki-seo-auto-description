<?php

if (!defined('ABSPATH')) exit;

class KashiwazakiSEODescription_Models {

    private $default_model = null;

    public function __construct() {

    }

    public function load_models_from_file($api_provider = 'openai') {
        $available_models = array(
            'gpt-4.1-nano' => 'GPT-4.1 Nano - 最も経済的',
            'gpt-4.1-mini' => 'GPT-4.1 Mini - コストパフォーマンスが良い',
            'gpt-4.1' => 'GPT-4.1 - 高性能'
        );
        
        return $available_models;

    }

    public function get_default_model($api_provider = 'openai') {
        return 'gpt-4.1-nano';
    }

    public function get_excluded_models() {
        return array();
    }

    public function add_to_excluded_models($model_id) {
        // GPTモデルでは除外機能を使用しない
    }

    public function remove_from_excluded_models($model_id) {
        $excluded_models = $this->get_excluded_models();
        $key = array_search($model_id, $excluded_models);
        if ($key !== false) {
            unset($excluded_models[$key]);
            update_option('kashiwazaki_seo_description_excluded_models', array_values($excluded_models));
        }
    }

    public function restore_all_excluded_models() {
        $excluded_models = $this->get_excluded_models();
        $count = count($excluded_models);

        if ($count > 0) {
            update_option('kashiwazaki_seo_description_excluded_models', array());
            update_option('kashiwazaki_seo_description_model_errors', array());
        }

        return $count;
    }

    public function get_all_models_with_status() {
        $models_file = KASHIWAZAKI_SEO_DESCRIPTION_PLUGIN_DIR . 'models.txt';
        $all_models = array();
        $excluded_models = $this->get_excluded_models();
        $error_info = get_option('kashiwazaki_seo_description_model_errors', array());

        if (!file_exists($models_file)) {
            return array();
        }

        $lines = file($models_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;

            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $model_id = trim($parts[0]);
                $display_name = trim($parts[1]);

                $all_models[$model_id] = array(
                    'display_name' => $display_name,
                    'is_excluded' => in_array($model_id, $excluded_models),
                    'error_info' => isset($error_info[$model_id]) ? $error_info[$model_id] : null
                );
            }
        }

        return $all_models;
    }

    public function get_model_display_name($model_id) {
        if (empty($model_id)) {
            $model_id = $this->get_default_model();
        }

        $display_names = array(
            'gpt-4.1-nano' => 'GPT-4.1 Nano',
            'gpt-4.1-mini' => 'GPT-4.1 Mini',
            'gpt-4.1' => 'GPT-4.1'
        );
        
        return isset($display_names[$model_id]) ? $display_names[$model_id] : ucfirst($model_id);
    }

    public function extract_short_model_name($display_name) {
        $name = $display_name;

        $name = preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $name);
        $name = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $name);
        $name = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $name);
        $name = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $name);
        $name = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $name);

        $name = preg_replace('/\s*\([^)]*\).*$/', '', $name);
        $name = preg_replace('/^[^\w\s]+\s*/', '', $name);

        return trim($name);
    }
}
