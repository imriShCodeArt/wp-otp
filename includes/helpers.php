<?php

if (!defined('ABSPATH')) {
    exit;
}

function wp_otp_sanitize_phone($phone)
{
    return preg_replace('/\D+/', '', $phone);
}
