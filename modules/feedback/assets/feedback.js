/**
 * TPW Feedback Form AJAX Submission
 */
jQuery(document).ready(function ($) {
    const $forms = $('.tpw-feedback-form');

    if (!$forms.length) {
        return;
    }

    $forms.on('submit', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $status = $form.find('.tpw-feedback-status');

        $status.removeClass('success error').text('');

        const formData = $form.serialize();

        $.ajax({
            url: TPW_FEEDBACK.ajaxurl,
            method: 'POST',
            data: formData,
            dataType: 'json',
            beforeSend: function () {
                $status.text('Sending...');
            },
            success: function (response) {
                if (response.success) {
                    $status.addClass('success').text(TPW_FEEDBACK.i18n.thanks);
                    // Optionally clear form inputs
                    $form.find('input[type="radio"], input[type="text"], textarea').val('').prop('checked', false);
                } else {
                    const msg = response.data && response.data.message ? response.data.message : TPW_FEEDBACK.i18n.error;
                    $status.addClass('error').text(msg);
                }
            },
            error: function () {
                $status.addClass('error').text(TPW_FEEDBACK.i18n.error);
            }
        });
    });
});