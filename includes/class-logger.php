<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

class WP_OTP_Logger
{

    protected $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'otp_logs';
    }

    public function log($event_type, $contact, $message)
    {
        global $wpdb;

        $wpdb->insert(
            $this->table_name,
            [
                'event_type' => $event_type,
                'contact' => $contact,
                'message' => $message,
                'created_at' => current_time('mysql', 1),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );
    }
}
