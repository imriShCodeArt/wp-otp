<?php

if (!defined('ABSPATH')) {
    exit;
}

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
    protected $delivery;

    public function __construct()
    {
        $this->code_generator = new WP_OTP_CodeGenerator();
        $this->repository = new WP_OTP_Repository();
        $this->delivery = new WP_OTP_Delivery_Email();
    }

    /**
     * Generate and send an OTP to the given email.
     *
     * @param string $email
     * @param int $length
     * @param int $expiry_minutes
     * @return bool
     */
    public function send_otp($email, $length = 6, $expiry_minutes = 5)
    {
        // Generate OTP
        $otp = $this->code_generator->generate($length);

        $hash = password_hash($otp, PASSWORD_DEFAULT);

        // Calculate expiry timestamp
        $expires_at = current_time('mysql', 1);
        $expires_at = date('Y-m-d H:i:s', strtotime("+$expiry_minutes minutes", strtotime($expires_at)));

        // Save OTP record
        $this->repository->save_otp($email, $hash, $expires_at);

        // Send the OTP
        return $this->delivery->send($email, $otp, $expiry_minutes);
    }

    /**
     * Verify the OTP entered by the user.
     *
     * @param string $email
     * @param string $input_otp
     * @param int $max_attempts
     * @return bool
     */
    public function verify_otp($email, $input_otp, $max_attempts = 3)
    {
        $row = $this->repository->get_otp_record($email);

        if (!$row) {
            return false;
        }

        if ($row->status !== 'pending') {
            return false;
        }

        if (strtotime($row->expires_at) < time()) {
            $this->repository->update_status($email, 'expired');
            return false;
        }

        if ($row->attempts >= $max_attempts) {
            return false;
        }

        $is_valid = password_verify($input_otp, $row->code_hash);

        if ($is_valid) {
            $this->repository->update_status($email, 'verified');
            return true;
        } else {
            $this->repository->increment_attempts($email);
            return false;
        }
    }
}
