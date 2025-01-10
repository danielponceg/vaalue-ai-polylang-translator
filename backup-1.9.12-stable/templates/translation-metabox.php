<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_lang = pll_get_post_language($post->ID);
$languages = pll_languages_list(array('fields' => 'name'));
$language_locales = pll_languages_list(array('fields' => 'locale'));
$language_map = array_combine($language_locales, $languages);
$translations = pll_get_post_translations($post->ID);

// Debug information
error_log('=== VAPT Debug Info Start ===');
error_log('Current Language: ' . print_r($current_lang, true));
error_log('Post ID: ' . $post->ID);
error_log('Post Title: ' . $post->post_title);
error_log('Post Status: ' . $post->post_status);
error_log('Language Map: ' . print_r($language_map, true));
error_log('Raw Translations Array: ' . print_r($translations, true));

foreach ($translations as $lang => $trans_id) {
    error_log("Translation Check - Language: $lang, Post ID: $trans_id");
    $trans_post = get_post($trans_id);
    if ($trans_post) {
        error_log("Translation Title: " . $trans_post->post_title);
        error_log("Translation Status: " . $trans_post->post_status);
    }
}

error_log('Language Locales: ' . print_r($language_locales, true));
error_log('Available Languages: ' . print_r(pll_languages_list(), true));
error_log('=== VAPT Debug Info End ===');

if (!$current_lang) {
    echo '<div class="notice notice-error inline"><p>' . esc_html__('Please set a language for this post first using the Language meta box.', 'vaalue-ai-polylang-translator') . '</p></div>';
    return;
}

$current_lang_name = isset($language_map[$current_lang]) ? $language_map[$current_lang] : $current_lang;
?>

<div class="vapt-translation-metabox">
    <p><?php printf(__('Current language: %s', 'vaalue-ai-polylang-translator'), 
        '<strong>' . esc_html($current_lang_name ?: $current_lang) . '</strong>'); ?></p>

    <div class="vapt-translation-status">
        <h4><?php _e('Translation Status:', 'vaalue-ai-polylang-translator'); ?></h4>
        <?php foreach ($language_locales as $lang): ?>
            <div class="vapt-language-status" data-lang="<?php echo esc_attr($lang); ?>">
                <span class="vapt-language"><?php echo esc_html($language_map[$lang]); ?>:</span>
                <span class="vapt-status-text<?php echo $lang === $current_lang ? ' current' : ''; ?>">
                <?php
                if ($lang === $current_lang) {
                    _e('Current', 'vaalue-ai-polylang-translator');
                } else {
                    $has_translation = isset($translations[substr($lang, 0, 2)]);
                    if ($has_translation && $translated_post = get_post($translations[substr($lang, 0, 2)])) {
                        printf(
                            '<a href="%s" target="_blank" class="vapt-translation-link vapt-translated">%s</a>',
                            get_edit_post_link($translations[substr($lang, 0, 2)]),
                            __('Translated', 'vaalue-ai-polylang-translator')
                        );
                    } else {
                        _e('Not translated', 'vaalue-ai-polylang-translator');
                    }
                }
                ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="vapt-actions">
        <button type="button" class="button vapt-translate-button" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <?php _e('Translate Now', 'vaalue-ai-polylang-translator'); ?>
        </button>
        <span class="spinner"></span>
    </div>
</div>

<div id="vapt-translation-dialog" class="vapt-dialog" style="display: none;">
    <div class="vapt-dialog-content">
        <h3><?php _e('Confirm Translation', 'vaalue-ai-polylang-translator'); ?></h3>
        
        <div class="vapt-model-selection">
            <h4><?php _e('Select AI Model:', 'vaalue-ai-polylang-translator'); ?></h4>
            <label class="vapt-model-option">
                <input type="radio" name="vapt_model" value="gpt-3.5-turbo" checked>
                <?php _e('GPT-3.5 Turbo (Faster, more economical)', 'vaalue-ai-polylang-translator'); ?>
            </label>
            <label class="vapt-model-option">
                <input type="radio" name="vapt_model" value="gpt-4">
                <?php _e('GPT-4 (Higher quality, more accurate)', 'vaalue-ai-polylang-translator'); ?>
            </label>
        </div>

        <div class="vapt-language-selection">
            <h4><?php _e('Select target languages:', 'vaalue-ai-polylang-translator'); ?></h4>
            <div class="vapt-language-options">
                <?php foreach ($language_locales as $lang): ?>
                    <?php if ($lang === $current_lang || isset($translations[substr($lang, 0, 2)])) continue; ?>
                    <label class="vapt-language-option">
                        <input type="checkbox" name="vapt_target_languages[]" value="<?php echo esc_attr($lang); ?>">
                        <?php echo esc_html($language_map[$lang]); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="vapt-dialog-buttons">
            <button type="button" class="button vapt-cancel-button"><?php _e('Cancel', 'vaalue-ai-polylang-translator'); ?></button>
            <button type="button" class="button button-primary vapt-confirm-button"><?php _e('Translate', 'vaalue-ai-polylang-translator'); ?></button>
        </div>
    </div>
</div>

<div id="vapt-results-dialog" class="vapt-dialog" style="display: none;">
    <div class="vapt-dialog-content">
        <h3><?php _e('Translation Results', 'vaalue-ai-polylang-translator'); ?></h3>
        <div class="vapt-results-content"></div>
        <div class="vapt-dialog-buttons">
            <button type="button" class="button button-primary vapt-close-results"><?php _e('Close', 'vaalue-ai-polylang-translator'); ?></button>
        </div>
    </div>
</div>