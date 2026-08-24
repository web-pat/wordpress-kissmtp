<?php
if (!defined('ABSPATH')) {
    exit;
}

class KISSMTP {
    private $options = [];
    private $loaded_options = null;

    public function init() {
        $this->options = $this->get_options();

        add_action('phpmailer_init', [$this, 'configure_phpmailer'], 10);
        add_action('wp_mail_failed', [$this, 'handle_mail_failed'], 10, 1);

        if (!empty($this->options['from_email'])) {
            add_filter('wp_mail_from', [$this, 'force_from_email'], PHP_INT_MAX);
        }

        if (!empty($this->options['from_name'])) {
            add_filter('wp_mail_from_name', [$this, 'force_from_name'], PHP_INT_MAX);
        }
    }

    public function get_options() {
        if ($this->loaded_options !== null) {
            return $this->loaded_options;
        }

        $defaults = [
            'host'           => '',
            'port'           => 587,
            'encryption'     => 'tls',
            'auth'           => true,
            'username'       => '',
            'password'       => '',
            'from_email'     => '',
            'from_name'      => '',
            'return_path'    => true,
        ];

        $options = get_option('kissmtp_options', []);
        $this->loaded_options = wp_parse_args($options, $defaults);
        return $this->loaded_options;
    }

    public function is_configured() {
        return !empty($this->options['host']) && !empty($this->options['username']);
    }

    public function configure_phpmailer($phpmailer) {
        if (!$this->is_configured()) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $this->options['host'];
        $phpmailer->Port       = (int) $this->options['port'];
        $phpmailer->SMTPAuth   = (bool) $this->options['auth'];

        if ($this->options['auth']) {
            $phpmailer->Username = $this->options['username'];
            $phpmailer->Password = $this->decrypt_password($this->options['password']);
        }

        switch ($this->options['encryption']) {
            case 'ssl':
                $phpmailer->SMTPSecure = 'ssl';
                break;
            case 'tls':
                $phpmailer->SMTPSecure = 'tls';
                break;
            default:
                $phpmailer->SMTPSecure = '';
                $phpmailer->SMTPAutoTLS = false;
                break;
        }

        if ((bool) $this->options['return_path']) {
            $phpmailer->Sender = $this->options['from_email'] ?: $phpmailer->From;
        }

        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $phpmailer->SMTPDebug = 2;
            $phpmailer->Debugoutput = function($str, $level) {
                error_log('[KISSMTP SMTP] ' . trim($str));
            };
        }
    }

    public function force_from_email($email) {
        return $this->options['from_email'] ?: $email;
    }

    public function force_from_name($name) {
        return $this->options['from_name'] ?: $name;
    }

    public function handle_mail_failed($wp_error) {
        $message = sprintf(
            __('KISSMTP: Mail failed — %s', 'kissmtp'),
            $wp_error->get_error_message()
        );

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[KISSMTP] ' . $message);
        }

        set_transient('kissmtp_last_error', $message, 5 * MINUTE_IN_SECONDS);
    }

    public function encrypt_password($password) {
        if (empty($password)) {
            return '';
        }
        $key = substr(KISSMTP_ENCRYPTION_KEY, 0, 32);
        $iv  = random_bytes(16);
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
        if ($encrypted === false) {
            return '';
        }
        return base64_encode($iv . '::' . $encrypted);
    }

    public function decrypt_password($encoded) {
        if (empty($encoded)) {
            return '';
        }
        $key = substr(KISSMTP_ENCRYPTION_KEY, 0, 32);
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || strpos($decoded, '::') === false) {
            return $encoded;
        }

        list($iv, $encrypted) = explode('::', $decoded, 2);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

        return $decrypted !== false ? $decrypted : $encoded;
    }

    public function test_connection($recipient_email) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', __('SMTP not configured', 'kissmtp'));
        }

        $to      = sanitize_email($recipient_email);
        $subject = __('KISSMTP Test Email', 'kissmtp');
        $message = __('This is a test email sent via KISSMTP.', 'kissmtp');
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        $result = wp_mail($to, $subject, $message, $headers);

        if (!$result) {
            $error = get_transient('kissmtp_last_error');
            return new WP_Error('send_failed', $error ?: __('Failed to send test email', 'kissmtp'));
        }

        return true;
    }
}