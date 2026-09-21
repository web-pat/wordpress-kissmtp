<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'kissmtp_settings' );
delete_option( 'kissmtp_options' );
delete_transient( 'kissmtp_test_error' );
