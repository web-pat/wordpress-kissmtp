<?php

namespace KissMtp;

final class Plugin {
    private static $instance;
    private $repository;
    private $credentials;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate() {
        $repository = new SettingsRepository();
        $repository->migrate_legacy();
    }

    public function boot() {
        $this->repository  = new SettingsRepository();
        $this->credentials = new CredentialStore();
        $this->repository->migrate_legacy();

        $mailer = new MailerConfigurator( $this->repository, $this->credentials );
        $mailer->register();

        if ( is_admin() ) {
            $admin = new Admin( $this->repository, $this->credentials );
            $admin->register();
        }
    }

    public function repository() {
        return $this->repository;
    }

    public function credentials() {
        return $this->credentials;
    }
}
