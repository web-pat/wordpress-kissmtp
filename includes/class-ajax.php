<?php
if (!defined('ABSPATH')) {
    exit;
}

class KISSMTP_Ajax {
    public function init() {
        add_action('wp_ajax_kissmtp_test_email', [$this, 'handle_test_email']);
    }

    public function handle_test_email() {
        check_ajax_referer('kissmtp_test_email', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'kissmtp')]);
        }

        $recipient = isset($_POST['recipient']) ? sanitize_email(wp_unslash($_POST['recipient'])) : '';

        if (!is_email($recipient)) {
            wp_send_json_error(['message' => __('Please enter a valid email address.', 'kissmtp')]);
        }

        global $kissmtp_instance;
        $kissmtp = $kissmtp_instance ?? new KISSMTP();
        $result = $kissmtp->test_connection($recipient);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => sprintf(__('Test email sent successfully to %s!', 'kissmtp'), $recipient)]);
    }
}