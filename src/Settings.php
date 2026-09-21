<?php

namespace KissMtp;

final class Settings {
    public static function defaults() {
        return array(
            'enabled'     => false,
            'host'        => '',
            'port'        => 587,
            'encryption'  => 'tls',
            'auth'        => true,
            'username'    => '',
            'password'    => '',
            'from_email'  => '',
            'from_name'   => '',
            'return_path' => true,
            'timeout'     => 15,
        );
    }

    public static function normalize( $settings ) {
        $settings = is_array( $settings ) ? $settings : array();
        $settings = wp_parse_args( $settings, self::defaults() );

        $settings['enabled']     = (bool) $settings['enabled'];
        $settings['host']        = self::sanitize_host( $settings['host'] );
        $settings['port']        = max( 1, min( 65535, absint( self::string( $settings['port'] ) ) ) );
        $settings['encryption']  = in_array( $settings['encryption'], array( 'none', 'ssl', 'tls' ), true ) ? $settings['encryption'] : 'tls';
        $settings['auth']        = (bool) $settings['auth'];
        $settings['username']    = sanitize_text_field( self::string( $settings['username'] ) );
        $settings['password']    = self::string( $settings['password'] );
        $settings['from_email']  = sanitize_email( self::string( $settings['from_email'] ) );
        $settings['from_name']   = sanitize_text_field( self::string( $settings['from_name'] ) );
        $settings['return_path'] = (bool) $settings['return_path'];
        $settings['timeout']     = max( 1, min( 60, absint( self::string( $settings['timeout'] ) ) ) );

        return $settings;
    }

    public static function validate( $settings, $has_password ) {
        $errors = array();

        if ( ! $settings['enabled'] ) {
            return $errors;
        }

        if ( '' === $settings['host'] ) {
            $errors[] = __( 'An SMTP host is required when SMTP is enabled.', 'kissmtp' );
        }

        if ( $settings['auth'] && '' === $settings['username'] ) {
            $errors[] = __( 'An SMTP username is required when authentication is enabled.', 'kissmtp' );
        }

        if ( $settings['auth'] && ! $has_password ) {
            $errors[] = __( 'An SMTP password is required when authentication is enabled.', 'kissmtp' );
        }

        if ( '' !== $settings['from_email'] && ! is_email( $settings['from_email'] ) ) {
            $errors[] = __( 'The From email address is invalid.', 'kissmtp' );
        }

        return $errors;
    }

    public static function is_usable( $settings, $has_password ) {
        return $settings['enabled'] && '' !== $settings['host'] && ( ! $settings['auth'] || ( '' !== $settings['username'] && $has_password ) );
    }

    private static function sanitize_host( $host ) {
        $host = trim( sanitize_text_field( self::string( $host ) ) );

        if ( strlen( $host ) > 253 || preg_match( '/[\s\\/:@]/', $host ) ) {
            return '';
        }

        return $host;
    }

    private static function string( $value ) {
        return is_scalar( $value ) ? (string) $value : '';
    }
}
