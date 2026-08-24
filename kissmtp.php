<?php
/**
 * Plugin Name:       KISSMTP
 * Plugin URI:        https://github.com/web-pat/wordpress-kissmtp
 * Description:       Simple SMTP configuration for WordPress. Forces all outbound emails through SMTP with support for SSL/TLS/No encryption.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            web-pat
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       kissmtp
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KISSMTP_VERSION', '1.0.0');
define('KISSMTP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KISSMTP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KISSMTP_ENCRYPTION_KEY', wp_hash('kissmtp-smtp-credentials', 'auth'));

require_once KISSMTP_PLUGIN_DIR . 'includes/class-activator.php';
require_once KISSMTP_PLUGIN_DIR . 'includes/class-kissmtp.php';
require_once KISSMTP_PLUGIN_DIR . 'includes/class-admin.php';
require_once KISSMTP_PLUGIN_DIR . 'includes/class-ajax.php';

function kissmtp_init() {
    global $kissmtp_instance;
    $kissmtp_instance = new KISSMTP();
    $kissmtp_instance->init();

    if (is_admin()) {
        $admin = KISSMTP_Admin::get_instance();
        $admin->init();

        $ajax = new KISSMTP_Ajax();
        $ajax->init();
    }
}
add_action('plugins_loaded', 'kissmtp_init');

function kissmtp_load_textdomain() {
    load_plugin_textdomain('kissmtp', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'kissmtp_load_textdomain');

register_activation_hook(__FILE__, ['KISSMTP_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['KISSMTP_Activator', 'deactivate']);