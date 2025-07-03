<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_OTP_Admin_Ajax
{

    public function __construct()
    {
        add_action('wp_ajax_wp_otp_test_sms', [$this, 'handle_test_sms']);
    }

    public function handle_test_sms()
    {
        // Handle admin AJAX request
        wp_send_json_success(['message' => 'SMS sent!']);
    }
}

new WP_OTP_Admin_Ajax();
