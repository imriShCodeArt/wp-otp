<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sanitize a phone number by stripping non-digit characters.
 *
 * @param string $phone
 * @return string
 */
function wp_otp_sanitize_phone($phone)
{
    return preg_replace('/\D+/', '', $phone);
}

/**
 * Return default plugin settings.
 *
 * @return array
 */
function wp_otp_default_settings()
{
    return [
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
}

/**
 * Retrieve merged plugin settings (defaults + saved).
 *
 * @return array
 */
function wp_otp_get_settings()
{
    $defaults = wp_otp_default_settings();
    $saved = get_option('wp_otp_settings', []);
    return array_merge($defaults, $saved);
}

/**
 * Delete expired OTP codes.
 *
 * @return int
 */
function wp_otp_cleanup_expired_codes()
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

/**
 * Run on plugin activation. Creates DB tables and default options.
 *
 * @return void
 */
function wp_otp_activate()
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

    if (get_option('wp_otp_settings') === false) {
        update_option('wp_otp_settings', wp_otp_default_settings());
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

/**
 * Load plugin text domain for translations.
 */
function wp_otp_load_textdomain()
{
    load_plugin_textdomain(
        'wp-otp',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

/**
 * Initialize the plugin.
 */
function wp_otp_init()
{
    try {
        // Manually include required files to ensure they're loaded
        $required_files = [
            'includes/class-otp-manager.php',
            'includes/class-otp-repository.php',
            'includes/class-logger.php',
            'includes/class-auth-overrides.php',
            'includes/class-shortcodes.php'
        ];

        foreach ($required_files as $file) {
            $file_path = WP_OTP_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("WP OTP: Required file not found: $file_path");
            }
        }

        // Always load core classes
        if (class_exists(WP_OTP_Manager::class)) {
            new WP_OTP_Manager();
        } else {
            error_log('WP OTP: WP_OTP_Manager class not found');
        }
        
        if (class_exists(WP_OTP_Auth_Overrides::class)) {
            new WP_OTP_Auth_Overrides();
        } else {
            error_log('WP OTP: WP_OTP_Auth_Overrides class not found');
        }

        // Load frontend or shared classes
        if (class_exists(WP_OTP_Shortcodes::class)) {
            new WP_OTP_Shortcodes();
        } else {
            error_log('WP OTP: WP_OTP_Shortcodes class not found');
        }

        // Admin classes
        if (is_admin()) {
            if (class_exists(WP_OTP_Admin_Page::class)) {
                new WP_OTP_Admin_Page();
            } else {
                error_log('WP OTP: WP_OTP_Admin_Page class not found');
            }
            if (class_exists(WP_OTP_Admin_Fields::class)) {
                new WP_OTP_Admin_Fields();
            } else {
                error_log('WP OTP: WP_OTP_Admin_Fields class not found');
            }
            if (class_exists(WP_OTP_Admin_Ajax::class)) {
                new WP_OTP_Admin_Ajax();
            } else {
                error_log('WP OTP: WP_OTP_Admin_Ajax class not found');
            }
        }
    } catch (Exception $e) {
        error_log('WP OTP: Error during plugin initialization: ' . $e->getMessage());
    }
}

/**
 * Register WPML/Polylang strings on admin_init for default settings.
 */
function wp_otp_register_default_strings()
{
    $defaults = wp_otp_default_settings();

    foreach (wp_otp_get_translatable_keys() as $key => $label) {
        $value = $defaults[$key] ?? '';
        do_action('wpml_register_single_string', 'WP OTP', $label, $value);
    }
}

/**
 * Register WPML strings whenever plugin settings are saved.
 */
function wp_otp_register_updated_strings($new_value)
{
    if (empty($new_value) || !is_array($new_value)) {
        return;
    }

    foreach (wp_otp_get_translatable_keys() as $key => $label) {
        if (!empty($new_value[$key])) {
            do_action('wpml_register_single_string', 'WP OTP', $label, $new_value[$key]);
        }
    }
}

/**
 * Returns keys/labels for all strings that should be translatable.
 *
 * @return array
 */
function wp_otp_get_translatable_keys()
{
    return [
        'email_subject' => 'Email Subject',
        'email_body' => 'Email Body',
        'sms_sender' => 'SMS Sender',
        'sms_message' => 'SMS Message',
        'sms_username' => 'SMS API Username',
        'sms_password' => 'SMS API Password',
        'sms_access_token' => 'SMS API Access Token',
    ];
}

/**
 * Optional migration: ensures all new default keys exist in saved options.
 */
function wp_otp_migrate_settings()
{
    $defaults = wp_otp_default_settings();
    $saved = get_option('wp_otp_settings', []);
    $merged = array_merge($defaults, $saved);
    update_option('wp_otp_settings', $merged);
}

/**
 * Deactivation logic for the plugin.
 */
function wp_otp_deactivate()
{
    // Place deactivation logic here if needed
}

/**
 * Plugin uninstall function.
 * 
 * This function is called when the plugin is uninstalled.
 * It cleans up all plugin data from the database.
 */
function wp_otp_uninstall()
{
    // Remove plugin options
    delete_option('wp_otp_settings');

    // Drop custom OTP tables
    global $wpdb;
    
    // Drop OTP codes table
    $codes_table = esc_sql($wpdb->prefix . 'otp_codes');
    $wpdb->query("DROP TABLE IF EXISTS `$codes_table`");

    // Drop OTP logs table
    $logs_table = esc_sql($wpdb->prefix . 'otp_logs');
    $wpdb->query("DROP TABLE IF EXISTS `$logs_table`");

    // Clear any scheduled events
    wp_clear_scheduled_hook('wp_otp_cleanup_expired_codes');

    // Remove user meta related to OTP
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wp_otp_%'");
}
