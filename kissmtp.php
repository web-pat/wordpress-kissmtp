<?php
/**
 * Plugin Name:       KISSMTP
 * Plugin URI:        https://github.com/web-pat/wordpress-kissmtp
 * Description:       Configures WordPress mail delivery through a standards-compliant SMTP server.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            web-pat
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       kissmtp
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KISSMTP_VERSION', '2.0.0' );
define( 'KISSMTP_FILE', __FILE__ );
define( 'KISSMTP_DIR', plugin_dir_path( __FILE__ ) );
define( 'KISSMTP_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
    static function ( $class ) {
        $prefix = 'KissMtp\\';

        if ( strpos( $class, $prefix ) !== 0 ) {
            return;
        }

        $relative = substr( $class, strlen( $prefix ) );
        $file     = KISSMTP_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

        if ( is_readable( $file ) ) {
            require_once $file;
        }
    }
);

register_activation_hook( __FILE__, array( 'KissMtp\\Plugin', 'activate' ) );

add_action(
    'plugins_loaded',
    static function () {
        KissMtp\Plugin::instance()->boot();
    }
);
