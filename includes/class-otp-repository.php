<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Repository
 *
 * Responsible for interacting with the OTP database table.
 *
 * @package WpOtp
 */
class WP_OTP_Repository
{
    /**
     * @var string The name of the OTP table.
     */
    protected $table_name;

    /**
     * WP_OTP_Repository constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'otp_codes';
    }

    /**
     * Save a new OTP record.
     *
     * @param string $contact    Contact identifier (email or phone).
     * @param string $hash       Hashed OTP code.
     * @param string $expires_at Expiry timestamp (Y-m-d H:i:s).
     *
     * @return int|false Row ID of the inserted record or false on failure.
     */
    public function save_otp($contact, $hash, $expires_at)
    {
        return wp_otp_insert_code(
            $contact,
            $hash,
            $expires_at
        );
    }

    /**
     * Retrieve the OTP record for a specific contact.
     *
     * @param string $contact The contact identifier.
     *
     * @return object|null Database row object or null if not found.
     */
    public function get_otp_record($contact)
    {
        return wp_otp_get_code($contact);
    }

    /**
     * Update the OTP status for a specific contact.
     *
     * @param string $contact The contact identifier.
     * @param string $status  New status value (e.g. "pending", "verified", "expired").
     *
     * @return int|false Number of rows updated, or false on failure.
     */
    public function update_status($contact, $status)
    {
        return wp_otp_update_code($contact, [
            'status' => $status
        ]);
    }

    /**
     * Increment the failed attempts counter for a contact.
     *
     * @param string $contact The contact identifier.
     *
     * @return int|false Number of rows updated, or false on failure.
     */
    public function increment_attempts($contact)
    {
        global $wpdb;

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table_name} SET attempts = attempts + 1 WHERE contact = %s",
                $contact
            )
        );
    }

    /**
     * Delete expired OTP codes from the database.
     *
     * @return int Number of rows deleted.
     */
    public function cleanup_expired()
    {
        global $wpdb;

        return $wpdb->query(
            "DELETE FROM {$this->table_name} WHERE expires_at < NOW()"
        );
    }

    /**
     * Count how many OTPs were recently generated for a contact.
     *
     * Useful for enforcing resend limits.
     *
     * @param string $contact        The contact identifier.
     * @param int    $window_minutes Time window in minutes.
     *
     * @return int The number of OTPs generated in the time window.
     */
    public function count_recent_otps($contact, $window_minutes)
    {
        global $wpdb;

        $since = date('Y-m-d H:i:s', strtotime("-$window_minutes minutes", current_time('timestamp', 1)));

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE contact = %s AND created_at >= %s",
                $contact,
                $since
            )
        );
    }
}
