<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Delivery_Email
 *
 * Handles sending OTPs via email.
 */
class WP_OTP_Delivery_Email
{

    protected $subject_template;
    protected $message_template;

    public function __construct()
    {
        $this->load_templates();
    }

    /**
     * Load templates from WP options or defaults.
     */
    protected function load_templates()
    {
        $this->subject_template = get_option(
            'wp_otp_email_subject',
            __('Your OTP Code', 'wp-otp')
        );

        $this->message_template = get_option(
            'wp_otp_email_message',
            __('Your OTP code is {OTP}. It will expire in {minutes} minutes.', 'wp-otp')
        );
    }

    /**
     * Send OTP email.
     *
     * @param string $email Recipient email.
     * @param string $otp The OTP code.
     * @param int $expiry_minutes Minutes until expiration.
     * @return bool
     */
    public function send($email, $otp, $expiry_minutes)
    {
        $subject = str_replace(
            '{OTP}',
            $otp,
            $this->subject_template
        );

        $message = str_replace(
            ['{OTP}', '{minutes}'],
            [$otp, $expiry_minutes],
            $this->message_template
        );

        /**
         * Filter email subject and message before sending.
         */
        $subject = apply_filters('wp_otp_email_subject', $subject, $email, $otp);
        $message = apply_filters('wp_otp_email_message', $message, $email, $otp, $expiry_minutes);

        return wp_mail($email, $subject, $message);
    }
}
