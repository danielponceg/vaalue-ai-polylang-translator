<?php
class VAPT_OpenAI {
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $max_retries = 3;
    private $timeout = 60;
    private $retry_delay = 5;

    public function __construct() {
        $this->api_key = get_option('vapt_openai_api_key');
    }

    public function translate_text($text, $target_language) {
        if (empty($text)) {
            return new WP_Error('empty_text', __('No text provided for translation.', 'vaalue-ai-polylang-translator'));
        }

        if (empty($this->api_key)) {
            error_log('VAPT OpenAI Error: API key is missing');
            return new WP_Error('missing_api_key', __('OpenAI API key is not configured.', 'vaalue-ai-polylang-translator'));
        }

        error_log(sprintf('VAPT OpenAI: Starting translation to %s. Text length: %d', 
            $target_language, 
            strlen($text)
        ));

        // Extract base language code
        $lang_code = substr($target_language, 0, 2);
        $language_map = array(
            'en' => 'English',
            'es' => 'Spanish',
            'pt' => 'Portuguese',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian'
        );

        $target_name = isset($language_map[$lang_code]) ? $language_map[$lang_code] : $target_language;

        $attempt = 0;
        do {
            $attempt++;
            if ($attempt > 1) {
                $delay = $this->retry_delay * ($attempt - 1);
                error_log(sprintf('VAPT OpenAI: Retry attempt %d, waiting %d seconds', $attempt, $delay));
                sleep($delay);
            }

            $response = wp_remote_post($this->api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'model' => get_option('vapt_translation_model', 'gpt-4'),
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => sprintf(
                                'You are a professional translator. Translate the following text to %s. Maintain the original formatting, tone, and HTML tags if present.',
                                esc_html($target_name)
                            )
                        ),
                        array(
                            'role' => 'user',
                            'content' => $text
                        )
                    ),
                    'temperature' => 0.3
                )),
                'timeout' => $this->timeout
            ));

            if (is_wp_error($response)) {
                error_log('VAPT OpenAI Error: ' . $response->get_error_message());
                if ($attempt >= $this->max_retries) {
                    return $response;
                }
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            
            // Handle rate limits
            if ($response_code === 429) {
                error_log('VAPT OpenAI: Rate limit exceeded');
                if ($attempt >= $this->max_retries) {
                    return new WP_Error('rate_limit', __('OpenAI API rate limit exceeded. Please try again later.', 'vaalue-ai-polylang-translator'));
                }
                continue;
            }

            if ($response_code === 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($body['choices'][0]['message']['content'])) {
                    error_log(sprintf('VAPT OpenAI: Translation successful after %d attempts', $attempt));
                    return $body['choices'][0]['message']['content'];
                }
            }

            error_log(sprintf('VAPT OpenAI: Unexpected response (code: %d)', $response_code));
        } while ($attempt < $this->max_retries);

        return new WP_Error('translation_failed', __('Translation failed after multiple attempts. Please try again.', 'vaalue-ai-polylang-translator'));
    }
}