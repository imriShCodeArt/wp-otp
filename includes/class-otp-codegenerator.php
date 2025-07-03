<?php

namespace WpOtp;

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
     * @throws \Exception
     */
    public function generate($length = 6)
    {
        $digits = '0123456789';
        return $this->generate_from_charset($digits, $length);
    }

    /**
     * Generate a random alphanumeric OTP.
     *
     * @param int $length
     * @return string
     * @throws \Exception
     */
    public function generate_alphanumeric($length = 6)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return $this->generate_from_charset($characters, $length);
    }

    /**
     * Generate a random OTP from any character set.
     *
     * @param string $charset
     * @param int $length
     * @return string
     * @throws \Exception
     */
    protected function generate_from_charset($charset, $length)
    {
        $otp = '';
        $max_index = strlen($charset) - 1;

        for ($i = 0; $i < $length; $i++) {
            $otp .= $charset[random_int(0, $max_index)];
        }

        return $otp;
    }
}
