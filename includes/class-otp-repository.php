<?php

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
     * Save or update an OTP record.
     *
     * @param string $contact
     * @param string $hash
     * @param string $expires_at
     * @return void
     */
    public function save_otp($contact, $hash, $expires_at)
    {
        global $wpdb;

        $existing = $this->get_otp_record($contact);

        if ($existing) {
            $wpdb->update(
                $this->table_name,
                [
                    'code_hash' => $hash,
                    'expires_at' => $expires_at,
                    'attempts' => 0,
                    'status' => 'pending',
                ],
                ['contact' => $contact],
                ['%s', '%s', '%d', '%s'],
                ['%s']
            );
        } else {
            $wpdb->insert(
                $this->table_name,
                [
                    'contact' => $contact,
                    'code_hash' => $hash,
                    'expires_at' => $expires_at,
                    'attempts' => 0,
                    'status' => 'pending',
                ],
                ['%s', '%s', '%s', '%d', '%s']
            );
        }
    }

    /**
     * Get OTP record for a contact.
     *
     * @param string $contact
     * @return object|null
     */
    public function get_otp_record($contact)
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE contact = %s",
            $contact
        ));
    }

    /**
     * Update OTP status.
     *
     * @param string $contact
     * @param string $status
     * @return void
     */
    public function update_status($contact, $status)
    {
        global $wpdb;

        $wpdb->update(
            $this->table_name,
            ['status' => $status],
            ['contact' => $contact],
            ['%s'],
            ['%s']
        );
    }

    /**
     * Increment attempts counter.
     *
     * @param string $contact
     * @return void
     */
    public function increment_attempts($contact)
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE $this->table_name SET attempts = attempts + 1 WHERE contact = %s",
            $contact
        ));
    }
}
