(function($) {
    'use strict';

    var $passwordField = $('#kissmtp_password');
    var $toggleButton = $('.kissmtp-toggle-password');
    var $testRecipient = $('#kissmtp_test_recipient');
    var $sendTestBtn = $('#kissmtp_send_test');
    var $testResult = $('#kissmtp_test_result');
    var $spinner = $('.kissmtp-test-form .spinner');
    var originalButtonText = $sendTestBtn.text();

    $toggleButton.on('click', function() {
        var type = $passwordField.attr('type') === 'password' ? 'text' : 'password';
        $passwordField.attr('type', type);
        $toggleButton.find('.dashicons').toggleClass('dashicons-hidden dashicons-visibility');
    });

    $testRecipient.on('input', function() {
        $sendTestBtn.prop('disabled', !isEmail($(this).val()));
    });

    $sendTestBtn.on('click', function() {
        var recipient = $testRecipient.val().trim();

        if (!isEmail(recipient)) {
            showResult('error', kissmtp_ajax.error_valid);
            return;
        }

        setLoading(true);
        $testResult.hide().removeClass('success error');

        $.post(kissmtp_ajax.ajax_url, {
            action: 'kissmtp_test_email',
            nonce: kissmtp_ajax.nonce,
            recipient: recipient
        })
        .done(function(response) {
            if (response.success) {
                showResult('success', response.data.message);
            } else {
                showResult('error', response.data.message || kissmtp_ajax.error_send);
            }
        })
        .fail(function() {
            showResult('error', kissmtp_ajax.error_send);
        })
        .always(function() {
            setLoading(false);
        });
    });

    function isEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function setLoading(loading) {
        $sendTestBtn.prop('disabled', loading);
        $testRecipient.prop('disabled', loading);
        if (loading) {
            $sendTestBtn.text(kissmtp_ajax.sending);
            $spinner.addClass('is-active');
        } else {
            $sendTestBtn.text(originalButtonText);
            $spinner.removeClass('is-active');
        }
    }

    function showResult(type, message) {
        $testResult
            .removeClass('success error')
            .addClass(type)
            .text(message)
            .slideDown();
    }
})(jQuery);