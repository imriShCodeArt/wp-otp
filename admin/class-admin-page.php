<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

use WpOtp\WP_OTP_Admin_Fields;

/**
 * Class WP_OTP_Admin_Page
 *
 * Renders the WP OTP settings page in the admin.
 */
class WP_OTP_Admin_Page
{

    protected $admin_fields;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_init', [$this, 'register_settings']);
        $this->admin_fields = new WP_OTP_Admin_Fields();
    }

    /**
     * Register the menu page.
     */
    public function register_page()
    {
        $capability = apply_filters('wp_otp_admin_capability', 'manage_options');

        add_menu_page(
            __('WP OTP Settings', 'wp-otp'),
            __('WP OTP', 'wp-otp'),
            $capability,
            'wp-otp',
            [$this, 'render_page'],
            'dashicons-shield',
            65
        );
    }

    /**
     * Register settings and fields.
     */
    public function register_settings()
    {
        // Register the settings group
        register_setting(
            'wp_otp_settings_group',
            'wp_otp_settings',
            [$this, 'sanitize_settings']
        );

        // Register the section
        add_settings_section(
            'wp_otp_main',
            __('OTP Settings', 'wp-otp'),
            null,
            'wp-otp'
        );

        // Define your fields
        $fields = [
            'otp_channels' => ['label' => __('OTP Channels', 'wp-otp'), 'callback' => 'field_otp_channels',],
            'otp_cooldown' => ['label' => __('OTP Cooldown (seconds)', 'wp-otp'), 'callback' => 'field_otp_cooldown',],
            'otp_resend_limit' => ['label' => __('OTP Resend Limit', 'wp-otp'), 'callback' => 'field_otp_resend_limit',],
            'otp_resend_window' => ['label' => __('OTP Resend Window (minutes)', 'wp-otp'), 'callback' => 'field_otp_resend_window',],
            'otp_length' => ['label' => __('OTP Length', 'wp-otp'), 'callback' => 'field_otp_length',],
            'otp_expiry' => ['label' => __('OTP Expiry (minutes)', 'wp-otp'), 'callback' => 'field_otp_expiry',],
            'email_subject' => ['label' => __('Email Subject', 'wp-otp'), 'callback' => 'field_email_subject',],
            'email_body' => ['label' => __('Email Body', 'wp-otp'), 'callback' => 'field_email_body',],
            'sms_sender' => ['label' => __('SMS Sender Name', 'wp-otp'), 'callback' => 'field_sms_sender',],
            'sms_message' => ['label' => __('SMS Message Template', 'wp-otp'), 'callback' => 'field_sms_message',],
            'phone_only_auth' => ['label' => __('Enable Phone-only Authentication', 'wp-otp'), 'callback' => 'field_phone_only_auth',],
        ];

        foreach ($fields as $id => $field) {
            add_settings_field(
                $id,
                $field['label'],
                [$this->admin_fields, $field['callback']],
                'wp-otp',
                'wp_otp_main'
            );
        }
    }


    /**
     * Sanitize settings before saving.
     *
     * @param array $input
     * @return array
     */
    public function sanitize_settings($input)
    {
        $output = [];

        $output['otp_channels'] = isset($input['otp_channels']) && is_array($input['otp_channels'])
            ? array_map('sanitize_text_field', $input['otp_channels'])
            : [];

        $output['otp_cooldown'] = isset($input['otp_cooldown'])
            ? max(10, intval($input['otp_cooldown']))
            : 30;


        $output['otp_length'] = isset($input['otp_length'])
            ? max(4, min(10, intval($input['otp_length'])))
            : 6;

        $output['otp_expiry'] = isset($input['otp_expiry'])
            ? max(1, intval($input['otp_expiry']))
            : 5;

        $output['email_subject'] = isset($input['email_subject'])
            ? sanitize_text_field($input['email_subject'])
            : '';

        $output['email_body'] = isset($input['email_body'])
            ? sanitize_textarea_field($input['email_body'])
            : '';

        $output['sms_sender'] = isset($input['sms_sender'])
            ? sanitize_text_field($input['sms_sender'])
            : '';

        $output['sms_message'] = isset($input['sms_message'])
            ? sanitize_textarea_field($input['sms_message'])
            : '';

        $output['otp_resend_window'] = isset($input['otp_resend_window'])
            ? sanitize_textarea_field($input['otp_resend_window'])
            : '';

        $output['otp_resend_limit'] = isset($input['otp_resend_limit'])
            ? sanitize_textarea_field($input['otp_resend_limit'])
            : '';

        $output['phone_only_auth'] = isset($input['phone_only_auth']) && $input['phone_only_auth'] === '1' ? '1' : '0';

        return $output;
    }

    /**
     * Render the admin page.
     */
    public function render_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WP OTP Settings', 'wp-otp'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-otp&tab=settings"
                    class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Settings', 'wp-otp'); ?>
                </a>
                <a href="?page=wp-otp&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Logs', 'wp-otp'); ?>
                </a>
            </h2>

            <?php
            if ($active_tab === 'settings') {
                ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wp_otp_settings_group');
                    do_settings_sections('wp-otp');
                    submit_button();
                    ?>
                </form>
                <?php
            } elseif ($active_tab === 'logs') {
                $this->render_logs_tab();
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render Logs Tab.
     */
    public function render_logs_tab()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'otp_logs';
        $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");
        include WP_OTP_PATH . 'admin/views/logs-tab.php';
    ?>
    <?php
    }
}
