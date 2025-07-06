<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Logger
 *
 * Handles inserting logs into the wp_otp_logs table.
 */
class WP_OTP_Logger
{
    /**
     * @var string The full table name for logs.
     */
    protected $table_name;

    /**
     * WP_OTP_Logger constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'otp_logs';
    }

    /**
     * Log an event to the logs table.
     *
     * @param string      $event_type Event type (e.g. send, verify, etc.)
     * @param string      $contact    The contact info (email or phone).
     * @param string|null $message    Optional log message.
     * @param string|null $channel    Channel used (email|sms).
     * @param int|null    $user_id    Optional user ID associated with the event.
     *
     * @return void
     */
    public function log($event_type, $contact, $message, $channel = null, $user_id = null)
    {
        global $wpdb;

        $wpdb->insert(
            $this->table_name,
            [
                'event_type' => $event_type,
                'contact' => $contact,
                'message' => $message,
                'channel' => $channel,
                'user_id' => $user_id,
                'created_at' => current_time('mysql', 1),
            ],
            [
                '%s',
                '%s',
                '%s',
                $channel !== null ? '%s' : null,
                $user_id !== null ? '%d' : null,
                '%s',
            ]
        );
    }

}
