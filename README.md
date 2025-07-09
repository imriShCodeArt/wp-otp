# WP OTP - One-Time Password Authentication

A WordPress plugin that provides OTP-based authentication, allowing users to register and login using email or SMS verification codes instead of traditional passwords.

## Features

### 🔐 **OTP-Only Authentication**
- **No Passwords Required**: Users can register and login using only email or phone number
- **Automatic User Creation**: New users are created automatically when they first authenticate
- **Seamless Integration**: Replaces WordPress default login form with OTP authentication
- **Multi-Channel Support**: Send OTP via email or SMS

### 🛡️ **Security Features**
- **Rate Limiting**: Prevents abuse with configurable resend limits
- **Expiration**: OTP codes expire after configurable time period
- **Attempt Limits**: Maximum verification attempts to prevent brute force
- **Secure Hashing**: OTP codes are hashed using WordPress password_hash()
- **Comprehensive Logging**: All authentication events are logged for audit

### 📱 **User Experience**
- **Modern UI**: Clean, responsive design that works on all devices
- **Step-by-Step Flow**: Intuitive two-step authentication process
- **Auto-Detection**: Automatically detects email vs phone input
- **Real-time Validation**: Client-side validation with helpful error messages
- **Resend Functionality**: Users can request new OTP codes with cooldown

### ⚙️ **Admin Features**
- **Easy Configuration**: Simple admin interface for all settings
- **Channel Management**: Enable/disable email and SMS channels
- **Template Customization**: Customize email and SMS message templates
- **Activity Logs**: View detailed logs of all OTP activities
- **Statistics**: Monitor OTP usage and success rates

## Installation

### Requirements
- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

### Installation Steps

1. **Download the Plugin**
   ```bash
   git clone https://github.com/yourusername/wp-otp.git
   ```

2. **Install Dependencies**
   ```bash
   cd wp-otp
   composer install
   ```

3. **Upload to WordPress**
   - Upload the `wp-otp` folder to `/wp-content/plugins/`
   - Or zip the folder and upload via WordPress admin

4. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "WP OTP" and click "Activate"

5. **Configure Settings**
   - Go to WordPress Admin → WP OTP
   - Configure your OTP settings
   - Enable "Phone-only Authentication" for OTP-only login

## Configuration

### Basic Settings

1. **OTP Channels**: Choose which channels to enable (Email, SMS)
2. **OTP Length**: Set the number of digits (4-10)
3. **OTP Expiry**: Set how long OTP codes are valid (in minutes)
4. **Resend Limits**: Configure how many times users can request new OTPs
5. **Cooldown**: Set delay between resend requests (in seconds)

### Email Configuration

1. **Email Subject**: Customize the subject line for OTP emails
2. **Email Body**: Customize the email content with placeholders:
   - `{OTP}` - The OTP code
   - `{MINUTES}` - Expiry time in minutes

### SMS Configuration (Optional)

1. **SMS Provider**: Configure 019 SMS service credentials
2. **SMS Message**: Customize SMS content with placeholders
3. **Sender Name**: Set the sender name for SMS messages

### Advanced Settings

1. **Phone-only Authentication**: Enable to bypass WordPress default login
2. **User Role**: Set default role for new users (default: subscriber)
3. **Redirect URLs**: Configure where users go after successful login

## Usage

### For End Users

#### Login Process
1. **Enter Contact**: User enters email or phone number
2. **Choose Channel**: Select email or SMS delivery
3. **Send OTP**: Click "Send OTP" to receive verification code
4. **Enter OTP**: Enter the 6-digit code received
5. **Verify & Login**: Click "Verify & Login" to complete authentication

#### Registration
- **Automatic**: New users are created automatically on first successful OTP verification
- **No Registration Form**: No separate registration process needed
- **Contact Verification**: Email/phone is automatically verified during login

### For Administrators

#### Shortcode Usage
```php
// Display OTP login form anywhere
[wp_otp_login]

// With custom redirect
[wp_otp_login redirect="/dashboard"]

// With specific channel
[wp_otp_login channel="email"]
```

#### Template Integration
```php
// Include OTP form in theme
<?php echo do_shortcode('[wp_otp_login]'); ?>

// Or include directly
<?php include WP_OTP_PATH . 'templates/auth-login-form.php'; ?>
```

