<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

class WP_OTP_Shortcodes
{
    protected $manager;

    public function __construct()
    {
        $this->manager = new WP_OTP_Manager();

        add_shortcode('wp_otp_request', [$this, 'render_request_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_wp_otp_request', [$this, 'handle_otp_request']);
        add_action('wp_ajax_nopriv_wp_otp_request', [$this, 'handle_otp_request']);
    }

    public function enqueue_scripts()
    {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (has_shortcode($post->post_content, 'wp_otp_request')) {
            wp_enqueue_style(
                'wp-otp-bootstrap',
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
                [],
                '5.3.3'
            );

            wp_enqueue_style(
                'wp-otp-frontend-style',
                WP_OTP_URL . 'assets/css/wp-otp-frontend.css',
                [],
                WP_OTP_VERSION
            );

            wp_enqueue_script(
                'wp-otp-frontend',
                WP_OTP_URL . 'assets/js/wp-otp-frontend.js',
                ['jquery'],
                WP_OTP_VERSION,
                true
            );

            wp_localize_script(
                'wp-otp-frontend',
                'wpOtpFrontend',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wp_otp_nonce'),
                    'cooldown' => wp_otp_get_settings()['otp_cooldown'] ?? 30,
                ]
            );
        }
    }

    public function render_request_form()
    {
        $options = wp_otp_get_settings();
        $channels = $options['otp_channels'] ?? [];

        ob_start();

        $template_path = WP_OTP_PATH . 'templates/shortcode-request-form/index.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p>' . esc_html__('OTP request form template not found.', 'wp-otp') . '</p>';
        }

        return ob_get_clean();
    }

    public function handle_otp_request()
    {
        check_ajax_referer('wp_otp_nonce', 'nonce');

        $contact = sanitize_text_field($_POST['contact'] ?? '');
        $channel = sanitize_text_field($_POST['channel'] ?? 'email');

        if (empty($contact)) {
            wp_send_json([
                'success' => false,
                'message' => __('Please enter a valid email or phone number.', 'wp-otp'),
                'code' => 'missing_contact'
            ]);
        }

        $result = $this->manager->send_otp($contact, $channel);

        wp_send_json($result);
    }
}
