<?php

namespace KissMtp;

final class MailerConfigurator {
    private $repository;
    private $credentials;
    private $mailer;
    private $original = array();
    private $applied = array();

    public function __construct( SettingsRepository $repository, CredentialStore $credentials ) {
        $this->repository  = $repository;
        $this->credentials = $credentials;
    }

    public function register() {
        add_filter( 'pre_wp_mail', array( $this, 'guard_delivery' ), 20, 2 );
        add_action( 'phpmailer_init', array( $this, 'configure' ), 20 );
        add_action( 'wp_mail_succeeded', array( $this, 'restore' ), PHP_INT_MAX, 0 );
        add_action( 'wp_mail_failed', array( $this, 'restore' ), PHP_INT_MAX, 0 );
        add_filter( 'wp_mail_from', array( $this, 'from_email' ), 20 );
        add_filter( 'wp_mail_from_name', array( $this, 'from_name' ), 20 );
    }

    public function guard_delivery( $result, $atts ) {
        // Also clean up after a previous send that exited before WordPress's result hooks.
        $this->restore();
        if ( null !== $result ) {
            return $result;
        }

        $settings = $this->repository->get();
        $error    = $this->configuration_error( $settings );
        if ( ! $settings['enabled'] || ! is_wp_error( $error ) ) {
            return null;
        }

        do_action( 'wp_mail_failed', new \WP_Error( 'kissmtp_configuration', $error->get_error_message(), $atts ) );
        return false;
    }

    private function configuration_error( $settings ) {
        $errors = Settings::validate( $settings, $this->credentials->has_password( $settings ) );
        if ( $errors ) {
            return new \WP_Error( 'kissmtp_configuration', implode( ' ', $errors ) );
        }

        return $settings['enabled'] && $settings['auth'] ? $this->credentials->decrypt( $settings['password'] ) : '';
    }

    public function configure( $phpmailer ) {
        $this->restore();
        $settings = $this->repository->get();
        if ( ! Settings::is_usable( $settings, $this->credentials->has_password( $settings ) ) ) {
            return;
        }

        $password = $settings['auth'] ? $this->credentials->decrypt( $settings['password'] ) : '';
        if ( is_wp_error( $password ) ) {
            return;
        }

        // PHPMailer reuses connected sessions even when Host or credentials change.
        $phpmailer->smtpClose();
        $this->mailer  = $phpmailer;
        $this->applied = array(
            'Mailer'        => 'smtp',
            'Host'          => $settings['host'],
            'Port'          => $settings['port'],
            'Timeout'       => $settings['timeout'],
            'SMTPAuth'      => $settings['auth'],
            'SMTPDebug'     => 0,
            'SMTPAutoTLS'   => 'none' !== $settings['encryption'],
            'SMTPSecure'    => 'none' === $settings['encryption'] ? '' : $settings['encryption'],
            'SMTPKeepAlive' => false,
            'AuthType'      => '',
            'Username'      => $settings['auth'] ? $settings['username'] : '',
            'Password'      => $password,
        );

        if ( $settings['return_path'] && is_email( $settings['from_email'] ) ) {
            $this->applied['Sender'] = $settings['from_email'];
        }

        foreach ( $this->applied as $property => $value ) {
            $this->original[ $property ] = $phpmailer->$property;
            $phpmailer->$property = $value;
        }
    }

    public function is_configured( $phpmailer ) {
        if ( ! $this->mailer || $this->mailer !== $phpmailer ) {
            return false;
        }

        foreach ( $this->applied as $property => $value ) {
            if ( $phpmailer->$property !== $value ) {
                return false;
            }
        }

        return true;
    }

    public function restore() {
        if ( ! $this->mailer ) {
            return;
        }

        $this->mailer->smtpClose();
        foreach ( $this->applied as $property => $value ) {
            // Do not overwrite changes made by a later mail plugin.
            if ( $this->mailer->$property === $value ) {
                $this->mailer->$property = $this->original[ $property ];
            }
        }

        $this->mailer   = null;
        $this->original = array();
        $this->applied  = array();
    }

    public function from_email( $email ) {
        $settings = $this->repository->get();
        return $settings['enabled'] && ! is_wp_error( $this->configuration_error( $settings ) ) && is_email( $settings['from_email'] ) ? $settings['from_email'] : $email;
    }

    public function from_name( $name ) {
        $settings = $this->repository->get();
        return $settings['enabled'] && ! is_wp_error( $this->configuration_error( $settings ) ) && '' !== $settings['from_name'] ? $settings['from_name'] : $name;
    }
}
