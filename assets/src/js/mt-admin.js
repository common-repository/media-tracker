jQuery(document).ready(function($) {
    /**
     * Deactivate Popup Modal
     * */
    var deactivateLink = $('#the-list').find('[data-slug="media-tracker"] .deactivate a');

    if (deactivateLink.length) {
        deactivateLink.on('click', function(e) {
            e.preventDefault();
            $('#mt-feedback-modal').show();
        });
    }

    $('#mt-submit-feedback').on('click', function() {
        var feedback = $('textarea[name="feedback"]').val();

        $.post(mediaTacker.ajax_url, {
            action: 'mt_save_feedback',
            feedback: feedback,
            nonce: mediaTacker.nonce
        }, function(response) {
            if (response.success) {
                window.location.href = deactivateLink.attr('href');
            } else {
                alert('There was an error. Please try again.');
            }
        });
    });

    $('#mt-skip-feedback').on('click', function() {
        window.location.href = deactivateLink.attr('href');
    });

    // Close modal when clicking outside of it
    $(window).on('click', function(e) {
        if ($(e.target).is('#mt-feedback-modal')) {
            $('#mt-feedback-modal').hide();
        }
    });

    // Optional: Add a close button inside the modal if you prefer
    $('#mt-feedback-modal').append('<span class="close">&times;</span>');
    $('#mt-feedback-modal .close').on('click', function() {
        $('#mt-feedback-modal').hide();
    });


    // Broken Media Link Detection: quick-edit-form
    $('.editinline').on('click', function(e) {
        e.preventDefault();

        const classList = $(this).attr('class').split(/\s+/);
        let targetClass = '';

        classList.forEach(function(cls) {
            if (cls.startsWith('quick-edit-item-')) {
                targetClass = cls;
            }
        });

        $('.quick-edit-form').addClass('hidden');
        $('.' + targetClass).removeClass('hidden');
    });

    $('.quick-edit-form .cancel').on('click', function() {
        $(this).closest('.quick-edit-form').addClass('hidden');
    });

    // clear-broken-links-transient
    $('#clear-broken-links-transient').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var nonce = button.data('nonce');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'clear_broken_links_transient',
                nonce: nonce
            },
            beforeSend: function() {
                button.text('Clearing...').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    if (response.data) {
                        // Show success message
                        $('#success-message').text('Transient cache cleared successfully!').fadeIn();

                        // Reload after a short delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                }
            },
            complete: function() {
                button.text('Clear Transient Cache').prop('disabled', false);
            }
        });
    });
});
