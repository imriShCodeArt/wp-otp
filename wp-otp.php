<?php
/**
 * Plugin Name: WP OTP - One-Time Password Authentication
 * Plugin URI: https://github.com/yourusername/wp-otp
 * Description: A WordPress plugin that provides OTP-based authentication, allowing users to register and login using email or SMS verification codes instead of traditional passwords.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-otp
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package WpOtp
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_OTP_VERSION', '1.0.0');
define('WP_OTP_URL', plugin_dir_url(__FILE__));
define('WP_OTP_PATH', plugin_dir_path(__FILE__));

// Autoloader
require_once WP_OTP_PATH . 'vendor/autoload.php';

// Load admin classes
require_once WP_OTP_PATH . 'admin/class-admin-page.php';
require_once WP_OTP_PATH . 'admin/class-wp-otp-admin-fields.php';
require_once WP_OTP_PATH . 'admin/class-admin-ajax.php';

// Load text domain
add_action('init', 'wp_otp_load_textdomain');

// Initialize plugin
add_action('plugins_loaded', 'wp_otp_plugins_loaded');

// Activation hook
register_activation_hook(__FILE__, 'wp_otp_activate_hook');

// Deactivation hook
register_deactivation_hook(__FILE__, 'wp_otp_deactivate_hook');

// Uninstall hook
register_uninstall_hook(__FILE__, 'wp_otp_uninstall');

// Add cleanup cron job
add_action('wp_otp_cleanup_expired_codes', 'wp_otp_cleanup_cron');

// Schedule cleanup if not already scheduled
if (!wp_next_scheduled('wp_otp_cleanup_expired_codes')) {
    wp_schedule_event(time(), 'hourly', 'wp_otp_cleanup_expired_codes');
}

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wp_otp_plugin_action_links');

// Add plugin row meta
add_filter('plugin_row_meta', 'wp_otp_plugin_row_meta', 10, 2);

// Security headers for OTP pages
add_action('send_headers', 'wp_otp_security_headers');

// Disable XML-RPC if OTP-only auth is enabled
add_filter('xmlrpc_enabled', 'wp_otp_xmlrpc_filter');

// Add custom capabilities for OTP management
add_action('admin_init', 'wp_otp_add_capabilities');

// Add REST API endpoints for OTP (if needed)
add_action('rest_api_init', 'wp_otp_rest_api_init');

// Add shortcode for OTP form
add_shortcode('wp_otp_login', 'wp_otp_login_shortcode');

// Add admin notice for first-time setup
add_action('admin_notices', 'wp_otp_admin_notice');

// Add footer credit (optional)
add_action('wp_footer', 'wp_otp_footer_credit');

// Function definitions
function wp_otp_load_textdomain()
{
    load_plugin_textdomain('wp-otp', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

function wp_otp_plugins_loaded()
{
    // Load text domain
    load_plugin_textdomain('wp-otp', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Load helpers first
    require_once WP_OTP_PATH . 'includes/helpers.php';
    
    // Initialize the plugin
    wp_otp_init();
}

function wp_otp_activate_hook()
{
    global $wpdb;

    $codes_table = esc_sql($wpdb->prefix . 'otp_codes');
    $logs_table = esc_sql($wpdb->prefix . 'otp_logs');
    $charset_collate = $wpdb->get_charset_collate();

    $sql_codes = "CREATE TABLE $codes_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        contact VARCHAR(255) NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts INT DEFAULT 0,
        status ENUM('pending','verified','expired') DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql_logs = "CREATE TABLE $logs_table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_type VARCHAR(50) NOT NULL,
        subject VARCHAR(100) NULL,
        contact VARCHAR(255) NOT NULL,
        message TEXT NULL,
        channel VARCHAR(50) NULL,
        user_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_codes);
    dbDelta($sql_logs);

    // Set default settings if not already set
    if (get_option('wp_otp_settings') === false) {
        $default_settings = [
            'otp_channels' => ['email'],
            'otp_cooldown' => 30,
            'otp_length' => 6,
            'otp_expiry' => 5,
            'otp_resend_limit' => 3,
            'otp_resend_window' => 15,
            'email_subject' => 'Your OTP Code',
            'email_body' => 'Your OTP code is: {otp}',
            'sms_sender' => '',
            'sms_message' => 'Your OTP code is {OTP}. It will expire in {MINUTES} minutes.',
            'sms_username' => '',
            'sms_password' => '',
            'sms_access_token' => '',
            'phone_only_auth' => '0',
        ];
        update_option('wp_otp_settings', $default_settings);
    }
    
    // Run migration for logs table - only if table exists and has data
    try {
        wp_otp_migrate_logs_table();
    } catch (Exception $e) {
        // Log the error but don't fail activation
        error_log('WP OTP: Migration error during activation: ' . $e->getMessage());
    }
}

/**
 * Migrate logs table to add subject column and reorganize log types.
 */
