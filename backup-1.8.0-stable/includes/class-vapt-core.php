<?php
class VAPT_Core {
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function init() {
        load_plugin_textdomain('vaalue-ai-polylang-translator', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function register_settings() {
        register_setting('vapt_settings', 'vapt_openai_api_key');
        register_setting('vapt_settings', 'vapt_translation_model', array(
            'default' => 'gpt-4'
        ));
        register_setting('vapt_settings', 'vapt_auto_translate', array(
            'type' => 'boolean',
            'default' => false
        ));
    }
}