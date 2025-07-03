<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Admin_Ajax
 *
 * Handles AJAX requests for admin settings.
 */
class WP_OTP_Admin_Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_wp_otp_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_nopriv_wp_otp_send_otp', [$this, 'wp_otp_send_otp']);
        add_action('wp_ajax_wp_otp_send_otp', [$this, 'wp_otp_send_otp']); // Optional for logged-in users
        add_action('wp_ajax_nopriv_wp_otp_verify_otp', [$this, 'wp_otp_verify_otp']);
        add_action('wp_ajax_wp_otp_verify_otp', [$this, 'wp_otp_verify_otp']);

    }


    function wp_otp_send_otp()
    {
        check_ajax_referer('wp_otp_nonce', 'nonce');

        $contact = sanitize_text_field($_POST['contact'] ?? '');
        $channel = sanitize_text_field($_POST['channel'] ?? 'email');

        $manager = new \WpOtp\WP_OTP_Manager();

        $result = $manager->send_otp($contact, $channel);

        if ($result) {
            wp_send_json_success(['message' => 'OTP sent successfully.']);
        } else {
            wp_send_json_error(['message' => 'Failed to send OTP.']);
        }
    }


    /**
     * Save settings via AJAX.
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

        // Sanitize via the same method as settings API
        $admin_page = new WP_OTP_Admin_Page();
        $sanitized = $admin_page->sanitize_settings($input);

        update_option('wp_otp_settings', $sanitized);

        wp_send_json_success([
            'message' => __('Settings saved successfully.', 'wp-otp')
        ]);
    }

    public function wp_otp_verify_otp()
    {
        check_ajax_referer('wp_otp_nonce', 'nonce');

        $contact = sanitize_text_field($_POST['contact'] ?? '');
        $otp = sanitize_text_field($_POST['otp'] ?? '');
        $channel = sanitize_text_field($_POST['channel'] ?? 'email');

        $manager = new \WpOtp\WP_OTP_Manager();
        $verified = $manager->verify_otp($contact, $otp);

        if ($verified) {
            wp_send_json_success(['message' => 'OTP verified successfully.']);
        } else {
            wp_send_json_error(['message' => 'Invalid or expired OTP.']);
        }
    }

}
