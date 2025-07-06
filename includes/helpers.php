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
}

/**
 * Insert a new OTP record into wp_otp_codes.
 *
 * @param string $contact
 * @param string $code_hash
 * @param string $expires_at
 * @return int|false
 */
function wp_otp_insert_code($contact, $code_hash, $expires_at)
{
    global $wpdb;

    $table = $wpdb->prefix . 'otp_codes';

    $result = $wpdb->insert(
        $table,
        [
            'contact' => $contact,
            'code_hash' => $code_hash,
            'expires_at' => $expires_at,
            'attempts' => 0,
            'status' => 'pending',
            'created_at' => current_time('mysql', 1),
        ],
        [
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
        ]
    );

    return ($result === false) ? false : $wpdb->insert_id;
}

/**
 * Update an OTP record by contact.
 *
 * @param string $contact
 * @param array  $data
 * @return int|false
 */
function wp_otp_update_code($contact, $data)
{
    global $wpdb;

    $table = $wpdb->prefix . 'otp_codes';

    if (empty($data)) {
        return false;
    }

    $format = [];
    foreach ($data as $key => $value) {
        switch ($key) {
            case 'attempts':
                $format[] = '%d';
                break;
            case 'expires_at':
            case 'created_at':
                $format[] = '%s';
                break;
            case 'status':
            case 'code_hash':
                $format[] = '%s';
                break;
            default:
                $format[] = '%s';
                break;
        }
    }

    $result = $wpdb->update(
        $table,
        $data,
        ['contact' => $contact],
        $format,
        ['%s']
    );

    return ($result === false) ? false : $result;
}

/**
 * Retrieve an OTP record for a given contact.
 *
 * @param string $contact
 * @return object|null
 */
function wp_otp_get_code($contact)
{
    global $wpdb;

    $table = $wpdb->prefix . 'otp_codes';

    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE contact = %s LIMIT 1",
            $contact
        )
    );
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
