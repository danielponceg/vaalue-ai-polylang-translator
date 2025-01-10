<?php
/**
 * Plugin Name: Vaalue AI-Powered Polylang Translator
 * Plugin URI: https://example.com/ai-polylang-translator
 * Description: Automatically translate WordPress content using OpenAI API and Polylang integration
 * Version: 1.9.12-stable
 * Author: Vaalue.co
 * Author URI: https://vaalue.co
 * Text Domain: vaalue-ai-polylang-translator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('VAPT_VERSION', '1.9.12-stable');
define('VAPT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VAPT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load required files
require_once VAPT_PLUGIN_DIR . 'includes/class-vapt-core.php';
require_once VAPT_PLUGIN_DIR . 'includes/class-vapt-admin.php';
require_once VAPT_PLUGIN_DIR . 'includes/class-vapt-openai.php';
require_once VAPT_PLUGIN_DIR . 'includes/class-vapt-polylang.php';

// Initialize the plugin
function vapt_init() {
    // Check if Polylang is active
    if (!function_exists('pll_languages_list')) {
        add_action('admin_notices', 'vapt_polylang_missing_notice');
        return;
    }

    // Initialize plugin classes
    new VAPT_Core();
    if (is_admin()) {
        new VAPT_Admin();
    }
}
add_action('plugins_loaded', 'vapt_init');

// Admin notice for missing Polylang
function vapt_polylang_missing_notice() {
    $class = 'notice notice-error';
    $message = __('Vaalue AI-Powered Polylang Translator requires Polylang plugin to be installed and activated.', 'vaalue-ai-polylang-translator');
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}