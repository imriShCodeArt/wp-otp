<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_OTP_Admin_Page
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_plugin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_plugin_menu()
    {
        add_menu_page(
            __('WP OTP Settings', 'wp-otp'),
            __('WP OTP', 'wp-otp'),
            'manage_options',
            'wp-otp',
            [$this, 'settings_page'],
            'dashicons-lock'
        );
    }

    public function register_settings()
    {
        register_setting('wp_otp_settings_group', 'wp_otp_settings');
    }

    public function settings_page()
    {
        include WP_OTP_PATH . 'admin/views/settings-page.php';
    }
}

new WP_OTP_Admin_Page();
