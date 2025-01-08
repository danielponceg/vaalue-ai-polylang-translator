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
        const button = $(this);
        const postId = button.data('post-id');
        const selectedLanguages = [];

        console.log('Translation requested for post ID:', postId);

        if (!postId) {
            alert('Error: No post ID found');
            return;
        }

        // Get selected languages from checkboxes
        $('input.vapt-language-checkbox:checked').each(function() {
            selectedLanguages.push($(this).val());
        });

        console.log('Selected languages:', selectedLanguages);

        if (selectedLanguages.length === 0) {
            alert('Please select at least one language to translate');
            return;
        }

        const spinner = button.siblings('.spinner');
        translate(postId, selectedLanguages, button, spinner);
    });

    function translate(postId, targetLanguages, button, spinner) {
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
                nonce: vaptData.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Translation successful:', response);
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
                const isSuccess = result === true;
                const className = isSuccess ? 'vapt-result-success' : 'vapt-result-error';
                const message = isSuccess 
                    ? `Successfully created translation in ${lang}`
                    : `Failed to translate to ${lang}: ${result.message || 'Unknown error'}`;

                resultsContent.append(
                    `<div class="vapt-result-item ${className}">${message}</div>`
                );
            });
        }

        $('#vapt-results-dialog').show();
    }
});