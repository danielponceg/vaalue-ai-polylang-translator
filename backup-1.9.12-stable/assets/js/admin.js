jQuery(document).ready(function($) {
    if ($('#vapt-translation-dialog').length === 0) {
        return;
    }

    // Dialog handlers
    $('.vapt-dialog').on('click', function(e) {
        if ($(e.target).hasClass('vapt-dialog')) {
            $(this).hide();
        }
    });

    $('.vapt-cancel-button').on('click', function() {
        $('#vapt-translation-dialog').hide();
    });

    $('.vapt-close-results').on('click', function() {
        $('#vapt-results-dialog').hide();
        location.reload();
    });

    $('.vapt-translate-button').on('click', function() {
        $('#vapt-translation-dialog').show();
    });

    $('.vapt-confirm-button').on('click', function() {
        const button = $('.vapt-translate-button');
        const postId = button.data('post-id');
        const selectedLanguages = [];
        const selectedModel = $('input[name="vapt_model"]:checked').val();

        $('input[name="vapt_target_languages[]"]:checked').each(function() {
            selectedLanguages.push($(this).val());
        });

        if (!postId || selectedLanguages.length === 0) {
            alert('Please select at least one language to translate');
            return;
        }

        $('#vapt-translation-dialog').hide();
        const spinner = button.siblings('.spinner');
        translate(postId, selectedLanguages, selectedModel, button, spinner);
    });

    function translate(postId, targetLanguages, model, button, spinner) {
        button.prop('disabled', true);
        spinner.addClass('is-active');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'vapt_translate_post',
                post_id: postId,
                target_languages: targetLanguages,
                model: model,
                nonce: vaptData.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    if (response.data.results) {
                        Object.entries(response.data.results).forEach(([lang, result]) => {
                            const statusElement = $(`.vapt-language-status[data-lang="${lang}"] .vapt-status-text`);
                            if (result && (result.success || result.edit_link)) {
                                const editLink = result.edit_link || '#';
                                statusElement.html(`<a href="${editLink}" target="_blank" class="vapt-translation-link vapt-translated">Translated</a>`);
                            } else {
                                statusElement.html('<span class="vapt-translation-error">Failed</span>');
                            }
                        });
                    }
                    showResults(response.data);
                } else {
                    showResults({
                        results: targetLanguages.reduce((acc, lang) => {
                            acc[lang] = { 
                                success: false, 
                                message: response.data?.message || 'Translation failed. Please try again.'
                            };
                            return acc;
                        }, {})
                    });
                }
            },
            error: function(xhr, status, error) {
                showResults({
                    results: targetLanguages.reduce((acc, lang) => {
                        acc[lang] = { 
                            success: false, 
                            message: 'Translation request failed: ' + error
                        };
                        return acc;
                    }, {})
                });
            },
            complete: function() {
                button.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    }

    function showResults(data) {
        const resultsContent = $('.vapt-results-content');
        resultsContent.empty();

        if (data.results) {
            Object.entries(data.results).forEach(([lang, result]) => {
                const isSuccess = result && (
                    result === true || 
                    result.success === true || 
                    (typeof result === 'object' && result.edit_link)
                );
                
                const className = isSuccess ? 'vapt-result-success' : 'vapt-result-error';
                let message;
                
                if (isSuccess) {
                    const langName = lang.split('_')[0].toUpperCase();
                    message = `Successfully created translation in ${langName}`;
                    if (result.edit_link) {
                        message = `<a href="${result.edit_link}" target="_blank">${message}</a>`;
                    }
                } else {
                    const errorMsg = (result && result.message) ? result.message : 'Unknown error';
                    message = `Failed to translate to ${lang}: ${errorMsg}`;
                }

                resultsContent.append(
                    `<div class="vapt-result-item ${className}">${message}</div>`
                );
            });
        }

        $('#vapt-results-dialog').show();
    }
});