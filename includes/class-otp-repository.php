<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Repository
 *
 * Responsible for interacting with the OTP database table.
 */
class WP_OTP_Repository
{

    protected $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'otp_codes';
    }

    /**
     * Save a new OTP record.
     *
     * @param string $contact
     * @param string $hash
     * @param string $expires_at
     * @return int|false Row ID or false on failure.
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
     * Get OTP record for a contact.
     *
     * @param string $contact
     * @return object|null
     */
    public function get_otp_record($contact)
    {
        return wp_otp_get_code($contact);
    }

    /**
     * Update OTP status for a contact.
     *
     * @param string $contact
     * @param string $status
     * @return int|false Rows updated or false.
     */
    public function update_status($contact, $status)
    {
        return wp_otp_update_code($contact, [
            'status' => $status
        ]);
    }

    /**
     * Increment attempts counter.
     *
     * @param string $contact
     * @return int|false Rows updated or false.
     */
    public function increment_attempts($contact)
    {
        $record = $this->get_otp_record($contact);
        if (!$record) {
            return false;
        }

        $new_attempts = (int) $record->attempts + 1;

        return wp_otp_update_code($contact, [
            'attempts' => $new_attempts
        ]);
    }

    /**
     * Delete expired OTPs.
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
     * Count OTPs generated recently for resend limiting.
     *
     * @param string $contact
     * @param int $window_minutes
     * @return int
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
