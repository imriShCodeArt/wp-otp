<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

class WP_OTP_SMS_019_Client
{

    public function __construct($username, $password, $sender)
    {
        // Store credentials
    }

    public function send_sms($phone, $message)
    {
        // Call 019 API
        return true;
    }
}
