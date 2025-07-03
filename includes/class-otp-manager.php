<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

class WP_OTP_Manager
{

    public function __construct()
    {
        // Hook into plugin logic
    }

    public function generate_otp($phone, $length = 6)
    {
        // Generate random code
        return rand(100000, 999999);
    }

    public function store_otp($phone, $otp, $expires_in_minutes)
    {
        // Save OTP hashed to DB
    }

    public function verify_otp($phone, $input_otp)
    {
        // Check OTP validity
        return true;
    }
}
