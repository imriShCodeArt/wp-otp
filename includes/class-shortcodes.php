<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

class WP_OTP_Shortcodes
{
    public function __construct()
    {
        add_shortcode('wp_otp_request', [$this, 'render_request_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts()
    {
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

    public function render_request_form()
    {
        $options = wp_otp_get_settings();
        $channels = $options['otp_channels'] ?? [];

        ob_start();

        $template_path = WP_OTP_PATH . 'templates/shortcode-request-form.php';
        if (file_exists($template_path)) {
            include $template_path;
        }

        return ob_get_clean();
    }
}
