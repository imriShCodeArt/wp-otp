<?php
/**
 * Plugin Name: WP OTP
 * Description: Send and verify One-Time Passwords via email or 019 SMS for WordPress users.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: wp-otp
 * Domain Path: /languages
 */

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_OTP_PATH', plugin_dir_path(__FILE__));
define('WP_OTP_URL', plugin_dir_url(__FILE__));
define('WP_OTP_VERSION', '1.0.0');

// Composer autoload (optional)
$composer_autoload = WP_OTP_PATH . 'vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

// Load helpers
require_once __DIR__ . '/includes/helpers.php';

// Load plugin text domain
add_action('plugins_loaded', __NAMESPACE__ . '\\wp_otp_load_textdomain');

// Plugin init
add_action('init', __NAMESPACE__ . '\\wp_otp_init');

// Admin initialization
if (is_admin()) {
    add_action('admin_init', __NAMESPACE__ . '\\wp_otp_register_default_strings');
    add_action('admin_init', __NAMESPACE__ . '\\wp_otp_migrate_settings');
}

// Register WPML strings after updating settings
add_action('update_option_wp_otp_settings', __NAMESPACE__ . '\\wp_otp_register_updated_strings');

// Activation / deactivation hooks
register_activation_hook(__FILE__, __NAMESPACE__ . '\\wp_otp_activate');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\wp_otp_deactivate');
