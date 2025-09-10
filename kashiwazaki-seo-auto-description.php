<?php
/*
Plugin Name: Kashiwazaki SEO Auto Description
Plugin URI: https://www.tsuyoshikashiwazaki.jp
Description: 投稿・固定ページ・カスタム投稿・メディアから自動でdescriptionを生成するSEOツール
Version: 1.0.0
Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
*/

if (!defined('ABSPATH')) exit;

define('KASHIWAZAKI_SEO_DESCRIPTION_VERSION', '1.0.0');
define('KASHIWAZAKI_SEO_DESCRIPTION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KASHIWAZAKI_SEO_DESCRIPTION_PLUGIN_URL', plugin_dir_url(__FILE__));

class KashiwazakiSEOAutoDescription {

    private $admin;
    private $meta_box;
    private $api;
    private $models;

    public function __construct() {
        $this->load_dependencies();
        $this->init();
    }

    private function load_dependencies() {
        require_once KASHIWAZAKI_SEO_DESCRIPTION_PLUGIN_DIR . 'includes/class-models.php';
        require_once KASHIWAZAKI_SEO_DESCRIPTION_PLUGIN_DIR . 'includes/class-api.php';
        require_once KASHIWAZAKI_SEO_DESCRIPTION_PLUGIN_DIR . 'includes/class-meta-box.php';
        require_once KASHIWAZAKI_SEO_DESCRIPTION_PLUGIN_DIR . 'includes/class-admin.php';
    }

    private function init() {
        $this->models = new KashiwazakiSEODescription_Models();
        $this->api = new KashiwazakiSEODescription_API($this->models);
        $this->meta_box = new KashiwazakiSEODescription_MetaBox($this->api);
        $this->admin = new KashiwazakiSEODescription_Admin($this->models, $this->api);
    }
}

new KashiwazakiSEOAutoDescription();
