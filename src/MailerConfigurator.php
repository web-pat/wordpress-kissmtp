<?php

namespace KissMtp;

final class MailerConfigurator {
    private $repository;
    private $credentials;

    public function __construct( SettingsRepository $repository, CredentialStore $credentials ) {
        $this->repository  = $repository;
        $this->credentials = $credentials;
    }

    public function register() {
        add_action( 'phpmailer_init', array( $this, 'configure' ), 20 );
        add_filter( 'wp_mail_from', array( $this, 'from_email' ), 20 );
        add_filter( 'wp_mail_from_name', array( $this, 'from_name' ), 20 );
    }

    public function configure( $phpmailer ) {
        $settings = $this->repository->get();
        if ( ! Settings::is_usable( $settings, $this->credentials->has_password( $settings ) ) ) {
            return;
        }

        $password = $settings['auth'] ? $this->credentials->decrypt( $settings['password'] ) : '';
        if ( is_wp_error( $password ) ) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $settings['host'];
        $phpmailer->Port       = $settings['port'];
        $phpmailer->Timeout    = $settings['timeout'];
        $phpmailer->SMTPAuth   = $settings['auth'];
        $phpmailer->SMTPDebug  = 0;
        $phpmailer->SMTPAutoTLS = 'none' !== $settings['encryption'];
        $phpmailer->SMTPSecure = 'none' === $settings['encryption'] ? '' : $settings['encryption'];

        if ( $settings['auth'] ) {
            $phpmailer->Username = $settings['username'];
            $phpmailer->Password = $password;
        }

        if ( $settings['return_path'] && is_email( $settings['from_email'] ) ) {
            $phpmailer->Sender = $settings['from_email'];
        }
    }

    public function from_email( $email ) {
        $settings = $this->repository->get();
        return $settings['enabled'] && is_email( $settings['from_email'] ) ? $settings['from_email'] : $email;
    }

    public function from_name( $name ) {
        $settings = $this->repository->get();
        return $settings['enabled'] && '' !== $settings['from_name'] ? $settings['from_name'] : $name;
    }
}
