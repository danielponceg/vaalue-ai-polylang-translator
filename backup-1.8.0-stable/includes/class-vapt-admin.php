<?php
class VAPT_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('add_meta_boxes', array($this, 'add_translation_metabox'));
        add_action('wp_ajax_vapt_translate_post', array($this, 'handle_translation_request'));
    }

    public function add_admin_menu() {
        add_options_page(
            __('AI Translator Settings', 'vaalue-ai-polylang-translator'),
            __('AI Translator', 'vaalue-ai-polylang-translator'),
            'manage_options',
            'vaalue-ai-polylang-translator',
            array($this, 'render_settings_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        // Load on settings page and post edit screens
        if (!in_array($hook, array('settings_page_vaalue-ai-polylang-translator', 'post.php', 'post-new.php'))) {
            return;
        }

        wp_enqueue_style(
            'vapt-admin-css',
            VAPT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            VAPT_VERSION
        );

        wp_enqueue_script(
            'vapt-admin-js',
            VAPT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            VAPT_VERSION,
            true
        );
        
        wp_localize_script('vapt-admin-js', 'vaptData', array(
            'nonce' => wp_create_nonce('vapt_translate_nonce')
        ));
    }

    public function render_settings_page() {
        require_once VAPT_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    public function add_translation_metabox() {
        $post_types = get_post_types(array('public' => true));
        foreach ($post_types as $post_type) {
            add_meta_box(
                'vapt_translation_metabox',
                __('AI Translation', 'vaalue-ai-polylang-translator'),
                array($this, 'render_translation_metabox'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    public function render_translation_metabox($post) {
        require_once VAPT_PLUGIN_DIR . 'templates/translation-metabox.php';
    }
    
    public function handle_translation_request() {
        // Set time limit for long-running translations
        set_time_limit(300); // 5 minutes max execution time
        
        // Limit memory usage
        ini_set('memory_limit', '512M');
        
        check_ajax_referer('vapt_translate_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $target_languages = isset($_POST['target_languages']) ? (array)$_POST['target_languages'] : array();
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'gpt-3.5-turbo';

        // Validate model
        if (!in_array($model, array('gpt-3.5-turbo', 'gpt-4'))) {
            wp_send_json_error(array('message' => 'Invalid model selected'));
            return;
        }

        // Limit number of simultaneous translations
        if (count($target_languages) > 5) {
            wp_send_json_error(array('message' => 'Please select 5 or fewer languages at a time'));
            return;
        }

        if (!$post_id) {
            wp_send_json_error(array('message' => 'Invalid post ID'));
            return;
        }
        
        if (empty($target_languages)) {
            wp_send_json_error(array('message' => 'No target languages selected'));
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => 'Post not found'));
            return;
        }
        
        // Check if a translation is already in progress
        $translation_lock = get_transient('vapt_translating_' . $post_id);
        if ($translation_lock) {
            wp_send_json_error(array('message' => 'A translation is already in progress for this post'));
            return;
        }
        
        // Set translation lock
        set_transient('vapt_translating_' . $post_id, true, 5 * MINUTE_IN_SECONDS);
        
        try {
            $polylang = new VAPT_Polylang();
            $result = $polylang->translate_post($post_id, $target_languages, $model);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            } else {
                $response_data = array(
                    'message' => 'Translation completed',
                    'results' => array()
                );

                foreach ($result as $lang => $translation_result) {
                    if (is_wp_error($translation_result)) {
                        $response_data['results'][$lang] = array(
                            'success' => false,
                            'message' => $translation_result->get_error_message()
                        );
                    } else if (is_array($translation_result) && isset($translation_result['success']) && $translation_result['success']) {
                        // Handle successful translation
                        $response_data['results'][$lang] = array(
                            'success' => true,
                            'edit_link' => $translation_result['edit_link'] ?? get_edit_post_link($translation_result['post_id'], '')
                        );
                    } else {
                        // Fallback for unexpected result format
                        $response_data['results'][$lang] = array(
                            'success' => false,
                            'message' => __('Unexpected translation result format', 'vaalue-ai-polylang-translator')
                        );
                        error_log('VAPT: Unexpected translation result format for ' . $lang . ': ' . print_r($translation_result, true));
                    }
                }

                // Check if any translations were successful
                $has_success = false;
                foreach ($response_data['results'] as $result) {
                    if ($result['success']) {
                        $has_success = true;
                        break;
                    }
                }

                if ($has_success) {
                    wp_send_json_success($response_data);
                } else {
                    wp_send_json_error(array(
                        'message' => __('No translations were successful', 'vaalue-ai-polylang-translator'),
                        'results' => $response_data['results']
                    ));
                }
            }
        } catch (Exception $e) {
            error_log('VAPT Translation Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Translation failed: ' . $e->getMessage()));
        } finally {
            // Always remove the translation lock
            delete_transient('vapt_translating_' . $post_id);
        }
    }
}