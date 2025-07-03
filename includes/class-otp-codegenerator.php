<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_CodeGenerator
 *
 * Responsible for generating random OTP codes.
 */
class WP_OTP_CodeGenerator
{

    /**
     * Generate a random numeric OTP.
     *
     * @param int $length
     * @return string
     */
    public function generate($length = 6)
    {
        $digits = '0123456789';
        $otp = '';

        for ($i = 0; $i < $length; $i++) {
            $otp .= $digits[rand(0, strlen($digits) - 1)];
        }

        return $otp;
    }

    /**
     * Generate a random alphanumeric OTP.
     *
     * @param int $length
     * @return string
     */
    public function generate_alphanumeric($length = 6)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $otp = '';

        for ($i = 0; $i < $length; $i++) {
            $otp .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $otp;
    }
}
