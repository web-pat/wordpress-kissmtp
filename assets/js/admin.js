(function ($) {
    'use strict';

    var $recipient = $('#kissmtp_test_recipient');
    var $button = $('#kissmtp_send_test');
    var $spinner = $('.kissmtp-test-form .spinner');
    var $result = $('#kissmtp_test_result');
    var buttonText = $button.text();

    $button.on('click', function () {
        var email = $recipient.val().trim();
        if (!email) {
            showResult('error', 'Please enter a recipient email address.');
            return;
        }

        setLoading(true);
        $.post(kissmtpAdmin.ajaxUrl, {
            action: 'kissmtp_test_email',
            nonce: kissmtpAdmin.nonce,
            recipient: email
        }).done(function (response) {
            showResult(response.success ? 'success' : 'error', response.data.message || kissmtpAdmin.error);
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : kissmtpAdmin.error;
            showResult('error', message);
        }).always(function () {
            setLoading(false);
        });
    });

    function setLoading(loading) {
        $button.prop('disabled', loading).text(loading ? kissmtpAdmin.sending : buttonText);
        $recipient.prop('disabled', loading);
        $spinner.toggleClass('is-active', loading);
    }

    function showResult(type, message) {
        $result.removeClass('success error').addClass(type).text(message).show();
    }
})(jQuery);
