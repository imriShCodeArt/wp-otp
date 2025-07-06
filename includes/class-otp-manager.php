<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Manager
 *
 * Handles OTP generation, sending, and verification.
 */
class WP_OTP_Manager
{

    /**
     * @var WP_OTP_CodeGenerator
     */
    protected $code_generator;

    /**
     * @var WP_OTP_Repository
     */
    protected $repository;

    /**
     * @var WP_OTP_Delivery_Email
     */
    protected $email_delivery;

    /**
     * @var WP_OTP_SMS_019_Client
     */
    protected $sms_client;

    /**
     * @var WP_OTP_Logger
     */
    protected $logger;

    public function __construct()
    {
        $this->code_generator = new WP_OTP_CodeGenerator();
        $this->repository = new WP_OTP_Repository();
        $this->email_delivery = new WP_OTP_Delivery_Email();
        // $this->sms_client = new WP_OTP_SMS_019_Client();
        $this->logger = new WP_OTP_Logger();
    }

    /**
     * Send an OTP code to email or phone.
     *
     * @param string $contact Email or phone number.
     * @param string $channel "email" or "sms"
     * @param int $length
     * @return bool
     */
    public function send_otp($contact, $channel = 'email', $length = 6)
    {
        $options = wp_otp_get_settings();
        $allowed_channels = $options['otp_channels'] ?? [];
        $expiry_minutes = isset($options['otp_expiry']) ? (int) $options['otp_expiry'] : 5;
        $resend_limit = isset($options['otp_resend_limit']) ? (int) $options['otp_resend_limit'] : 3;
        $resend_window = isset($options['otp_resend_window']) ? (int) $options['otp_resend_window'] : 15;

        if (!in_array($channel, $allowed_channels, true)) {
            $this->logger->log('send_failed', $contact, "Channel $channel is not allowed.");
            return false;
        }

        $recent_count = $this->repository->count_recent_otps($contact, $resend_window);

        if ($recent_count >= $resend_limit) {
            $this->logger->log('send_blocked', $contact, "Resend limit reached for $channel in last $resend_window minutes.");
            return false;
        }

        $otp = $this->code_generator->generate($length);
        $hash = password_hash($otp, PASSWORD_DEFAULT);
        $expires_at = date('Y-m-d H:i:s', strtotime("+$expiry_minutes minutes", current_time('timestamp', 1)));

        $saved_id = $this->repository->save_otp($contact, $hash, $expires_at);

        if (!$saved_id) {
            $this->logger->log('send_failed', $contact, "Could not save OTP to DB.");
            return false;
        }

        if ($channel === 'email') {
            $result = $this->email_delivery->send($contact, $otp, $expiry_minutes);

            if ($result) {
                $this->logger->log('send_success', $contact, "OTP sent via email. Expires in $expiry_minutes minutes.");
            } else {
                $this->logger->log('send_failed', $contact, "Email delivery failed.");
            }

            return $result;
        }

        if ($channel === 'sms') {
            $result = $this->sms_client->send_otp_sms($contact, $otp, $expiry_minutes);

            if ($result) {
                $this->logger->log('send_success', $contact, "OTP sent via SMS. Expires in $expiry_minutes minutes.");
            } else {
                $this->logger->log('send_failed', $contact, "SMS delivery failed.");
            }

            return $result;
        }

        $this->logger->log('send_failed', $contact, "Unsupported channel: $channel.");
        return false;
    }

    /**
     * Verify the OTP entered by the user.
     *
     * @param string $contact
     * @param string $input_otp
     * @param int $max_attempts
     * @return bool
     */
    public function verify_otp($contact, $input_otp, $max_attempts = 3)
    {
        $row = $this->repository->get_otp_record($contact);

        if (!$row) {
            $this->logger->log('verify_failed', $contact, "No OTP record found.");
            return false;
        }

        if ($row->status !== 'pending') {
            $this->logger->log('verify_failed', $contact, "OTP already {$row->status}.");
            return false;
        }

        if (strtotime($row->expires_at) < time()) {
            $this->repository->update_status($contact, 'expired');
            $this->logger->log('verify_failed', $contact, "OTP expired at {$row->expires_at}.");
            return false;
        }

        if ($row->attempts >= $max_attempts) {
            $this->logger->log('verify_failed', $contact, "Maximum attempts reached.");
            return false;
        }

        $is_valid = password_verify($input_otp, $row->code_hash);

        if ($is_valid) {
            $this->repository->update_status($contact, 'verified');
            $this->logger->log('verify_success', $contact, "OTP verified successfully.");
            return true;
        } else {
            $this->repository->increment_attempts($contact);
            $this->logger->log('verify_failed', $contact, "Incorrect OTP entered.");
            return false;
        }
    }
}
