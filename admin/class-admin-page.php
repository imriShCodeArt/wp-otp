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
     * Register the admin menu page.
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
     * Register settings and fields for each tab.
     */
    public function register_settings()
    {
        register_setting(
            'wp_otp_settings_group',
            'wp_otp_settings',
            [$this, 'sanitize_settings']
        );

        // SETTINGS TAB
        add_settings_section(
            'wp_otp_main_settings',
            __('General Settings', 'wp-otp'),
            null,
            'wp-otp-settings'
        );

        $settings_fields = [
            'otp_channels' => 'field_otp_channels',
            'otp_cooldown' => 'field_otp_cooldown',
            'otp_resend_limit' => 'field_otp_resend_limit',
            'otp_resend_window' => 'field_otp_resend_window',
            'otp_length' => 'field_otp_length',
            'otp_expiry' => 'field_otp_expiry',
            'phone_only_auth' => 'field_phone_only_auth',
        ];

        foreach ($settings_fields as $id => $callback) {
            add_settings_field(
                $id,
                $this->get_field_label($id),
                [$this->admin_fields, $callback],
                'wp-otp-settings',
                'wp_otp_main_settings'
            );
        }

        // EMAIL TAB
        add_settings_section(
            'wp_otp_email_settings',
            __('Email Settings', 'wp-otp'),
            null,
            'wp-otp-email'
        );

        $email_fields = [
            'email_subject' => 'field_email_subject',
            'email_body' => 'field_email_body',
        ];

        foreach ($email_fields as $id => $callback) {
            add_settings_field(
                $id,
                $this->get_field_label($id),
                [$this->admin_fields, $callback],
                'wp-otp-email',
                'wp_otp_email_settings'
            );
        }

        // SMS TAB
        add_settings_section(
            'wp_otp_sms_settings',
            __('SMS Settings', 'wp-otp'),
            null,
            'wp-otp-sms'
        );

        $sms_fields = [
            'sms_sender' => ['label' => __('SMS Sender Name', 'wp-otp'), 'callback' => 'field_sms_sender'],
            'sms_message' => ['label' => __('SMS Message Template', 'wp-otp'), 'callback' => 'field_sms_message'],
            'sms_api_key' => ['label' => __('SMS API Key', 'wp-otp'), 'callback' => 'field_sms_api_key'],
            'sms_api_secret' => ['label' => __('SMS API Secret', 'wp-otp'), 'callback' => 'field_sms_api_secret'],
        ];

        foreach ($sms_fields as $id => $field) {
            add_settings_field(
                $id,
                $field['label'],
                [$this->admin_fields, $field['callback']],
                'wp-otp-sms',
                'wp_otp_sms_settings'
            );

        }

    }

    /**
     * Get translated field label.
     *
     * @param string $field_id
     * @return string
     */
    protected function get_field_label($field_id)
    {
        $labels = [
            'otp_channels' => __('OTP Channels', 'wp-otp'),
            'otp_cooldown' => __('OTP Cooldown (seconds)', 'wp-otp'),
            'otp_resend_limit' => __('OTP Resend Limit', 'wp-otp'),
            'otp_resend_window' => __('OTP Resend Window (minutes)', 'wp-otp'),
            'otp_length' => __('OTP Length', 'wp-otp'),
            'otp_expiry' => __('OTP Expiry (minutes)', 'wp-otp'),
            'phone_only_auth' => __('Enable Phone-only Authentication', 'wp-otp'),
            'email_subject' => __('Email Subject', 'wp-otp'),
            'email_body' => __('Email Body', 'wp-otp'),
            'sms_sender' => __('SMS Sender Name', 'wp-otp'),
            'sms_message' => __('SMS Message Template', 'wp-otp'),
        ];

        return $labels[$field_id] ?? $field_id;
    }

    /**
     * Sanitize plugin settings.
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
            ? sanitize_text_field($input['otp_resend_window'])
            : '';

        $output['otp_resend_limit'] = isset($input['otp_resend_limit'])
            ? sanitize_text_field($input['otp_resend_limit'])
            : '';

        $output['phone_only_auth'] = isset($input['phone_only_auth']) && $input['phone_only_auth'] === '1' ? '1' : '0';

        $output['sms_api_key'] = isset($input['sms_api_key'])
            ? sanitize_text_field($input['sms_api_key'])
            : '';

        $output['sms_api_secret'] = isset($input['sms_api_secret'])
            ? sanitize_text_field($input['sms_api_secret'])
            : '';


        return $output;
    }

    /**
     * Render the admin settings page.
     */
    public function render_page()
    {
        $active_tab = $_GET['tab'] ?? 'settings';

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WP OTP Settings', 'wp-otp'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-otp&tab=settings"
                    class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Settings', 'wp-otp'); ?>
                </a>
                <a href="?page=wp-otp&tab=email" class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Email', 'wp-otp'); ?>
                </a>
                <a href="?page=wp-otp&tab=sms" class="nav-tab <?php echo $active_tab === 'sms' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('SMS', 'wp-otp'); ?>
                </a>
                <a href="?page=wp-otp&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Logs', 'wp-otp'); ?>
                </a>
            </h2>

            <?php
            if (in_array($active_tab, ['settings', 'email', 'sms'], true)):
                ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wp_otp_settings_group');
                    do_settings_sections('wp-otp-' . $active_tab);
                    submit_button();
                    ?>
                </form>
                <?php
            elseif ($active_tab === 'logs'):
                $this->render_logs_tab();
            endif;
            ?>
        </div>
        <?php
    }

    /**
     * Render Logs Tab.
     *
     * @return void
     */
    public function render_logs_tab()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'otp_logs';
        $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");

        include WP_OTP_PATH . 'admin/views/logs-tab.php';
    }
}