#### Custom Hooks
```php
// Customize email subject
add_filter('wp_otp_email_subject', function($subject, $email, $otp) {
    return 'Your verification code: ' . $otp;
}, 10, 3);

// Customize email message
add_filter('wp_otp_email_message', function($message, $email, $otp, $expiry) {
    return "Your code is: $otp (valid for $expiry minutes)";
}, 10, 4);

// Customize redirect after login
add_filter('wp_otp_login_redirect', function($redirect_url, $user) {
    return home_url('/dashboard/');
}, 10, 2);
```

## Security Considerations

### Rate Limiting
- **Resend Limits**: Configurable maximum resend attempts per time window
- **Cooldown Periods**: Minimum time between resend requests
- **IP-based Limits**: Additional protection against abuse

### Data Protection
- **Secure Storage**: OTP codes are hashed, never stored in plain text
- **Automatic Cleanup**: Expired OTP codes are automatically removed
- **Session Management**: Secure session handling for authenticated users

### Audit Trail
- **Comprehensive Logging**: All authentication events are logged
- **User Tracking**: Track which users are using OTP authentication
- **Error Monitoring**: Log failed attempts and system errors

## Troubleshooting

### Common Issues

#### OTP Not Received
1. **Check Email Settings**: Verify SMTP configuration
2. **Check SMS Settings**: Verify SMS provider credentials
3. **Check Spam Folder**: OTP emails might be marked as spam
4. **Rate Limiting**: User may have exceeded resend limits

#### Login Not Working
1. **Check OTP Expiry**: Codes expire after configured time
2. **Check Attempt Limits**: Too many failed attempts
3. **Check User Creation**: Ensure user creation is working
4. **Check Logs**: Review admin logs for errors

#### Admin Issues
1. **Check Permissions**: Ensure admin has proper capabilities
2. **Check Database**: Verify OTP tables are created
3. **Check Settings**: Verify all required settings are configured

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## API Reference

### Classes

#### `WP_OTP_Manager`
Main class for OTP generation, sending, and verification.

```php
$manager = new WP_OTP_Manager();

// Send OTP
$result = $manager->send_otp($contact, $channel, $length);

// Verify OTP
$result = $manager->verify_otp($contact, $otp, $max_attempts);
```

#### `WP_OTP_Repository`
Database operations for OTP records.

```php
$repository = new WP_OTP_Repository();

// Save OTP
$id = $repository->save_otp($contact, $hash, $expires_at);

// Get OTP record
$record = $repository->get_otp_record($contact);

// Update status
$repository->update_status($contact, 'verified');
```

#### `WP_OTP_Auth_Overrides`
Handles OTP-only authentication flow.

```php
$auth = new WP_OTP_Overrides();
// Automatically handles WordPress authentication override
```

### Hooks and Filters

#### Actions
- `wp_otp_send_otp` - Fired when OTP is sent
- `wp_otp_verify_otp` - Fired when OTP is verified
- `wp_otp_user_created` - Fired when new user is created
- `wp_otp_login_success` - Fired on successful login

#### Filters
- `wp_otp_email_subject` - Customize email subject
- `wp_otp_email_message` - Customize email message
- `wp_otp_sms_message` - Customize SMS message
- `wp_otp_login_redirect` - Customize redirect URL
- `wp_otp_user_role` - Customize default user role

## Development

### Local Development

1. **Clone Repository**
   ```bash
   git clone https://github.com/yourusername/wp-otp.git
   cd wp-otp
   ```

2. **Install Dependencies**
   ```bash
   composer install
   ```

3. **Setup WordPress**
   - Point your local WordPress to the plugin directory
   - Activate the plugin
   - Configure settings

4. **Run Tests**
   ```bash
   composer test
   ```

### Code Standards

The plugin follows WordPress coding standards:

```bash
# Check code style
composer lint

# Fix code style
composer fix
```

### Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This plugin is licensed under the GPL v2 or later.

## Support

- **Documentation**: [Plugin Wiki](https://github.com/yourusername/wp-otp/wiki)
- **Issues**: [GitHub Issues](https://github.com/yourusername/wp-otp/issues)
- **Email**: support@yoursite.com

## Changelog

### Version 1.0.0
- Initial release
- OTP-only authentication
- Email and SMS support
- Admin interface
- Comprehensive logging
- Security features

## Credits

- Built with WordPress best practices
- Uses WordPress native functions and hooks
- Follows WordPress coding standards
- Compatible with major WordPress themes and plugins 