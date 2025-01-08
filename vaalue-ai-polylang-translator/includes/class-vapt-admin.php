<?php
class VAPT_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('add_meta_boxes', array($this, 'add_translation_metabox'));
        add_action('wp_ajax_vapt_translate_post', array($this, 'handle_translation_request'));
        add_action('wp_ajax_vapt_check_translation_status', array($this, 'check_translation_status'));
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
        check_ajax_referer('vapt_translate_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $target_languages = isset($_POST['target_languages']) ? (array)$_POST['target_languages'] : array();
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'gpt-3.5-turbo';

        // Validate inputs...
        if (!$post_id || empty($target_languages)) {
            wp_send_json_error(array('message' => 'Invalid request parameters'));
            return;
        }

        // Calculate estimated time based on content length and language count
        $post = get_post($post_id);
        $total_length = mb_strlen($post->post_title) + mb_strlen($post->post_content);
        $estimated_time = $this->calculate_estimated_time($total_length, count($target_languages), $model);

        // Store translation job info
        $job_id = uniqid('vapt_', true);
        $translation_job = array(
            'post_id' => $post_id,
            'languages' => $target_languages,
            'model' => $model,
            'start_time' => time(),
            'estimated_time' => $estimated_time,
            'status' => 'processing',
            'results' => array()
        );
        set_transient($job_id, $translation_job, 24 * HOUR_IN_SECONDS);

        // Start background processing
        wp_schedule_single_event(time(), 'vapt_process_translation', array($job_id));

        // Return immediately with job ID and estimated time
        wp_send_json_success(array(
            'job_id' => $job_id,
            'message' => sprintf(
                __('Translation started. Estimated time: %d minutes', 'vaalue-ai-polylang-translator'),
                ceil($estimated_time / 60)
            ),
            'estimated_time' => $estimated_time
        ));
    }

    private function calculate_estimated_time($content_length, $language_count, $model) {
        // Base time per 1000 characters (in seconds)
        $base_time = ($model === 'gpt-4') ? 8 : 5;
        
        // Calculate basic content processing time
        $content_time = ($content_length / 1000) * $base_time;
        
        // Add overhead for each language
        $language_overhead = 5; // seconds per language
        
        // Total estimated time in seconds
        return ($content_time * $language_count) + ($language_overhead * $language_count);
    }

    public function check_translation_status() {
        check_ajax_referer('vapt_translate_nonce', 'nonce');
        
        $job_id = sanitize_text_field($_POST['job_id']);
        $translation_job = get_transient($job_id);

        if (!$translation_job) {
            wp_send_json_error(array('message' => 'Translation job not found'));
            return;
        }

        $elapsed_time = time() - $translation_job['start_time'];
        $progress = min(95, ($elapsed_time / $translation_job['estimated_time']) * 100);

        if ($translation_job['status'] === 'completed') {
            wp_send_json_success(array(
                'status' => 'completed',
                'results' => $translation_job['results']
            ));
        } else {
            wp_send_json_success(array(
                'status' => 'processing',
                'progress' => $progress,
                'message' => sprintf(
                    __('Translation in progress (%d%%). Please wait...', 'vaalue-ai-polylang-translator'),
                    $progress
                )
            ));
        }
    }
}