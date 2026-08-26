<?php
if (!defined('ABSPATH')) {
    exit;
}

class KISSMTP_Admin {
    private static $instance = null;
    private $kissmtp;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        if ($this->kissmtp === null) {
            global $kissmtp_instance;
            $this->kissmtp = $kissmtp_instance ?? new KISSMTP();
        }

        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_settings_page() {
        add_options_page(
            __('KISSMTP Settings', 'kissmtp'),
            __('KISSMTP', 'kissmtp'),
            'manage_options',
            'kissmtp',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('kissmtp_options_group', 'kissmtp_options', [$this, 'sanitize_options']);

        add_settings_section(
            'kissmtp_smtp_section',
            __('SMTP Configuration', 'kissmtp'),
            [$this, 'render_smtp_section'],
            'kissmtp'
        );

        add_settings_field('kissmtp_host', __('SMTP Host', 'kissmtp'), [$this, 'field_host'], 'kissmtp', 'kissmtp_smtp_section');
        add_settings_field('kissmtp_port', __('SMTP Port', 'kissmtp'), [$this, 'field_port'], 'kissmtp', 'kissmtp_smtp_section');
        add_settings_field('kissmtp_encryption', __('Encryption', 'kissmtp'), [$this, 'field_encryption'], 'kissmtp', 'kissmtp_smtp_section');
        add_settings_field('kissmtp_auth', __('Authentication', 'kissmtp'), [$this, 'field_auth'], 'kissmtp', 'kissmtp_smtp_section');
        add_settings_field('kissmtp_username', __('Username', 'kissmtp'), [$this, 'field_username'], 'kissmtp', 'kissmtp_smtp_section');
        add_settings_field('kissmtp_password', __('Password', 'kissmtp'), [$this, 'field_password'], 'kissmtp', 'kissmtp_smtp_section');

        add_settings_section(
            'kissmtp_from_section',
            __('From Address', 'kissmtp'),
            [$this, 'render_from_section'],
            'kissmtp'
        );

        add_settings_field('kissmtp_from_email', __('From Email', 'kissmtp'), [$this, 'field_from_email'], 'kissmtp', 'kissmtp_from_section');
        add_settings_field('kissmtp_from_name', __('From Name', 'kissmtp'), [$this, 'field_from_name'], 'kissmtp', 'kissmtp_from_section');
        add_settings_field('kissmtp_return_path', __('Return Path', 'kissmtp'), [$this, 'field_return_path'], 'kissmtp', 'kissmtp_from_section');
    }

    public function sanitize_options($input) {
        $sanitized = [];
        $sanitized['host']        = sanitize_text_field($input['host'] ?? '');
        $sanitized['port']        = max(1, min(65535, absint($input['port'] ?? 587)));
        $sanitized['encryption']  = in_array($input['encryption'] ?? 'tls', ['none', 'ssl', 'tls'], true) ? $input['encryption'] : 'tls';
        $sanitized['auth']        = !empty($input['auth']);
        $sanitized['username']    = sanitize_email($input['username'] ?? '');
        $options = get_option('kissmtp_options');
        $sanitized['password'] = !empty($input['password']) ? $this->kissmtp->encrypt_password($input['password']) : ($options ? $options['password'] : '');
        $sanitized['from_email']  = sanitize_email($input['from_email'] ?? '');
        $sanitized['from_name']   = sanitize_text_field($input['from_name'] ?? '');
        $sanitized['return_path'] = !empty($input['return_path']);

        return $sanitized;
    }

    public function render_settings_page() {
        $last_error = get_transient('kissmtp_last_error');
        ?>
        <div class="wrap kissmtp-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if ($last_error): ?>
                <div class="notice notice-error kissmtp-error" style="display:block;">
                    <p><?php echo esc_html($last_error); ?></p>
                    <button type="button" class="notice-dismiss" aria-label="<?php esc_attr_e('Dismiss this notice', 'kissmtp'); ?>"><span class="screen-reader-text"><?php esc_html_e('Dismiss', 'kissmtp'); ?></span></button>
                </div>
                <?php delete_transient('kissmtp_last_error'); ?>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('kissmtp_options_group'); ?>
                <?php do_settings_sections('kissmtp'); ?>
                <?php submit_button(__('Save Settings', 'kissmtp'), 'primary', 'kissmtp_submit'); ?>
            </form>

            <hr style="margin-top: 40px;">

            <h2><?php esc_html_e('Test Email', 'kissmtp'); ?></h2>
            <div id="kissmtp-test-email">
                <p class="description"><?php esc_html_e('Send a test email to verify your SMTP configuration.', 'kissmtp'); ?></p>
                <div class="kissmtp-test-form">
                    <label for="kissmtp_test_recipient"><?php esc_html_e('Recipient Email', 'kissmtp'); ?></label>
                    <input type="email" id="kissmtp_test_recipient" class="regular-text" placeholder="test@example.com" required>
                    <button type="button" id="kissmtp_send_test" class="button button-secondary" disabled><?php esc_html_e('Send Test Email', 'kissmtp'); ?></button>
                    <span class="spinner"></span>
                </div>
                <div id="kissmtp_test_result" class="kissmtp-test-result" style="display:none;"></div>
            </div>
        </div>
        <?php
    }

    public function render_smtp_section() {
        echo '<p class="description">' . esc_html__('Configure your SMTP server settings below. Contact your email provider for the correct values.', 'kissmtp') . '</p>';
    }

    public function field_host() {
        $options = $this->kissmtp->get_options();
        printf(
            '<input type="text" name="kissmtp_options[host]" value="%s" class="regular-text" placeholder="smtp.example.com" required>',
            esc_attr($options['host'])
        );
        echo ' <p class="description">' . esc_html__('Hostname of your SMTP server (e.g., smtp.gmail.com)', 'kissmtp') . '</p>';
    }

    public function field_port() {
        $options = $this->kissmtp->get_options();
        printf(
            '<input type="number" name="kissmtp_options[port]" value="%d" class="small-text" min="1" max="65535" required>',
            esc_attr($options['port'])
        );
        echo ' <p class="description">' . esc_html__('Common ports: 25 (no encryption), 465 (SSL), 587 (TLS), 2525 (TLS alternative)', 'kissmtp') . '</p>';
    }

    public function field_encryption() {
        $options = $this->kissmtp->get_options();
        $encryption = $options['encryption'];
        ?>
        <select name="kissmtp_options[encryption]">
            <option value="none" <?php selected($encryption, 'none'); ?>><?php esc_html_e('None (Plain text)', 'kissmtp'); ?></option>
            <option value="ssl" <?php selected($encryption, 'ssl'); ?>><?php esc_html_e('SSL (Implicit TLS on port 465)', 'kissmtp'); ?></option>
            <option value="tls" <?php selected($encryption, 'tls'); ?>><?php esc_html_e('TLS (STARTTLS on port 587/25)', 'kissmtp'); ?></option>
        </select>
        <p class="description">
            <?php esc_html_e('SSL: Encryption from the start (port 465). TLS: Upgrade plain connection to encrypted (port 587/25).', 'kissmtp'); ?>
        </p>
        <?php
    }

    public function field_auth() {
        $options = $this->kissmtp->get_options();
        printf(
            '<label><input type="checkbox" name="kissmtp_options[auth]" value="1" %s> %s</label>',
            checked($options['auth'], true, false),
            esc_html__('Require authentication (username/password)', 'kissmtp')
        );
    }

    public function field_username() {
        $options = $this->kissmtp->get_options();
        printf(
            '<input type="text" name="kissmtp_options[username]" value="%s" class="regular-text" placeholder="user@example.com">',
            esc_attr($options['username'])
        );
        echo ' <p class="description">' . esc_html__('SMTP username (usually your full email address)', 'kissmtp') . '</p>';
    }

    public function field_password() {
        $options = $this->kissmtp->get_options();
        $has_password = !empty($options['password']);
        ?>
        <div class="kissmtp-password-wrapper">
            <input type="password" name="kissmtp_options[password]" id="kissmtp_password" class="regular-text" placeholder="<?php echo $has_password ? esc_attr__('Leave blank to keep current', 'kissmtp') : esc_attr__('Enter SMTP password', 'kissmtp'); ?>" autocomplete="new-password">
            <button type="button" class="button kissmtp-toggle-password" aria-label="<?php esc_attr_e('Show/Hide password', 'kissmtp'); ?>" data-target="kissmtp_password">
                <span class="dashicons dashicons-hidden"></span>
            </button>
        </div>
        <p class="description">
            <?php
            if ($has_password) {
                esc_html_e('Leave blank to keep the current password.', 'kissmtp');
            } else {
                esc_html_e('Your SMTP password or app-specific password.', 'kissmtp');
            }
            ?>
        </p>
        <?php
    }

    public function render_from_section() {
        echo '<p class="description">' . esc_html__('These values will override the default WordPress "From" address on all outgoing emails.', 'kissmtp') . '</p>';
    }

    public function field_from_email() {
        $options = $this->kissmtp->get_options();
        printf(
            '<input type="email" name="kissmtp_options[from_email]" value="%s" class="regular-text" placeholder="wordpress@example.com">',
            esc_attr($options['from_email'])
        );
        echo ' <p class="description">' . esc_html__('Forces this email as the sender on all outgoing mail.', 'kissmtp') . '</p>';
    }

    public function field_from_name() {
        $options = $this->kissmtp->get_options();
        printf(
            '<input type="text" name="kissmtp_options[from_name]" value="%s" class="regular-text" placeholder="%s">',
            esc_attr($options['from_name']),
            esc_attr(get_bloginfo('name'))
        );
        echo ' <p class="description">' . esc_html__('Forces this name as the sender on all outgoing mail.', 'kissmtp') . '</p>';
    }

    public function field_return_path() {
        $options = $this->kissmtp->get_options();
        printf(
            '<label><input type="checkbox" name="kissmtp_options[return_path]" value="1" %s> %s</label>',
            checked($options['return_path'], true, false),
            esc_html__('Set Return-Path header to From Email (recommended for deliverability)', 'kissmtp')
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'settings_page_kissmtp') {
            return;
        }

        wp_enqueue_style('kissmtp-admin', KISSMTP_PLUGIN_URL . 'assets/css/admin.css', [], KISSMTP_VERSION);
        wp_enqueue_script('kissmtp-admin', KISSMTP_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], KISSMTP_VERSION, true);

        wp_localize_script('kissmtp-admin', 'kissmtp_ajax', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('kissmtp_test_email'),
            'sending'       => __('Sending...', 'kissmtp'),
            'success'       => __('Test email sent successfully!', 'kissmtp'),
            'error_send'    => __('Failed to send test email.', 'kissmtp'),
            'error_valid'   => __('Please enter a valid email address.', 'kissmtp'),
        ]);
    }
}