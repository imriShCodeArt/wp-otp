<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Manager
 *
 * Handles OTP generation, sending, and verification.
 *
 * @package WpOtp
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
     * @var WP_OTP_SMS_019_Client|null
     */
    protected $sms_client;

    /**
     * @var WP_OTP_Logger
     */
    protected $logger;

    /**
     * WP_OTP_Manager constructor.
     */
    public function __construct()
    {
        $this->code_generator = new WP_OTP_CodeGenerator();
        $this->repository = new WP_OTP_Repository();
        $this->email_delivery = new WP_OTP_Delivery_Email();
        // Uncomment when SMS client is implemented:
        // $this->sms_client = new WP_OTP_SMS_019_Client();
        $this->logger = new WP_OTP_Logger();
    }

    /**
     * Sends an OTP to a given contact (email or phone) through the specified channel.
     *
     * @param string $contact The email address or phone number.
     * @param string $channel Either "email" or "sms".
     * @param int $length The length of the OTP code to generate.
     *
     * @return array {
     *     @type bool   $success Whether the OTP was sent successfully.
     *     @type string $message User-friendly message.
     *     @type string $code    Machine-readable result code.
     * }
     */
    public function send_otp($contact, $channel = 'email', $length = 6)
    {
        $options = wp_otp_get_settings();
        $allowed_channels = $options['otp_channels'] ?? [];
        $expiry_minutes = (int) ($options['otp_expiry'] ?? 5);
        $resend_limit = (int) ($options['otp_resend_limit'] ?? 3);
        $resend_window = (int) ($options['otp_resend_window'] ?? 15);

        if (!in_array($channel, $allowed_channels)) {
            $this->logger->error("Channel $channel is not allowed.", $contact, $channel, get_current_user_id());
            return [
                'success' => false,
                'message' => sprintf(__('Channel %s is not supported.', 'wp-otp'), $channel),
                'code' => 'invalid_channel'
            ];
        }

        // Check resend limits
        $recent_count = $this->repository->count_recent_otps($contact, $resend_window);
        if ($recent_count >= $resend_limit) {
            $this->logger->warning("Resend limit reached for $channel.", $contact, $channel, get_current_user_id(), 'send_blocked');
            return [
                'success' => false,
                'message' => __('Too many OTP requests. Please wait before requesting another.', 'wp-otp'),
                'code' => 'resend_limit'
            ];
        }

        // Generate OTP
        $otp = $this->code_generator->generate($length);
        $hash = password_hash($otp, PASSWORD_DEFAULT);
        $expires_at = date('Y-m-d H:i:s', time() + ($expiry_minutes * 60));

        // Save to database
        $saved = $this->repository->save_otp($contact, $hash, $expires_at);
        if (!$saved) {
            $this->logger->error("Could not save OTP to DB.", $contact, $channel, get_current_user_id());
            return [
                'success' => false,
                'message' => __('Failed to generate OTP. Please try again.', 'wp-otp'),
                'code' => 'save_failed'
            ];
        }

        // Send OTP based on channel
        if ($channel === 'email') {
            $sent = $this->email_delivery->send($contact, $otp, $expiry_minutes);
            if ($sent) {
                $this->logger->info("OTP sent via email. Expires in $expiry_minutes minutes.", $contact, $channel, get_current_user_id());
                return [
                    'success' => true,
                    'message' => sprintf(__('OTP sent to your email. It will expire in %d minutes.', 'wp-otp'), $expiry_minutes),
                    'code' => 'email_sent'
                ];
            } else {
                $this->logger->error("Email delivery failed.", $contact, $channel, get_current_user_id());
                return [
                    'success' => false,
                    'message' => __('Failed to send OTP via email. Please try again.', 'wp-otp'),
                    'code' => 'email_failed'
                ];
            }
        } elseif ($channel === 'sms') {
            $sent = $this->sms_client->send_otp_sms($contact, $otp, $expiry_minutes);
            if ($sent) {
                $this->logger->info("OTP sent via SMS. Expires in $expiry_minutes minutes.", $contact, $channel, get_current_user_id());
                return [
                    'success' => true,
                    'message' => sprintf(__('OTP sent to your phone. It will expire in %d minutes.', 'wp-otp'), $expiry_minutes),
                    'code' => 'sms_sent'
                ];
            } else {
                $this->logger->error("SMS delivery failed.", $contact, $channel, get_current_user_id());
                return [
                    'success' => false,
                    'message' => __('Failed to send OTP via SMS. Please try again.', 'wp-otp'),
                    'code' => 'sms_failed'
                ];
            }
        } else {
            $this->logger->error("Unsupported channel: $channel.", $contact, $channel, get_current_user_id());
            return [
                'success' => false,
                'message' => __('Unsupported channel.', 'wp-otp'),
                'code' => 'invalid_channel'
            ];
        }
    }

    /**
     * Verifies an OTP entered by the user.
     *
     * @param string $contact The email or phone number to which OTP was sent.
     * @param string $input_otp The OTP entered by the user.
     * @param int $max_attempts Maximum allowed verification attempts.
     *
     * @return array {
     *     @type bool   $success Whether the OTP verification succeeded.
     *     @type string $message User-friendly message.
     *     @type string $code    Machine-readable result code.
     * }
     */
    public function verify_otp($contact, $input_otp, $max_attempts = 3)
    {
        $row = $this->repository->get_otp_record($contact);

        if (!$row) {
            $this->logger->warning("No OTP record found.", $contact, null, get_current_user_id(), 'verify_failed');
            return [
                'success' => false,
                'message' => __('No OTP found for this contact.', 'wp-otp'),
                'code' => 'no_otp_record'
            ];
        }

        if ($row->status !== WP_OTP_Repository::STATUS_PENDING) {
            $this->logger->warning("OTP already {$row->status}.", $contact, null, get_current_user_id(), 'verify_failed');
            return [
                'success' => false,
                'message' => sprintf(__('This OTP has already been %s.', 'wp-otp'), $row->status),
                'code' => 'otp_not_pending'
            ];
        }

        if (strtotime($row->expires_at) < time()) {
            $this->repository->update_status($contact, WP_OTP_Repository::STATUS_EXPIRED);
            $this->logger->warning("OTP expired at {$row->expires_at}.", $contact, null, get_current_user_id(), 'verify_failed');
            return [
                'success' => false,
                'message' => __('This OTP has expired. Please request a new one.', 'wp-otp'),
                'code' => 'otp_expired'
            ];
        }

        if ($row->attempts >= $max_attempts) {
            $this->repository->update_status($contact, WP_OTP_Repository::STATUS_EXPIRED);
            $this->logger->warning("Maximum attempts reached. OTP expired.", $contact, null, get_current_user_id(), 'verify_failed');
            return [
                'success' => false,
                'message' => __('Maximum attempts reached. Your OTP is now expired. Please request a new one.', 'wp-otp'),
                'code' => 'max_attempts'
            ];
        }

        $is_valid = password_verify($input_otp, $row->code_hash);

        if ($is_valid) {
            $this->repository->update_status($contact, WP_OTP_Repository::STATUS_VERIFIED);
            $this->logger->info("OTP verified successfully.", $contact, null, get_current_user_id());
            return [
                'success' => true,
                'message' => __('OTP verified successfully.', 'wp-otp'),
                'code' => 'otp_verified'
            ];
        } else {
            $this->repository->increment_attempts($contact);
            $this->logger->warning("Incorrect OTP entered.", $contact, null, get_current_user_id(), 'verify_failed');
            return [
                'success' => false,
                'message' => __('Incorrect OTP. Please try again.', 'wp-otp'),
                'code' => 'otp_incorrect'
            ];
        }
    }
}
