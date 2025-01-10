<?php
class VAPT_OpenAI {
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $max_retries = 3;
    private $timeout = 60;
    private $retry_delay = 2;
    private $max_chunk_size = 4000;
    private $max_tokens_map = array(
        'gpt-3.5-turbo' => 4000,
        'gpt-4' => 8000
    );

    public function __construct() {
        $this->api_key = get_option('vapt_openai_api_key');
    }

    public function translate_text($text, $target_language, $model = null) {
        if (empty($text)) {
            return new WP_Error('empty_text', __('No text provided for translation.', 'vaalue-ai-polylang-translator'));
        }

        if (empty($this->api_key)) {
            error_log('VAPT OpenAI Error: API key is missing');
            return new WP_Error('missing_api_key', __('OpenAI API key is not configured.', 'vaalue-ai-polylang-translator'));
        }

        // Use provided model or default to gpt-3.5-turbo
        $model = $model ?: 'gpt-3.5-turbo';
        
        // Adjust chunk size based on model
        $this->max_chunk_size = min(
            $this->max_chunk_size,
            intval($this->max_tokens_map[$model] * 0.75) // Leave room for response
        );

        // Split long text into chunks
        if (mb_strlen($text) > $this->max_chunk_size) {
            return $this->translate_long_text($text, $target_language, $model);
        }

        error_log(sprintf('VAPT OpenAI: Starting translation to %s. Text length: %d, Model: %s', 
            $target_language, 
            mb_strlen($text),
            $model
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
                'timeout' => $this->timeout,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'model' => $model,
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => "You are a professional translator. Translate the following text to $target_name. Maintain the original formatting, structure, and HTML tags if present. Do not add any explanations or notes."
                        ),
                        array(
                            'role' => 'user',
                            'content' => $text
                        )
                    ),
                    'temperature' => 0.3,
                    'max_tokens' => $this->max_tokens_map[$model]
                ))
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
                sleep(10); // Increased wait time for rate limits
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
            error_log('Response body: ' . wp_remote_retrieve_body($response));
        } while ($attempt < $this->max_retries);

        return new WP_Error('translation_failed', __('Translation failed after multiple attempts. Please try again.', 'vaalue-ai-polylang-translator'));
    }

    private function translate_long_text($text, $target_language, $model) {
        // Use smart text splitting
        $chunks = $this->smart_text_split($text);
        $translated_chunks = array();
        
        error_log(sprintf('VAPT OpenAI: Starting chunked translation with %d chunks', count($chunks)));
        
        foreach ($chunks as $index => $chunk) {
            error_log(sprintf('VAPT OpenAI: Translating chunk %d of %d (length: %d)', 
                $index + 1, 
                count($chunks),
                mb_strlen($chunk)
            ));
            
            $result = $this->translate_text($chunk, $target_language, $model);
            if (is_wp_error($result)) {
                error_log('VAPT OpenAI: Chunk translation failed: ' . $result->get_error_message());
                return $result;
            }
            $translated_chunks[] = $result;
            
            // Progressive delay between chunks based on model
            if ($index < count($chunks) - 1) {
                $delay = ($model === 'gpt-4') ? 3 : 1;
                sleep($delay);
            }
        }

        error_log('VAPT OpenAI: All chunks translated successfully');
        return implode("\n\n", $translated_chunks);
    }

    private function smart_text_split($text) {
        // Split text into semantic chunks (paragraphs, headers, lists)
        $chunks = array();
        $current_chunk = '';
        
        // Split by double newline first
        $paragraphs = preg_split('/\n\s*\n/', $text);
        
        foreach ($paragraphs as $paragraph) {
            // If adding this paragraph would exceed chunk size
            if (mb_strlen($current_chunk) + mb_strlen($paragraph) > $this->max_chunk_size) {
                if (!empty($current_chunk)) {
                    $chunks[] = $current_chunk;
                }
                
                // If single paragraph is longer than chunk size, split by sentences
                if (mb_strlen($paragraph) > $this->max_chunk_size) {
                    $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph);
                    $current_chunk = '';
                    
                    foreach ($sentences as $sentence) {
                        if (mb_strlen($current_chunk) + mb_strlen($sentence) > $this->max_chunk_size) {
                            if (!empty($current_chunk)) {
                                $chunks[] = $current_chunk;
                            }
                            $current_chunk = $sentence;
                        } else {
                            $current_chunk .= (!empty($current_chunk) ? ' ' : '') . $sentence;
                        }
                    }
                } else {
                    $current_chunk = $paragraph;
                }
            } else {
                $current_chunk .= (!empty($current_chunk) ? "\n\n" : '') . $paragraph;
            }
        }
        
        if (!empty($current_chunk)) {
            $chunks[] = $current_chunk;
        }
        
        return $chunks;
    }
}