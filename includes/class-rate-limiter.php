<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_OTP_Rate_Limiter
{

    public function __construct()
    {
        // Init limiter
    }

    public function check_phone_limit($phone)
    {
        return true;
    }

    public function check_ip_limit($ip)
    {
        return true;
    }

    public function increment_phone_count($phone)
    {
        // Increment phone counter
    }

    public function increment_ip_count($ip)
    {
        // Increment IP counter
    }
}
