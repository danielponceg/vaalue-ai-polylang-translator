<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields('vapt_settings');
        do_settings_sections('vapt_settings');
        ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="vapt_openai_api_key"><?php _e('OpenAI API Key', 'vaalue-ai-polylang-translator'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="vapt_openai_api_key" 
                           name="vapt_openai_api_key" 
                           value="<?php echo esc_attr(get_option('vapt_openai_api_key')); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Enter your OpenAI API key. Get it from your OpenAI dashboard.', 'vaalue-ai-polylang-translator'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="vapt_translation_model"><?php _e('AI Model', 'vaalue-ai-polylang-translator'); ?></label>
                </th>
                <td>
                    <select id="vapt_translation_model" name="vapt_translation_model">
                        <option value="gpt-4" <?php selected(get_option('vapt_translation_model'), 'gpt-4'); ?>>
                            GPT-4 (<?php _e('Recommended', 'vaalue-ai-polylang-translator'); ?>)
                        </option>
                        <option value="gpt-3.5-turbo" <?php selected(get_option('vapt_translation_model'), 'gpt-3.5-turbo'); ?>>
                            GPT-3.5 Turbo
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Select the AI model to use for translations.', 'vaalue-ai-polylang-translator'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Auto Translation', 'vaalue-ai-polylang-translator'); ?>
                </th>
                <td>
                    <label for="vapt_auto_translate">
                        <input type="checkbox" 
                               id="vapt_auto_translate" 
                               name="vapt_auto_translate" 
                               value="1" 
                               <?php checked(get_option('vapt_auto_translate'), 1); ?>>
                        <?php _e('Automatically translate posts when published', 'vaalue-ai-polylang-translator'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>