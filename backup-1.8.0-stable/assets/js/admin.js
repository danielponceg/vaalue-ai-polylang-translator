jQuery(document).ready(function($) {
    console.log('AI Translator initialized');

    // Check if translation dialog exists
    if ($('#vapt-translation-dialog').length === 0) {
        console.error('Translation dialog not found');
        return;
    }

    // Close dialog when clicking outside
    $('.vapt-dialog').on('click', function(e) {
        if ($(e.target).hasClass('vapt-dialog')) {
            $(this).hide();
        }
    });

    // Cancel button handler
    $('.vapt-cancel-button').on('click', function() {
        $('#vapt-translation-dialog').hide();
    });

    // Close results button handler
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

        console.log('Translation requested for post ID:', postId);
        console.log('Selected languages:', selectedLanguages);
        console.log('Selected model:', selectedModel);

        if (!postId) {
            alert('Error: No post ID found');
            return;
        }

        if (selectedLanguages.length === 0) {
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

        console.log('Starting translation for languages:', targetLanguages);

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
                if (response.success) {
                    console.log('Translation successful:', response);
                    targetLanguages.forEach(lang => {
                        const langCode = lang.substring(0, 2);
                        const statusElement = $(`.vapt-language-status[data-lang="${lang}"] .vapt-status-text`);
                        const editLink = response.data.results[lang]?.edit_link || '#';
                        statusElement.html(`<a href="${editLink}" target="_blank" class="vapt-translation-link vapt-translated">Translated</a>`);
                    });
                    showResults(response.data);
                } else {
                    console.error('Translation failed:', response);
                    alert(response.data?.message || 'Translation failed. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', {xhr, status, error});
                alert('Translation failed: ' + error);
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

        console.log('Showing translation results:', data);

        if (data.results) {
            Object.entries(data.results).forEach(([lang, result]) => {
                const isSuccess = result === true || 
                                (typeof result === 'object' && (result.success === true || result.edit_link));
                const className = isSuccess ? 'vapt-result-success' : 'vapt-result-error';
                let message;
                
                if (isSuccess) {
                    const langName = lang.split('_')[0].toUpperCase();
                    message = `Successfully created translation in ${langName}`;
                    if (result.edit_link) {
                        message = `<a href="${result.edit_link}" target="_blank">${message}</a>`;
                    }
                } else {
                    message = `Failed to translate to ${lang}: ${result.message || 'Unknown error'}`;
                }

                resultsContent.append(
                    `<div class="vapt-result-item ${className}">${message}</div>`
                );
            });
        }

        $('#vapt-results-dialog').show();
    }
});