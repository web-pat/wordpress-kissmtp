<?php

namespace KissMtp;

final class Admin {
    private $repository;
    private $credentials;
    private $mailer;

    public function __construct( SettingsRepository $repository, CredentialStore $credentials, MailerConfigurator $mailer ) {
        $this->repository  = $repository;
        $this->credentials = $credentials;
        $this->mailer      = $mailer;
    }

    public function register() {
        add_action( 'admin_menu', array( $this, 'add_page' ) );
        add_action( 'admin_post_kissmtp_save_settings', array( $this, 'save_settings' ) );
        add_action( 'wp_ajax_kissmtp_test_email', array( $this, 'test_email' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function add_page() {
        add_options_page(
            __( 'KISSMTP Settings', 'kissmtp' ),
            __( 'KISSMTP', 'kissmtp' ),
            'manage_options',
            'kissmtp',
            array( $this, 'render_page' )
        );
    }

    public function save_settings() {
        $this->authorize( 'kissmtp_save_settings' );
        $input    = isset( $_POST['kissmtp'] ) && is_array( $_POST['kissmtp'] ) ? wp_unslash( $_POST['kissmtp'] ) : array();
        $current  = $this->repository->get();
        $input['enabled']     = ! empty( $input['enabled'] );
        $input['auth']        = ! empty( $input['auth'] );
        $input['return_path'] = ! empty( $input['return_path'] );
        $settings = Settings::normalize( $input );
        $errors   = array();

        if ( defined( 'KISSMTP_PASSWORD' ) ) {
            $settings['password'] = $current['password'];
        } elseif ( isset( $input['password'] ) && is_scalar( $input['password'] ) && '' !== (string) $input['password'] ) {
            $encrypted = $this->credentials->encrypt( (string) $input['password'] );
            if ( is_wp_error( $encrypted ) ) {
                $errors[] = $encrypted->get_error_message();
                $settings['password'] = $current['password'];
            } else {
                $settings['password'] = $encrypted;
            }
        } elseif ( ! empty( $input['clear_password'] ) ) {
            $settings['password'] = '';
        } else {
            $settings['password'] = $current['password'];
        }

        $errors = array_merge( $errors, Settings::validate( $settings, $this->credentials->has_password( $settings ) ) );
        if ( $errors ) {
            set_transient( 'kissmtp_admin_errors_' . get_current_user_id(), $errors, MINUTE_IN_SECONDS );
            $this->redirect();
        }

        $this->repository->save( $settings );
        $this->redirect( array( 'updated' => '1' ) );
    }

    public function test_email() {
        check_ajax_referer( 'kissmtp_test_email', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kissmtp' ) ), 403 );
        }

        $rate_key = 'kissmtp_test_rate_' . get_current_user_id();
        if ( get_transient( $rate_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Please wait before sending another test email.', 'kissmtp' ) ), 429 );
        }

        $recipient_input = isset( $_POST['recipient'] ) ? wp_unslash( $_POST['recipient'] ) : '';
        $recipient = is_scalar( $recipient_input ) ? sanitize_email( (string) $recipient_input ) : '';
        if ( ! is_email( $recipient ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'kissmtp' ) ), 400 );
        }

        $settings = $this->repository->get();
        if ( ! Settings::is_usable( $settings, $this->credentials->has_password( $settings ) ) ) {
            wp_send_json_error( array( 'message' => __( 'Save a complete enabled SMTP configuration before sending a test email.', 'kissmtp' ) ), 400 );
        }

        if ( $settings['auth'] && is_wp_error( $this->credentials->decrypt( $settings['password'] ) ) ) {
            wp_send_json_error( array( 'message' => __( 'The stored SMTP password is unavailable. Enter it again before testing.', 'kissmtp' ) ), 400 );
        }

        set_transient( $rate_key, 1, 30 );
        $used_transport = false;
        $subject = __( 'KISSMTP Test Email', 'kissmtp' );
        $observe = function ( $data ) use ( &$used_transport, $recipient, $subject ) {
            // Ignore unrelated messages sent recursively by mail/logging plugins.
            if ( $subject !== $data['subject'] || array( $recipient ) !== (array) $data['to'] ) {
                return;
            }
            global $phpmailer;
            $used_transport = $this->mailer->is_configured( $phpmailer );
        };
        // Inspect the effective transport before the configurator restores shared state.
        add_action( 'wp_mail_succeeded', $observe, 0 );
        try {
            $sent = wp_mail(
                $recipient,
                $subject,
                __( 'This message was accepted for delivery by WordPress using the configured SMTP transport.', 'kissmtp' ),
                array( 'Content-Type: text/plain; charset=UTF-8' )
            );
        } finally {
            remove_action( 'wp_mail_succeeded', $observe, 0 );
            $this->mailer->restore();
        }

        if ( ! $sent ) {
            wp_send_json_error( array( 'message' => __( 'WordPress could not submit the test email. Check the SMTP settings and your mail server logs.', 'kissmtp' ) ), 500 );
        }

        if ( ! $used_transport ) {
            wp_send_json_error( array( 'message' => __( 'Another mail handler intercepted the test or changed the transport. WordPress reported success, but the KISSMTP configuration was not verified.', 'kissmtp' ) ), 409 );
        }

        wp_send_json_success( array( 'message' => __( 'WordPress submitted the test email to the configured transport. Check the recipient inbox to confirm delivery.', 'kissmtp' ) ) );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = $this->repository->get();
        $errors   = get_transient( 'kissmtp_admin_errors_' . get_current_user_id() );
        delete_transient( 'kissmtp_admin_errors_' . get_current_user_id() );
        ?>
        <div class="wrap kissmtp-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'kissmtp' ); ?></p></div>
            <?php endif; ?>
            <?php if ( is_array( $errors ) ) : ?>
                <div class="notice notice-error"><p><?php echo esc_html( implode( ' ', $errors ) ); ?></p></div>
            <?php endif; ?>
            <?php if ( ! defined( 'KISSMTP_PASSWORD' ) && ! $this->credentials->is_available() ) : ?>
                <div class="notice notice-warning"><p><?php esc_html_e( 'OpenSSL AES-256-GCM is unavailable. Define KISSMTP_PASSWORD in wp-config.php to use authenticated SMTP.', 'kissmtp' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="kissmtp_save_settings">
                <?php wp_nonce_field( 'kissmtp_save_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><?php esc_html_e( 'Enable SMTP', 'kissmtp' ); ?></th><td><label><input type="checkbox" name="kissmtp[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Route WordPress mail through this SMTP server', 'kissmtp' ); ?></label></td></tr>
                    <tr><th scope="row"><label for="kissmtp_host"><?php esc_html_e( 'SMTP host', 'kissmtp' ); ?></label></th><td><input id="kissmtp_host" class="regular-text" name="kissmtp[host]" value="<?php echo esc_attr( $settings['host'] ); ?>" autocomplete="off"><p class="description"><?php esc_html_e( 'Hostname or IPv4 address, without a protocol or port.', 'kissmtp' ); ?></p></td></tr>
                    <tr><th scope="row"><label for="kissmtp_port"><?php esc_html_e( 'SMTP port', 'kissmtp' ); ?></label></th><td><input id="kissmtp_port" class="small-text" type="number" min="1" max="65535" name="kissmtp[port]" value="<?php echo esc_attr( $settings['port'] ); ?>"></td></tr>
                    <tr><th scope="row"><label for="kissmtp_encryption"><?php esc_html_e( 'Encryption', 'kissmtp' ); ?></label></th><td><select id="kissmtp_encryption" name="kissmtp[encryption]"><option value="tls" <?php selected( $settings['encryption'], 'tls' ); ?>><?php esc_html_e( 'STARTTLS', 'kissmtp' ); ?></option><option value="ssl" <?php selected( $settings['encryption'], 'ssl' ); ?>><?php esc_html_e( 'Implicit TLS (port 465)', 'kissmtp' ); ?></option><option value="none" <?php selected( $settings['encryption'], 'none' ); ?>><?php esc_html_e( 'None', 'kissmtp' ); ?></option></select></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'Authentication', 'kissmtp' ); ?></th><td><label><input type="checkbox" name="kissmtp[auth]" value="1" <?php checked( $settings['auth'] ); ?>> <?php esc_html_e( 'Require a username and password', 'kissmtp' ); ?></label></td></tr>
                    <tr><th scope="row"><label for="kissmtp_username"><?php esc_html_e( 'Username', 'kissmtp' ); ?></label></th><td><input id="kissmtp_username" class="regular-text" name="kissmtp[username]" value="<?php echo esc_attr( $settings['username'] ); ?>" autocomplete="username"></td></tr>
                    <tr><th scope="row"><label for="kissmtp_password"><?php esc_html_e( 'Password', 'kissmtp' ); ?></label></th><td><?php if ( defined( 'KISSMTP_PASSWORD' ) ) : ?><p><?php esc_html_e( 'Password is supplied by the KISSMTP_PASSWORD constant.', 'kissmtp' ); ?></p><?php else : ?><input id="kissmtp_password" class="regular-text" type="password" name="kissmtp[password]" autocomplete="new-password"><p class="description"><?php echo esc_html( $this->credentials->has_password( $settings ) ? __( 'Leave blank to keep the saved password.', 'kissmtp' ) : __( 'Stored using authenticated encryption when OpenSSL is available.', 'kissmtp' ) ); ?></p><label><input type="checkbox" name="kissmtp[clear_password]" value="1"> <?php esc_html_e( 'Remove the saved password', 'kissmtp' ); ?></label><?php endif; ?></td></tr>
                    <tr><th scope="row"><label for="kissmtp_from_email"><?php esc_html_e( 'From email', 'kissmtp' ); ?></label></th><td><input id="kissmtp_from_email" class="regular-text" type="email" name="kissmtp[from_email]" value="<?php echo esc_attr( $settings['from_email'] ); ?>"><p class="description"><?php esc_html_e( 'Optional. Overrides the WordPress sender address.', 'kissmtp' ); ?></p></td></tr>
                    <tr><th scope="row"><label for="kissmtp_from_name"><?php esc_html_e( 'From name', 'kissmtp' ); ?></label></th><td><input id="kissmtp_from_name" class="regular-text" name="kissmtp[from_name]" value="<?php echo esc_attr( $settings['from_name'] ); ?>"></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'Return path', 'kissmtp' ); ?></th><td><label><input type="checkbox" name="kissmtp[return_path]" value="1" <?php checked( $settings['return_path'] ); ?>> <?php esc_html_e( 'Use the From email as the SMTP envelope sender', 'kissmtp' ); ?></label></td></tr>
                    <tr><th scope="row"><label for="kissmtp_timeout"><?php esc_html_e( 'Timeout', 'kissmtp' ); ?></label></th><td><input id="kissmtp_timeout" class="small-text" type="number" min="1" max="60" name="kissmtp[timeout]" value="<?php echo esc_attr( $settings['timeout'] ); ?>"> <?php esc_html_e( 'seconds', 'kissmtp' ); ?></td></tr>
                </table>
                <?php submit_button( __( 'Save Settings', 'kissmtp' ) ); ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Test Email', 'kissmtp' ); ?></h2>
            <p><?php esc_html_e( 'Save an enabled configuration before testing. A successful result means WordPress submitted the message; it does not guarantee inbox delivery.', 'kissmtp' ); ?></p>
            <div class="kissmtp-test-form"><label for="kissmtp_test_recipient"><?php esc_html_e( 'Recipient', 'kissmtp' ); ?></label><input id="kissmtp_test_recipient" class="regular-text" type="email"><button id="kissmtp_send_test" class="button" type="button"><?php esc_html_e( 'Send Test Email', 'kissmtp' ); ?></button><span class="spinner"></span></div>
            <p id="kissmtp_test_result" class="kissmtp-test-result" aria-live="polite"></p>
        </div>
        <?php
    }

    public function enqueue_assets( $hook ) {
        if ( 'settings_page_kissmtp' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'kissmtp-admin', KISSMTP_URL . 'assets/css/admin.css', array(), KISSMTP_VERSION );
        wp_enqueue_script( 'kissmtp-admin', KISSMTP_URL . 'assets/js/admin.js', array( 'jquery' ), KISSMTP_VERSION, true );
        wp_localize_script( 'kissmtp-admin', 'kissmtpAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'kissmtp_test_email' ),
            'sending' => __( 'Sending...', 'kissmtp' ),
            'error'   => __( 'The test email could not be sent.', 'kissmtp' ),
        ) );
    }

    private function authorize( $action ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'kissmtp' ), 403 );
        }

        check_admin_referer( $action );
    }

    private function redirect( $query = array() ) {
        $url = add_query_arg( $query, admin_url( 'options-general.php?page=kissmtp' ) );
        wp_safe_redirect( $url );
        exit;
    }
}
