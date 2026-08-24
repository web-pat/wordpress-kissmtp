<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('kissmtp_options');
delete_transient('kissmtp_last_error');