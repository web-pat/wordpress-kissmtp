# KISSMTP

KISSMTP configures WordPress' bundled PHPMailer instance to send mail through an SMTP server. It is deliberately limited to SMTP transport configuration, sender overrides, and an administrator-only test email.

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later
- OpenSSL with `aes-256-gcm` when an SMTP password is stored in the WordPress database

The plugin loads no mail configuration until `plugins_loaded`; it is safe to activate on a site without OpenSSL. An administrator can instead define the password outside the database:

```php
define( 'KISSMTP_PASSWORD', 'your-smtp-password' );
```

Add the constant to `wp-config.php`, before WordPress is loaded. A defined constant always takes precedence over a stored password.

Optionally, define `KISSMTP_ENCRYPTION_KEY` to provide the database-encryption key from deployment configuration. Otherwise the plugin derives its runtime key from the WordPress authentication salt. Changing authentication salts means database-stored passwords must be entered again.

## Usage

1. Activate the plugin.
2. Open **Settings > KISSMTP**.
3. Enter the SMTP host, port, encryption mode, authentication settings, and optional sender details.
4. Save the settings, then enable SMTP.
5. Use the test-email panel to submit a message. A successful result confirms WordPress handed the message to its configured mail transport, not that the recipient accepted or delivered it.

No mail behavior changes until **Enable SMTP** is selected. Disabling SMTP leaves WordPress and other mail plugins' sender settings untouched.

An enabled but incomplete or undecryptable configuration fails mail submission through `wp_mail_failed` rather than silently falling back to PHP mail. Re-enter the password after changing WordPress salts. KISSMTP closes existing SMTP sessions before configuring each message and restores its changes after delivery, including its envelope-sender override, so shared PHPMailer state does not leak between sites or messages.

Use only one mail transport plugin at a time. Other plugins may intercept `wp_mail()` or override SMTP settings; the test panel reports an unverified result when WordPress reports success without using KISSMTP's configured transport.

## Security

- Stored passwords use AES-256-GCM authenticated encryption and are never rendered or logged.
- SMTP protocol debug logging is always disabled to prevent credential leakage.
- Test email requests require `manage_options`, a nonce, and are limited to one request per administrator every 30 seconds.
- TLS certificate verification is left enabled. The plugin intentionally has no "allow self-signed certificates" setting.
- A WordPress database cannot securely protect a secret from an attacker who also controls the WordPress application. Prefer `KISSMTP_PASSWORD` supplied through deployment secrets for production sites.

## Upgrade from 1.x

Version 2 migrates only non-secret legacy connection settings. It disables SMTP and discards the old password value, because the previous CBC storage format was unauthenticated and cannot be safely trusted. Re-enter the password and explicitly enable SMTP after upgrading.

## Multisite

Configuration is per site. Network-wide settings and network activation are not supported.

## Development checks

Run PHP syntax checks against the plugin files and run the WordPress coding standards/static-analysis tooling supplied by your project before release. Test against the oldest supported PHP/WordPress pair and current WordPress/PHP, using a local SMTP test server for TLS, implicit TLS, authenticated, and unauthenticated flows.

Run the interoperability regression checks against an unpacked WordPress source distribution (no installation or database required):

```sh
KISSMTP_WP_ROOT=/path/to/wordpress php tests/interoperability.php
```

These checks use WordPress's real hooks and `wp_mail()` with a network-free PHPMailer test double. They cover failure handling, transport identity and cleanup across site configurations, and test-email interception; they do not replace live SMTP/TLS delivery tests.
