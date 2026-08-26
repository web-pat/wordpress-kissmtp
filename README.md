# KISSMTP - Simple SMTP for WordPress

A lightweight WordPress plugin that forces all outbound emails through SMTP for reliable email delivery. There is a bunch of plugins like this for the same purpose. Why do another one?
This plugin is *k*eep *i*t *s*imple *s*tupid. This plugin intends to do only what it says it does and there are not further upsells or nagging screens.

**Not yet tested nor ready for productive use!**

## Features

- **SMTP Configuration**: Configure any SMTP server (Gmail, Outlook, SendGrid, Mailgun, etc.)
- **Encryption Support**: SSL, TLS, or no encryption
- **Authentication**: Username/password authentication with encrypted credential storage
- **From Address Override**: Force custom sender email and name on all outgoing mail
- **Return Path**: Set return path header for better deliverability
- **Test Email**: Built-in test email functionality to verify configuration
- **Debug Logging**: SMTP debug output when WP_DEBUG and WP_DEBUG_LOG are enabled
- **Password Security**: AES-256-CBC encryption for stored SMTP credentials

## Requirements

- WordPress 5.5 or higher
- PHP 7.4 or higher
- OpenSSL extension (for password encryption)

## Installation

### Method 1: Upload via WordPress Admin

1. Download or clone this repository
2. Zip the `wordpress-kissmtp` folder
3. Go to **Plugins → Add New → Upload Plugin**
4. Choose the zip file and click **Install Now**
5. Click **Activate Plugin**

### Method 2: Manual Installation

1. Download or clone this repository
2. Copy the `wordpress-kissmtp` folder to `/wp-content/plugins/`
3. Go to **Plugins** in WordPress admin
4. Find **KISSMTP** and click **Activate**

### Method 3: WP-CLI

```bash
wp plugin install /path/to/wordpress-kissmtp --activate
```

## Configuration

1. Navigate to **Settings → KISSMTP** in your WordPress admin
2. Configure the SMTP settings:

| Field | Description | Example |
|-------|-------------|---------|
| SMTP Host | Your SMTP server hostname | `smtp.gmail.com` |
| SMTP Port | Server port number | `587` (TLS) or `465` (SSL) |
| Encryption | SSL, TLS, or None | `TLS` |
| Authentication | Enable username/password | Checked |
| Username | SMTP username (usually email) | `user@gmail.com` |
| Password | SMTP password or app password | `your-password` |

3. Configure the From Address (optional):

| Field | Description | Example |
|-------|-------------|---------|
| From Email | Sender email address | `wordpress@example.com` |
| From Name | Sender display name | `My Website` |
| Return Path | Set return-path header | Checked |

4. Click **Save Settings**

### Common SMTP Settings

**Gmail/Google Workspace:**
- Host: `smtp.gmail.com`
- Port: `587`
- Encryption: TLS
- Username: Your full email address
- Password: App-specific password (enable 2FA first)

**Microsoft Outlook/365:**
- Host: `smtp.office365.com`
- Port: `587`
- Encryption: TLS
- Username: Your full email address
- Password: Your password or app password

**SendGrid:**
- Host: `smtp.sendgrid.net`
- Port: `587`
- Encryption: TLS
- Username: `apikey`
- Password: Your SendGrid API key

**Mailgun:**
- Host: `smtp.mailgun.org`
- Port: `587`
- Encryption: TLS
- Username: Your Mailgun email
- Password: Your Mailgun password

## Testing

1. Go to **Settings → KISSMTP**
2. Scroll to the **Test Email** section
3. Enter a recipient email address
4. Click **Send Test Email**
5. Check the recipient inbox and any error messages

## Debugging

Enable WordPress debug logging to see SMTP debug output:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

SMTP debug logs are written to `/wp-content/debug.log` with the prefix `[KISSMTP SMTP]`.

## Uninstalling

The plugin cleanly removes all data when deleted:

1. Go to **Plugins**
2. Click **Deactivate** on KISSMTP
3. Click **Delete**

All SMTP settings and transients are automatically removed.

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request