function wp_otp_migrate_logs_table()
{
    global $wpdb;
    
    $logs_table = $wpdb->prefix . 'otp_logs';
    
    // Check if table exists first
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'");
    if (!$table_exists) {
        return; // Table doesn't exist, nothing to migrate
    }
    
    // Check if subject column exists
    $column_exists = $wpdb->get_results(
        "SHOW COLUMNS FROM $logs_table LIKE 'subject'"
    );
    
    if (empty($column_exists)) {
        // Add subject column
        $result = $wpdb->query("ALTER TABLE $logs_table ADD COLUMN subject VARCHAR(100) NULL AFTER event_type");
        if ($result === false) {
            error_log('WP OTP: Failed to add subject column to logs table');
            return;
        }
    }
    
    // Check if there are any existing logs to migrate
    $log_count = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
    if ($log_count == 0) {
        return; // No logs to migrate
    }
    
    // Update existing logs to map old event_types to new subjects and standardize types
    $log_mappings = [
        // Authentication related
        'auth_success' => ['subject' => 'auth_success', 'type' => 'info'],
        'auth_failed' => ['subject' => 'auth_failed', 'type' => 'error'],
        'auth_attempt' => ['subject' => 'auth_attempt', 'type' => 'info'],
        'user_lookup_start' => ['subject' => 'user_lookup_start', 'type' => 'debug'],
        'user_found' => ['subject' => 'user_found', 'type' => 'info'],
        'user_not_found' => ['subject' => 'user_not_found', 'type' => 'info'],
        'user_creation_start' => ['subject' => 'user_creation_start', 'type' => 'info'],
        'user_creation_failed' => ['subject' => 'user_creation_failed', 'type' => 'error'],
        'user_ready' => ['subject' => 'user_ready', 'type' => 'info'],
        'login_attempt' => ['subject' => 'login_attempt', 'type' => 'info'],
        'login_verification' => ['subject' => 'login_verification', 'type' => 'debug'],
        'otp_verified' => ['subject' => 'otp_verified', 'type' => 'info'],
        
        // Database related
        'db_validation_failed' => ['subject' => 'db_validation_failed', 'type' => 'error'],
        'db_otp_saved' => ['subject' => 'db_otp_saved', 'type' => 'info'],
        'db_otp_save_failed' => ['subject' => 'db_otp_save_failed', 'type' => 'error'],
        'db_otp_not_found' => ['subject' => 'db_otp_not_found', 'type' => 'warning'],
        'db_status_updated' => ['subject' => 'db_status_updated', 'type' => 'info'],
        'db_status_update_failed' => ['subject' => 'db_status_update_failed', 'type' => 'error'],
        'db_attempts_incremented' => ['subject' => 'db_attempts_incremented', 'type' => 'info'],
        'db_attempts_increment_failed' => ['subject' => 'db_attempts_increment_failed', 'type' => 'error'],
        'db_cleanup_completed' => ['subject' => 'db_cleanup_completed', 'type' => 'info'],
        'db_high_attempt_count' => ['subject' => 'db_high_attempt_count', 'type' => 'warning'],
        'db_error' => ['subject' => 'db_error', 'type' => 'error'],
        'db_expired_otps_found' => ['subject' => 'db_expired_otps_found', 'type' => 'info'],
        
        // OTP sending/verification
        'send_success' => ['subject' => 'send_success', 'type' => 'info'],
        'send_failed' => ['subject' => 'send_failed', 'type' => 'error'],
        'send_blocked' => ['subject' => 'send_blocked', 'type' => 'warning'],
        'verify_success' => ['subject' => 'verify_success', 'type' => 'info'],
        'verify_failed' => ['subject' => 'verify_failed', 'type' => 'error'],
        
        // SMS related
        'sms_sent' => ['subject' => 'sms_sent', 'type' => 'info'],
        'sms_failed' => ['subject' => 'sms_failed', 'type' => 'error'],
        'sms_balance_failed' => ['subject' => 'sms_balance_failed', 'type' => 'error'],
        
        // User creation related
        'registration_check' => ['subject' => 'registration_check', 'type' => 'debug'],
        'username_generated' => ['subject' => 'username_generated', 'type' => 'debug'],
        'user_data_prepared' => ['subject' => 'user_data_prepared', 'type' => 'debug'],
        'registration_temporarily_enabled' => ['subject' => 'registration_temporarily_enabled', 'type' => 'info'],
        'registration_restored' => ['subject' => 'registration_restored', 'type' => 'info'],
        
        // System related
        'system' => ['subject' => 'system', 'type' => 'info'],
        'debug' => ['subject' => 'debug', 'type' => 'debug'],
        'info' => ['subject' => 'info', 'type' => 'info'],
        'warning' => ['subject' => 'warning', 'type' => 'warning'],
        'error' => ['subject' => 'error', 'type' => 'error'],
        'critical' => ['subject' => 'critical', 'type' => 'critical'],
    ];
    
    foreach ($log_mappings as $old_event_type => $mapping) {
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $logs_table SET subject = %s, event_type = %s WHERE event_type = %s",
            $mapping['subject'],
            $mapping['type'],
            $old_event_type
        ));
        
        if ($result === false) {
            error_log("WP OTP: Failed to update logs for event_type: $old_event_type");
        }
    }
    
    // Update any remaining logs that don't have a subject
    $result = $wpdb->query(
        "UPDATE $logs_table SET subject = event_type WHERE subject IS NULL"
    );
    
    if ($result === false) {
        error_log('WP OTP: Failed to update remaining logs with subject');
    }
}

