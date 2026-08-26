<?php
if (!defined('ABSPATH')) {
    exit;
}

class KISSMTP_Activator {
    public static function activate() {
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

        add_option('kissmtp_options', $defaults, '', 'no');
    }

    public static function deactivate() {
    }
}