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
     * OTP status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_EXPIRED = 'expired';

    /**
     * @var string The name of the OTP table.
     */
    protected $table_name;

    /**
     * @var \wpdb WordPress database instance.
     */
    protected $wpdb;

    /**
     * @var WP_OTP_Logger Logger instance.
     */
    protected $logger;

    /**
     * WP_OTP_Repository constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'otp_codes';
        $this->logger = new WP_OTP_Logger();
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
        if (!$this->validate_contact($contact) || !$this->validate_hash($hash) || !$this->validate_timestamp($expires_at)) {
            $this->logger->error('Invalid parameters provided for OTP save', $contact, null, get_current_user_id());
            return false;
        }

        $result = $this->insert_code($contact, $hash, $expires_at);
        
        if ($result) {
            $this->logger->info("OTP saved successfully with ID: $result", $contact, null, get_current_user_id());
        } else {
            $this->logger->error('Failed to save OTP to database', $contact, null, get_current_user_id());
        }

        return $result;
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
        if (!$this->validate_contact($contact)) {
            $this->logger->error('Invalid contact parameter for OTP retrieval', $contact, null, get_current_user_id());
            return null;
        }

        $result = $this->get_code($contact);
        
        if (!$result) {
            $this->logger->warning('No OTP record found for contact', $contact, null, get_current_user_id(), 'db_otp_not_found');
        }

        return $result;
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
        if (!$this->validate_contact($contact) || !$this->validate_status($status)) {
            $this->logger->error("Invalid parameters for status update: $status", $contact, null, get_current_user_id());
            return false;
        }

        $result = $this->update_code($contact, [
            'status' => $status
        ]);
        
        if ($result) {
            $this->logger->info("OTP status updated to: $status", $contact, null, get_current_user_id());
        } else {
            $this->logger->error("Failed to update OTP status to: $status", $contact, null, get_current_user_id());
        }

        return $result;
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
        if (!$this->validate_contact($contact)) {
            $this->logger->error('Invalid contact parameter for attempts increment', $contact, null, get_current_user_id());
            return false;
        }

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table_name} SET attempts = attempts + 1 WHERE contact = %s",
                $contact
            )
        );

        if ($result !== false) {
            $this->logger->info("Failed attempts incremented for contact", $contact, null, get_current_user_id());
        } else {
            $this->logger->error('Failed to increment attempts for contact', $contact, null, get_current_user_id());
        }

        return ($result === false) ? false : $result;
    }

    /**
     * Delete expired OTP codes from the database.
     *
     * @return int Number of rows deleted.
     */
    public function cleanup_expired()
    {
        $result = $this->wpdb->query(
            "DELETE FROM {$this->table_name} WHERE expires_at < NOW()"
        );

        $deleted_count = ($result === false) ? 0 : $result;
        
        if ($deleted_count > 0) {
            $this->logger->info("Cleaned up $deleted_count expired OTP records", 'system', null, get_current_user_id());
        }

        return $deleted_count;
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
        if (!$this->validate_contact($contact) || !$this->validate_window_minutes($window_minutes)) {
            $this->logger->error("Invalid parameters for recent OTP count: window=$window_minutes", $contact, null, get_current_user_id());
            return 0;
        }

        $since = date('Y-m-d H:i:s', strtotime("-$window_minutes minutes", current_time('timestamp', 1)));

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE contact = %s AND created_at >= %s",
                $contact,
                $since
            )
        );

        $count = ($result === null) ? 0 : (int) $result;
        
        // Log high attempt counts for monitoring
        if ($count > 5) {
            $this->logger->warning("High recent OTP count: $count in $window_minutes minutes", $contact, null, get_current_user_id(), 'db_high_attempt_count');
        }

        return $count;
    }

    /**
     * Get all valid status values.
     *
     * @return array
     */
    public static function get_valid_statuses()
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_VERIFIED,
            self::STATUS_EXPIRED,
        ];
    }

    /**
     * Insert a new OTP record into wp_otp_codes.
     *
     * @param string $contact
     * @param string $code_hash
     * @param string $expires_at
     * @return int|false
     */
    private function insert_code($contact, $code_hash, $expires_at)
    {
        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'contact' => $contact,
                'code_hash' => $code_hash,
                'expires_at' => $expires_at,
                'attempts' => 0,
                'status' => self::STATUS_PENDING,
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

        if ($result === false) {
            $this->log_db_error('insert', $contact);
        }

        return ($result === false) ? false : $this->wpdb->insert_id;
    }

    /**
     * Update an OTP record by contact.
     *
     * @param string $contact
     * @param array  $data
     * @return int|false
     */
    private function update_code($contact, $data)
    {
        if (empty($data)) {
            return false;
        }

        $format = $this->get_format_array($data);

        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            ['contact' => $contact],
            $format,
            ['%s']
        );

        if ($result === false) {
            $this->log_db_error('update', $contact);
        }

        return ($result === false) ? false : $result;
    }

    /**
     * Retrieve an OTP record for a given contact.
     *
     * @param string $contact
     * @return object|null
     */
    private function get_code($contact)
    {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE contact = %s LIMIT 1",
                $contact
            )
        );
    }

    /**
     * Generate format array for wpdb update/insert operations.
     *
     * @param array $data
     * @return array
     */
    private function get_format_array($data)
    {
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
        return $format;
    }

    /**
     * Validate contact parameter.
     *
     * @param string $contact
     * @return bool
     */
    private function validate_contact($contact)
    {
        return !empty($contact) && is_string($contact) && strlen($contact) <= 255;
    }

    /**
     * Validate hash parameter.
     *
     * @param string $hash
     * @return bool
     */
    private function validate_hash($hash)
    {
        return !empty($hash) && is_string($hash) && strlen($hash) <= 255;
    }

    /**
     * Validate timestamp parameter.
     *
     * @param string $timestamp
     * @return bool
     */
    private function validate_timestamp($timestamp)
    {
        return !empty($timestamp) && is_string($timestamp) && strtotime($timestamp) !== false;
    }

    /**
     * Validate status parameter.
     *
     * @param string $status
     * @return bool
     */
    private function validate_status($status)
    {
        return in_array($status, self::get_valid_statuses(), true);
    }

    /**
     * Validate window minutes parameter.
     *
     * @param int $window_minutes
     * @return bool
     */
    private function validate_window_minutes($window_minutes)
    {
        return is_numeric($window_minutes) && $window_minutes > 0 && $window_minutes <= 1440; // Max 24 hours
    }

    /**
     * Log database errors if any occurred.
     *
     * @param string $operation The operation that was performed.
     * @param string $contact The contact identifier.
     * @return void
     */
    private function log_db_error($operation, $contact)
    {
        if ($this->wpdb->last_error) {
            $this->logger->error(
                "Database error during $operation: " . $this->wpdb->last_error,
                $contact,
                null,
                get_current_user_id(),
                'db_error'
            );
        }
    }

    /**
     * Check if an OTP record is expired.
     *
     * @param object $otp_record The OTP record object.
     * @return bool
     */
    public function is_otp_expired($otp_record)
    {
        if (!$otp_record || !isset($otp_record->expires_at)) {
            return true;
        }

        return strtotime($otp_record->expires_at) < time();
    }

    /**
     * Get expired OTP records.
     *
     * @param int $limit Maximum number of records to return.
     * @return array
     */
    public function get_expired_otps($limit = 100)
    {
        $result = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE expires_at < NOW() AND status = %s LIMIT %d",
                self::STATUS_PENDING,
                $limit
            )
        );

        $expired_count = ($result === null) ? 0 : count($result);
        
        if ($expired_count > 0) {
            $this->logger->info("Found $expired_count expired OTP records", 'system', null, get_current_user_id());
        }

        return ($result === null) ? [] : $result;
    }
}
