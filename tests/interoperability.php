<?php
/**
 * Run with KISSMTP_WP_ROOT=/path/to/wordpress php tests/interoperability.php.
 * Uses WordPress's real hooks and wp_mail(), with a network-free PHPMailer double.
 */

$root = getenv( 'KISSMTP_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/wp-includes/pluggable.php' ) ) {
    fwrite( STDERR, "Set KISSMTP_WP_ROOT to a WordPress source checkout.\n" );
    exit( 1 );
}
define( 'ABSPATH', rtrim( $root, '/' ) . '/' );
define( 'WPINC', 'wp-includes' );
define( 'MINUTE_IN_SECONDS', 60 );

require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/class-wp-error.php';
require ABSPATH . WPINC . '/PHPMailer/Exception.php';
require ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require ABSPATH . WPINC . '/pluggable.php';

// Minimal environment for core wp_mail() and the plugin; no database or network.
function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, $args ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function get_option( $key, $default = false ) { return isset( $GLOBALS['options'][ $key ] ) ? $GLOBALS['options'][ $key ] : $default; }
function network_home_url() { return 'https://example.org'; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function get_bloginfo( $key ) { return 'UTF-8'; }
function check_ajax_referer( $action, $key ) {}
function current_user_can( $capability ) { return true; }
function get_current_user_id() { return 1; }
function get_transient( $key ) { return false; }
function set_transient( $key, $value, $expiration ) {}
function wp_unslash( $value ) { return $value; }
function wp_send_json_error( $data, $status = null ) { throw new JsonResult( false, $data, $status ); }
function wp_send_json_success( $data ) { throw new JsonResult( true, $data, 200 ); }

class JsonResult extends Exception {
    public $success;
    public $status;
    public function __construct( $success, $data, $status ) {
        parent::__construct( $data['message'] );
        $this->success = $success;
        $this->status  = $status;
    }
}

class TestMailer extends \PHPMailer\PHPMailer\PHPMailer {
    public $messages = array();
    public $connected = true;
    public $fail = false;
    public function smtpClose() { $this->connected = false; }
    public function send() {
        if ( $this->fail ) {
            throw new \PHPMailer\PHPMailer\Exception( 'Simulated delivery failure' );
        }
        $this->messages[] = array( $this->Mailer, $this->Host, $this->Username, $this->Password, $this->Sender, $this->connected );
        return true;
    }
}

foreach ( array( 'Settings', 'SettingsRepository', 'CredentialStore', 'MailerConfigurator', 'Admin' ) as $class ) {
    require dirname( __DIR__ ) . '/src/' . $class . '.php';
}

function expect( $condition, $message ) {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
    echo "PASS: $message\n";
}

$repository = new \KissMtp\SettingsRepository();
$credentials = new \KissMtp\CredentialStore();
define( 'KISSMTP_ENCRYPTION_KEY', 'test-only-key' );
$settings = \KissMtp\Settings::defaults();
$settings = array_merge( $settings, array(
    'enabled' => true, 'host' => 'smtp.site-a.example', 'username' => 'site-a',
    'password' => $credentials->encrypt( 'test-password' ),
    'from_email' => 'site-a@example.org', 'from_name' => 'Site A',
) );
$GLOBALS['options']['kissmtp_settings'] = $settings;
$configurator = new \KissMtp\MailerConfigurator( $repository, $credentials );
$configurator->register();
$phpmailer = new TestMailer( true );
$phpmailer->Sender = 'original@example.org';
$phpmailer->Host = 'other-plugin.example';
$phpmailer->Username = 'other-user';
$phpmailer->Password = 'other-password';
$phpmailer->AuthType = 'CRAM-MD5';
$phpmailer->SMTPKeepAlive = true;
$errors = array();
add_action( 'wp_mail_failed', function ( $error ) use ( &$errors ) { $errors[] = $error; } );

expect( wp_mail( 'recipient@example.org', 'First', 'Body' ), 'Configured mail succeeds' );
expect( $phpmailer->messages[0] === array( 'smtp', 'smtp.site-a.example', 'site-a', 'test-password', 'site-a@example.org', false ), 'Existing connection is closed and site A identity is applied' );
expect( 'original@example.org' === $phpmailer->Sender && 'other-plugin.example' === $phpmailer->Host && 'other-password' === $phpmailer->Password && $phpmailer->SMTPKeepAlive && 'CRAM-MD5' === $phpmailer->AuthType, 'Original shared properties are restored after success' );
$inspect = function ( $mailer ) {
    expect( '' === $mailer->AuthType && ! $mailer->SMTPKeepAlive, 'Inherited authentication mechanism and keepalive do not affect KISSMTP' );
};
add_action( 'phpmailer_init', $inspect, 30 );
wp_mail( 'recipient@example.org', 'Authentication isolation', 'Body' );
remove_action( 'phpmailer_init', $inspect, 30 );

// Simulate switch_to_blog(): get_option() now returns another site's configuration.
$GLOBALS['options']['kissmtp_settings'] = array_merge( $settings, array(
    'host' => 'smtp.site-b.example', 'auth' => false, 'return_path' => false, 'from_email' => '', 'from_name' => '',
) );
wp_mail( 'recipient@example.org', 'Second', 'Body' );
expect( end( $phpmailer->messages ) === array( 'smtp', 'smtp.site-b.example', '', '', 'original@example.org', false ), 'Site B does not inherit site A credentials or envelope sender' );

$phpmailer->fail = true;
expect( ! wp_mail( 'recipient@example.org', 'Failure', 'Body' ) && 'other-plugin.example' === $phpmailer->Host, 'Shared state is restored after a delivery failure' );
$phpmailer->fail = false;

$GLOBALS['options']['kissmtp_settings'] = array_merge( $settings, array( 'password' => 'v1:broken' ) );
$count = count( $phpmailer->messages );
expect( ! wp_mail( 'recipient@example.org', 'Broken credentials', 'Body' ), 'Undecryptable credentials block submission' );
expect( count( $phpmailer->messages ) === $count && 'kissmtp_configuration' === end( $errors )->get_error_code(), 'Credential failure emits wp_mail_failed without invoking any transport' );
expect( 'caller@example.org' === $configurator->from_email( 'caller@example.org' ) && 'Caller' === $configurator->from_name( 'Caller' ), 'Broken configuration leaves caller sender identity alone' );
$GLOBALS['options']['kissmtp_settings']['host'] = '';
expect( ! wp_mail( 'recipient@example.org', 'Incomplete', 'Body' ), 'Enabled incomplete configuration cannot fall back to PHP mail' );

$GLOBALS['options']['kissmtp_settings']['enabled'] = false;
wp_mail( 'recipient@example.org', 'Disabled', 'Body', array( 'From: Caller <caller@example.org>' ) );
expect( 'mail' === end( $phpmailer->messages )[0] && 'caller@example.org' === $phpmailer->From, 'Disabled SMTP preserves core transport and caller sender' );

$GLOBALS['options']['kissmtp_settings'] = $settings;
$admin = new \KissMtp\Admin( $repository, $credentials, $configurator );
$_POST['recipient'] = 'recipient@example.org';
function test_result( $admin ) {
    try {
        $admin->test_email();
    } catch ( JsonResult $result ) {
        return $result;
    }
    throw new RuntimeException( 'Missing test response' );
}
expect( test_result( $admin )->success, 'Test panel verifies successful KISSMTP delivery before cleanup' );
$intercept = function ( $result ) { return true; };
add_filter( 'pre_wp_mail', $intercept, 10 );
$result = test_result( $admin );
expect( ! $result->success && 409 === $result->status, 'Test panel does not validate an intercepted wp_mail call' );
remove_filter( 'pre_wp_mail', $intercept, 10 );
$nested_intercept = function ( $result, $atts ) {
    if ( 'KISSMTP Test Email' === $atts['subject'] ) {
        wp_mail( 'logger@example.org', 'Unrelated message', 'Body' );
        return true;
    }
    return $result;
};
add_filter( 'pre_wp_mail', $nested_intercept, 10, 2 );
$result = test_result( $admin );
expect( ! $result->success && 409 === $result->status, 'Unrelated recursive email cannot falsely validate an intercepted test' );
remove_filter( 'pre_wp_mail', $nested_intercept, 10 );
$override = function ( $mailer ) { $mailer->Host = 'replacement.example'; $mailer->Sender = 'replacement@example.org'; };
add_action( 'phpmailer_init', $override, 30 );
$result = test_result( $admin );
expect( ! $result->success && 409 === $result->status, 'Test panel detects a later transport override' );
expect( 'replacement@example.org' === $phpmailer->Sender && 'replacement.example' === $phpmailer->Host, 'Cleanup preserves later plugins changes' );
remove_action( 'phpmailer_init', $override, 30 );

// A send interrupted before result hooks must also be cleaned up on the next call.
$configurator->configure( $phpmailer );
$GLOBALS['options']['kissmtp_settings']['enabled'] = false;
$configurator->guard_delivery( null, array() );
expect( 'replacement@example.org' === $phpmailer->Sender, 'Next-call cleanup restores state after an interrupted send' );

echo "All interoperability checks passed.\n";
