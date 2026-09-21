<?php

namespace KissMtp;

final class SettingsRepository {
    const OPTION = 'kissmtp_settings';

    public function get() {
        return Settings::normalize( get_option( self::OPTION, array() ) );
    }

    public function save( $settings ) {
        return update_option( self::OPTION, Settings::normalize( $settings ), false );
    }

    public function migrate_legacy() {
        if ( false !== get_option( self::OPTION, false ) ) {
            return;
        }

        $legacy = get_option( 'kissmtp_options', array() );
        if ( ! is_array( $legacy ) ) {
            add_option( self::OPTION, Settings::defaults(), '', false );
            return;
        }

        $settings = Settings::normalize( $legacy );
        // Legacy CBC ciphertext is intentionally not reused as an SMTP password.
        $settings['enabled']  = false;
        $settings['password'] = '';
        add_option( self::OPTION, $settings, '', false );
    }
}
