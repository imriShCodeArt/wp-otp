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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        $this->admin_fields = new WP_OTP_Admin_Fields();
    }

    public function enqueue_admin_scripts($hook)
    {
        // Check page to load only on plugin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-otp') {
            return;
        }

        wp_enqueue_script(
            'wp-otp-admin',
            WP_OTP_URL . 'admin/assets/js/wp-otp-admin.js',
            ['jquery'],
            WP_OTP_VERSION,
            true
        );
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
            __('SMS Settings (019 API)', 'wp-otp'),
            null,
            'wp-otp-sms'
        );

        $sms_fields = [
            'sms_sender' => ['label' => __('SMS Sender Name', 'wp-otp'), 'callback' => 'field_sms_sender'],
            'sms_message' => ['label' => __('SMS Message Template', 'wp-otp'), 'callback' => 'field_sms_message'],
            'sms_username' => ['label' => __('SMS API Username', 'wp-otp'), 'callback' => 'field_sms_username'],
            'sms_password' => ['label' => __('SMS API Password', 'wp-otp'), 'callback' => 'field_sms_password'],
            'sms_access_token' => ['label' => __('SMS API Access Token', 'wp-otp'), 'callback' => 'field_sms_access_token'],
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
            'phone_only_auth' => __('Use OTP Only for Authentication', 'wp-otp'),
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
            ? max(1, intval($input['otp_resend_window']))
            : 15;

        $output['otp_resend_limit'] = isset($input['otp_resend_limit'])
            ? max(1, intval($input['otp_resend_limit']))
            : 3;

        $output['phone_only_auth'] = isset($input['phone_only_auth']) && $input['phone_only_auth'] === '1' ? '1' : '0';

        // New 019 API fields:
        $output['sms_username'] = isset($input['sms_username'])
            ? sanitize_text_field($input['sms_username'])
            : '';

        $output['sms_password'] = isset($input['sms_password'])
            ? sanitize_text_field($input['sms_password'])
            : '';

        $output['sms_access_token'] = isset($input['sms_access_token'])
            ? sanitize_text_field($input['sms_access_token'])
            : '';

        return $output;
    }

    /**
     * Render the admin settings page.
     */
    public function render_page()
    {
        // Check for CSV download first and handle immediately
        if (
            isset($_GET['tab'], $_GET['download_csv']) &&
            $_GET['tab'] === 'logs'
        ) {
            // Ensure no previous output breaks headers
            if (ob_get_length()) {
                ob_end_clean();
            }
            $this->download_logs_csv();
            exit;
        }

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
     */
    public function render_logs_tab()
    {
        // Get logger instance
        $logger = new \WpOtp\WP_OTP_Logger();
        
        // Build filter arguments
        $filter_args = [
            'limit' => 100,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];

        // Date filters
        if (!empty($_GET['from_date'])) {
            $from_date = sanitize_text_field($_GET['from_date']) . ' 00:00:00';
            $filter_args['from_date'] = $from_date;
        }

        if (!empty($_GET['to_date'])) {
            $to_date = sanitize_text_field($_GET['to_date']) . ' 23:59:59';
            $filter_args['to_date'] = $to_date;
        }

        // Contact filter
        if (!empty($_GET['contact'])) {
            $filter_args['contact'] = sanitize_text_field($_GET['contact']);
        }

        // Channel filter
        if (!empty($_GET['channel'])) {
            $filter_args['channel'] = sanitize_text_field($_GET['channel']);
        }

        // Event type filter
        if (!empty($_GET['event_type'])) {
            $event_types = is_array($_GET['event_type']) ? $_GET['event_type'] : [$_GET['event_type']];
            $event_types = array_map('sanitize_text_field', $event_types);
            $filter_args['event_types'] = $event_types;
        }

        // Subject filter
        if (!empty($_GET['subject'])) {
            $subjects = is_array($_GET['subject']) ? $_GET['subject'] : [$_GET['subject']];
            $subjects = array_map('sanitize_text_field', $subjects);
            $filter_args['subjects'] = $subjects;
        }

        // Get logs using the logger's get_logs method
        $logs = $logger->get_logs($filter_args);

        // Get statistics for display
        $stats = $logger->get_statistics();

        include WP_OTP_PATH . 'admin/views/logs-tab.php';
    }

    /**
     * Download logs CSV.
     */
    public function download_logs_csv()
    {
        if (headers_sent()) {
            wp_die(__('Cannot export CSV: headers already sent.', 'wp-otp'));
        }

        // Get logger instance
        $logger = new \WpOtp\WP_OTP_Logger();
        
        // Build filter arguments
        $filter_args = [
            'limit' => 10000, // Higher limit for CSV export
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];

        // Date filters
        if (!empty($_GET['from_date'])) {
            $from_date = sanitize_text_field($_GET['from_date']) . ' 00:00:00';
            $filter_args['from_date'] = $from_date;
        }

        if (!empty($_GET['to_date'])) {
            $to_date = sanitize_text_field($_GET['to_date']) . ' 23:59:59';
            $filter_args['to_date'] = $to_date;
        }

        // Contact filter
        if (!empty($_GET['contact'])) {
            $filter_args['contact'] = sanitize_text_field($_GET['contact']);
        }

        // Channel filter
        if (!empty($_GET['channel'])) {
            $filter_args['channel'] = sanitize_text_field($_GET['channel']);
        }

        // Event type filter
        if (!empty($_GET['event_type'])) {
            $event_types = is_array($_GET['event_type']) ? $_GET['event_type'] : [$_GET['event_type']];
            $event_types = array_map('sanitize_text_field', $event_types);
            $filter_args['event_types'] = $event_types;
        }

        // Subject filter
        if (!empty($_GET['subject'])) {
            $subjects = is_array($_GET['subject']) ? $_GET['subject'] : [$_GET['subject']];
            $subjects = array_map('sanitize_text_field', $subjects);
            $filter_args['subjects'] = $subjects;
        }

        // Get logs using the logger's get_logs method
        $logs = $logger->get_logs($filter_args);

        if (empty($logs)) {
            wp_die(__('No logs found for export.', 'wp-otp'));
        }

        // Increase time limit for large exports
        if (!ini_get('safe_mode')) {
            set_time_limit(300);
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=otp_logs.csv');

        $output = fopen('php://output', 'w');

        // CSV header
        fputcsv($output, ['ID', 'Event Type', 'Subject', 'Contact', 'Channel', 'User ID', 'Message', 'Created At']);

        foreach ($logs as $log) {
            fputcsv($output, [
                $log->id,
                $log->event_type,
                $log->subject ?? 'N/A',
                $log->contact,
                $log->channel,
                $log->user_id,
                $log->message,
                $log->created_at,
            ]);
        }

        fclose($output);
        exit;
    }


}
