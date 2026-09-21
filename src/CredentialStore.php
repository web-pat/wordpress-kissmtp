<?php

namespace KissMtp;

final class CredentialStore {
    public function has_password( $settings ) {
        return ( defined( 'KISSMTP_PASSWORD' ) && is_scalar( KISSMTP_PASSWORD ) && '' !== (string) KISSMTP_PASSWORD ) || ! empty( $settings['password'] );
    }

    public function encrypt( $password ) {
        if ( ! $this->is_available() ) {
            return new \WP_Error( 'encryption_unavailable', __( 'OpenSSL with AES-256-GCM is required to store an SMTP password. Define KISSMTP_PASSWORD in wp-config.php instead.', 'kissmtp' ) );
        }

        try {
            $nonce = random_bytes( 12 );
        } catch ( \Exception $exception ) {
            return new \WP_Error( 'randomness_unavailable', __( 'A secure random number generator is required to store an SMTP password.', 'kissmtp' ) );
        }

        $tag        = '';
        $ciphertext = openssl_encrypt( $password, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag );
        if ( false === $ciphertext || '' === $tag ) {
            return new \WP_Error( 'encryption_failed', __( 'The SMTP password could not be encrypted.', 'kissmtp' ) );
        }

        return 'v1:' . base64_encode( $nonce . $tag . $ciphertext );
    }

    public function decrypt( $encoded ) {
        if ( defined( 'KISSMTP_PASSWORD' ) ) {
            return is_scalar( KISSMTP_PASSWORD ) ? (string) KISSMTP_PASSWORD : new \WP_Error( 'password_invalid', __( 'KISSMTP_PASSWORD must be a string.', 'kissmtp' ) );
        }

        if ( '' === $encoded || ! $this->is_available() || strpos( $encoded, 'v1:' ) !== 0 ) {
            return new \WP_Error( 'password_unavailable', __( 'The stored SMTP password is unavailable. Enter it again in KISSMTP settings.', 'kissmtp' ) );
        }

        $payload = base64_decode( substr( $encoded, 3 ), true );
        if ( false === $payload || strlen( $payload ) < 29 ) {
            return new \WP_Error( 'password_invalid', __( 'The stored SMTP password is invalid. Enter it again in KISSMTP settings.', 'kissmtp' ) );
        }

        $nonce      = substr( $payload, 0, 12 );
        $tag        = substr( $payload, 12, 16 );
        $ciphertext = substr( $payload, 28 );
        $password   = openssl_decrypt( $ciphertext, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag );

        if ( false === $password ) {
            return new \WP_Error( 'password_decrypt_failed', __( 'The stored SMTP password could not be decrypted. Enter it again in KISSMTP settings.', 'kissmtp' ) );
        }

        return $password;
    }

    public function is_available() {
        return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' ) && function_exists( 'openssl_get_cipher_methods' ) && in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true );
    }

    private function key() {
        $secret = defined( 'KISSMTP_ENCRYPTION_KEY' ) && is_scalar( KISSMTP_ENCRYPTION_KEY ) ? (string) KISSMTP_ENCRYPTION_KEY : wp_salt( 'auth' );
        return hash( 'sha256', 'kissmtp:v1:' . $secret, true );
    }
}
