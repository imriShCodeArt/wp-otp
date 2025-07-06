<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Admin_Ajax
 *
 * Handles AJAX requests for admin and frontend OTP logic.
 */
class WP_OTP_Admin_Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_wp_otp_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_nopriv_wp_otp_send_otp', [$this, 'wp_otp_send_otp']);
        add_action('wp_ajax_wp_otp_send_otp', [$this, 'wp_otp_send_otp']);
        add_action('wp_ajax_nopriv_wp_otp_verify_otp', [$this, 'wp_otp_verify_otp']);
        add_action('wp_ajax_wp_otp_verify_otp', [$this, 'wp_otp_verify_otp']);
    }

    /**
     * Handle OTP send AJAX request.
     *
     * @return void
     */
    public function wp_otp_send_otp()
    {
        check_ajax_referer('wp_otp_nonce', 'nonce');

        $contact = sanitize_text_field($_POST['contact'] ?? '');
        $channel = sanitize_text_field($_POST['channel'] ?? '');

        if (empty($contact)) {
            wp_send_json_error([
                'message' => __('Contact information is required.', 'wp-otp'),
                'code' => 'missing_contact'
            ]);
        }

        $manager = new \WpOtp\WP_OTP_Manager();
        $result = $manager->send_otp($contact, $channel);

        if (!empty($result['success'])) {
            wp_send_json_success([
                'message' => $result['message'] ?? __('OTP sent successfully.', 'wp-otp'),
                'code' => $result['code'] ?? ''
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'] ?? __('Failed to send OTP.', 'wp-otp'),
                'code' => $result['code'] ?? 'send_failed'
            ]);
        }
    }

    /**
     * Handle OTP verification via AJAX.
     *
     * @return void
     */
    public function wp_otp_verify_otp()
    {
        check_ajax_referer('wp_otp_nonce', 'nonce');

        $contact = sanitize_text_field($_POST['contact'] ?? '');
        $otp = sanitize_text_field($_POST['otp'] ?? '');

        if (empty($contact) || empty($otp)) {
            wp_send_json_error([
                'message' => __('Both contact and OTP are required.', 'wp-otp'),
                'code' => 'missing_fields'
            ]);
        }

        $manager = new \WpOtp\WP_OTP_Manager();
        $result = $manager->verify_otp($contact, $otp);

        if (!empty($result['success'])) {
            wp_send_json_success([
                'message' => $result['message'] ?? __('OTP verified successfully.', 'wp-otp'),
                'code' => $result['code'] ?? ''
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'] ?? __('Invalid or expired OTP.', 'wp-otp'),
                'code' => $result['code'] ?? 'verify_failed'
            ]);
        }
    }

    /**
     * Save plugin settings via AJAX.
     *
     * @return void
     */
    public function save_settings()
    {
        check_ajax_referer('wp_otp_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'wp-otp')
            ]);
        }

        if (empty($_POST['data']) || !is_array($_POST['data'])) {
            wp_send_json_error([
                'message' => __('Invalid request data.', 'wp-otp')
            ]);
        }

        $input = wp_unslash($_POST['data']);
        $admin_page = new WP_OTP_Admin_Page();
        $sanitized = $admin_page->sanitize_settings($input);

        update_option('wp_otp_settings', $sanitized);

        wp_send_json_success([
            'message' => __('Settings saved successfully.', 'wp-otp')
        ]);
    }
}