function wp_otp_deactivate_hook()
{
    // Clean up any scheduled events or temporary data
    wp_clear_scheduled_hook('wp_otp_cleanup_expired_codes');
}

function wp_otp_cleanup_cron()
{
    global $wpdb;
    $table = $wpdb->prefix . 'otp_codes';
    return $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table WHERE expires_at < %s",
            current_time('mysql')
        )
    );
}

function wp_otp_plugin_action_links($links)
{
    $settings_link = '<a href="' . admin_url('admin.php?page=wp-otp') . '">' . __('Settings', 'wp-otp') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function wp_otp_plugin_row_meta($links, $file)
{
    if (plugin_basename(__FILE__) === $file) {
        $links[] = '<a href="' . admin_url('admin.php?page=wp-otp') . '">' . __('Documentation', 'wp-otp') . '</a>';
        $links[] = '<a href="https://github.com/yourusername/wp-otp/issues">' . __('Support', 'wp-otp') . '</a>';
    }
    return $links;
}

function wp_otp_security_headers()
{
    if (isset($_GET['action']) && $_GET['action'] === 'login') {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
    }
}

function wp_otp_xmlrpc_filter($enabled)
{
    $settings = get_option('wp_otp_settings', []);
    if (isset($settings['phone_only_auth']) && $settings['phone_only_auth'] === '1') {
        return false;
    }
    return $enabled;
}

function wp_otp_add_capabilities()
{
    $role = get_role('administrator');
    if ($role) {
        $role->add_cap('manage_otp_settings');
        $role->add_cap('view_otp_logs');
    }
}

function wp_otp_rest_api_init()
{
    // Future: Add REST API endpoints for mobile apps
}

function wp_otp_login_shortcode($atts)
{
    $atts = shortcode_atts([
        'redirect' => '',
        'channel' => 'email',
    ], $atts);
    
    ob_start();
    include WP_OTP_PATH . 'templates/auth-login-form.php';
    return ob_get_clean();
}

function wp_otp_admin_notice()
{
    if (!get_option('wp_otp_settings')) {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>' . __('WP OTP Plugin', 'wp-otp') . '</strong>: ';
        echo __('Please configure your OTP settings in the <a href="' . admin_url('admin.php?page=wp-otp') . '">WP OTP settings page</a>.', 'wp-otp');
        echo '</p></div>';
    }
}

function wp_otp_footer_credit()
{
    if (is_user_logged_in() && current_user_can('manage_options')) {
        echo '<!-- WP OTP Plugin v' . WP_OTP_VERSION . ' -->';
    }
}

/**
 * Initialize the plugin.
 */
function wp_otp_init()
{
    // Define plugin constants if not already defined
    if (!defined('WP_OTP_URL')) {
        define('WP_OTP_URL', plugin_dir_url(__FILE__));
    }
    if (!defined('WP_OTP_PATH')) {
        define('WP_OTP_PATH', plugin_dir_path(__FILE__));
    }
    if (!defined('WP_OTP_VERSION')) {
        define('WP_OTP_VERSION', '1.0.0');
    }

    // Ensure we have the required path
    if (!defined('WP_OTP_PATH') || !is_dir(WP_OTP_PATH)) {
        return;
    }

    // Load required files
    $includes_path = WP_OTP_PATH . 'includes/';
    
    // Load logger first so we can use it for debugging
    if (file_exists($includes_path . 'class-logger.php')) {
        require_once $includes_path . 'class-logger.php';
    }
    
    // Initialize logger
    $logger = new \WpOtp\WP_OTP_Logger();
    // $logger->debug('Loading files from: ' . $includes_path);

    // Debug: Check if we're in admin
    if (is_admin()) {
        // $logger->debug('Initializing in admin context');
    }
    
    // Load core classes
    if (file_exists($includes_path . 'class-otp-codegenerator.php')) {
        // $logger->debug('Loading class-otp-codegenerator.php');
        require_once $includes_path . 'class-otp-codegenerator.php';
    } else {
        $logger->warning('class-otp-codegenerator.php NOT found');
    }
    
    if (file_exists($includes_path . 'class-delivery-email.php')) {
        // $logger->debug('Loading class-delivery-email.php');
        require_once $includes_path . 'class-delivery-email.php';
    } else {
        $logger->warning('class-delivery-email.php NOT found');
    }
    
    if (file_exists($includes_path . 'class-logger.php')) {
        // $logger->debug('Loading class-logger.php');
        require_once $includes_path . 'class-logger.php';
    } else {
        $logger->warning('class-logger.php NOT found');
    }
    
    if (file_exists($includes_path . 'class-auth-overrides.php')) {
        // $logger->debug('Loading class-auth-overrides.php');
        require_once $includes_path . 'class-auth-overrides.php';
    } else {
        $logger->warning('class-auth-overrides.php NOT found');
    }
    
    if (file_exists($includes_path . 'class-otp-repository.php')) {
        // $logger->debug('Loading class-otp-repository.php');
        require_once $includes_path . 'class-otp-repository.php';
    } else {
        $logger->warning('class-otp-repository.php NOT found');
    }
    
    if (file_exists($includes_path . 'class-otp-manager.php')) {
        // $logger->debug('Loading class-otp-manager.php');
        require_once $includes_path . 'class-otp-manager.php';
    } else {
        $logger->warning('class-otp-manager.php NOT found');
    }
    
    if (file_exists($includes_path . 'class-shortcodes.php')) {
        // $logger->debug('Loading class-shortcodes.php');
        require_once $includes_path . 'class-shortcodes.php';
    } else {
        $logger->warning('class-shortcodes.php NOT found');
    }

    // Initialize core classes if they exist
    if (class_exists('WpOtp\\WP_OTP_Auth_Overrides')) {
        // $logger->debug('Auth_Overrides class found, instantiating');
        new \WpOtp\WP_OTP_Auth_Overrides();
    } else {
        $logger->warning('Auth_Overrides class NOT found');
    }
    
    if (class_exists('WpOtp\\WP_OTP_Manager')) {
        // $logger->debug('Manager class found, instantiating');
        new \WpOtp\WP_OTP_Manager();
    } else {
        $logger->warning('Manager class NOT found');
    }
    
    if (class_exists('WpOtp\\WP_OTP_Shortcodes')) {
        // $logger->debug('Shortcodes class found, instantiating');
        new \WpOtp\WP_OTP_Shortcodes();
    } else {
        $logger->warning('Shortcodes class NOT found');
    }

    // Initialize admin classes if in admin
    if (is_admin()) {
        // $logger->debug('Checking admin classes');
        
        if (class_exists('WpOtp\\WP_OTP_Admin_Page')) {
            // $logger->debug('Admin_Page class found, instantiating');
            new \WpOtp\WP_OTP_Admin_Page();
        } else {
            $logger->warning('Admin_Page class NOT found');
        }
        
        if (class_exists('WpOtp\\WP_OTP_Admin_Fields')) {
            // $logger->debug('Admin_Fields class found, instantiating');
            new \WpOtp\WP_OTP_Admin_Fields();
        } else {
            $logger->warning('Admin_Fields class NOT found');
        }
        
        if (class_exists('WpOtp\\WP_OTP_Admin_Ajax')) {
            // $logger->debug('Admin_Ajax class found, instantiating');
            new \WpOtp\WP_OTP_Admin_Ajax();
        } else {
            $logger->warning('Admin_Ajax class NOT found');
        }
    }
} 