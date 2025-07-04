<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Admin_Page
 *
 * Renders the WP OTP settings page in the admin.
 */
class WP_OTP_Admin_Page
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_init', [$this, 'register_settings']);
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

        add_settings_field(
            'otp_channels',
            __('OTP Channels', 'wp-otp'),
            [$this, 'field_otp_channels'],
            'wp-otp',
            'wp_otp_main'
        );

        add_settings_field(
            'otp_cooldown',
            __('OTP Cooldown (seconds)', 'wp-otp'),
            [$this, 'field_otp_cooldown'],
            'wp-otp',
            'wp_otp_main'
        );

        add_settings_field(
            'otp_resend_limit',
            __('OTP Resend Limit'),
            [$this, 'field_otp_resend_limit'],
            'wp-otp',
            'wp_otp_main'
        );

        add_settings_field(
            'otp_resend_window',
            __('OTP Resend Window (minutes)'),
            [$this, 'field_otp_resend_window'],
            'wp-otp',
            'wp_otp_main'
        );

        register_setting(
            'wp_otp_settings_group',
            'wp_otp_settings',
            [$this, 'sanitize_settings']
        );

        add_settings_section(
            'wp_otp_main',
            __('OTP Settings', 'wp-otp'),
            null,
            'wp-otp'
        );

        add_settings_field(
            'otp_length',
            __('OTP Length', 'wp-otp'),
            [$this, 'field_otp_length'],
            'wp-otp',
            'wp_otp_main'
        );

        add_settings_field(
            'otp_expiry',
            __('OTP Expiry (minutes)', 'wp-otp'),
            [$this, 'field_otp_expiry'],
            'wp-otp',
            'wp_otp_main'
        );

        add_settings_field(
            'email_subject',
            __('Email Subject', 'wp-otp'),
            [$this, 'field_email_subject'],
            'wp-otp',
            'wp_otp_main'
        );

        add_settings_field(
            'email_body',
            __('Email Body', 'wp-otp'),
            [$this, 'field_email_body'],
            'wp-otp',
            'wp_otp_main'
        );

        add_settings_field(
            'sms_sender',
            __('SMS Sender Name', 'wp-otp'),
            [$this, 'field_sms_sender'],
            'wp-otp',
            'wp_otp_main'
        );

        add_settings_field(
            'sms_message',
            __('SMS Message Template', 'wp-otp'),
            [$this, 'field_sms_message'],
            'wp-otp',
            'wp_otp_main'
        );

        add_settings_field(
            'phone_only_auth',
            __('Enable Phone-only Authentication', 'wp-otp'),
            [$this, 'field_phone_only_auth'],
            'wp-otp',
            'wp_otp_main'
        );
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
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WP OTP Settings', 'wp-otp'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_otp_settings_group');
                do_settings_sections('wp-otp');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * OTP Length Field.
     */
    public function field_otp_length()
    {
        $options = wp_otp_get_settings();
        ?>
        <label for="wp_otp_otp_length"><?php esc_html_e('Length of the OTP code (4-10 digits).', 'wp-otp'); ?></label>
        <input type="number" id="wp_otp_otp_length" name="wp_otp_settings[otp_length]"
            value="<?php echo esc_attr($options['otp_length'] ?? 6); ?>" min="4" max="10" />
        <?php
    }

    /**
     * OTP Expiry Field.
     */
    public function field_otp_expiry()
    {
        $options = wp_otp_get_settings();
        ?>
        <label for="wp_otp_otp_expiry"><?php esc_html_e('How long the OTP is valid, in minutes.', 'wp-otp'); ?></label>
        <input type="number" id="wp_otp_otp_expiry" name="wp_otp_settings[otp_expiry]"
            value="<?php echo esc_attr($options['otp_expiry'] ?? 5); ?>" min="1" />
        <?php
    }

    /**
     * Email Subject Field.
     */
    public function field_email_subject()
    {
        $options = wp_otp_get_settings();
        $subject = $options['email_subject'] ?? __('Your OTP Code', 'wp-otp');
        $subject = apply_filters('wpml_translate_single_string', $subject, 'WP OTP', 'Email Subject');
        ?>
        <label for="wp_otp_email_subject"><?php esc_html_e('Subject line for OTP email.', 'wp-otp'); ?></label>
        <input type="text" id="wp_otp_email_subject" name="wp_otp_settings[email_subject]"
            value="<?php echo esc_attr($subject); ?>" class="regular-text" />
        <?php
    }


    /**
     * Email Body Field.
     */
    public function field_email_body()
    {
        $options = wp_otp_get_settings();

        // Fallback default
        $body = $options['email_body'] ?? __('Your OTP code is: {OTP}. It will expire in {MINUTES} minutes.', 'wp-otp');

        // Apply WPML translation
        $body = apply_filters('wpml_translate_single_string', $body, 'WP OTP', 'Email Body');
        ?>
        <label for="wp_otp_email_body">
            <?php esc_html_e('Body text for OTP email. Use placeholders like {OTP} and {MINUTES}.', 'wp-otp'); ?>
        </label>
        <textarea id="wp_otp_email_body" name="wp_otp_settings[email_body]" rows="5"
            class="large-text"><?php echo esc_textarea($body); ?></textarea>
        <?php
    }


    /**
     * SMS Sender Name Field.
     */
    public function field_sms_sender()
    {
        $options = wp_otp_get_settings();

        $sender = $options['sms_sender'] ?? '';
        $sender = apply_filters('wpml_translate_single_string', $sender, 'WP OTP', 'SMS Sender');
        ?>
        <label for="wp_otp_sms_sender">
            <?php esc_html_e('Sender name for SMS messages (must be registered with 019).', 'wp-otp'); ?>
        </label>
        <input type="text" id="wp_otp_sms_sender" name="wp_otp_settings[sms_sender]" value="<?php echo esc_attr($sender); ?>"
            class="regular-text" />
        <?php
    }

    /**
     * SMS Message Template Field.
     */
    public function field_sms_message()
    {
        $options = wp_otp_get_settings();

        $message = $options['sms_message'] ?? __('Your OTP code is {OTP}. It will expire in {MINUTES} minutes.', 'wp-otp');
        $message = apply_filters('wpml_translate_single_string', $message, 'WP OTP', 'SMS Message');
        ?>
        <label for="wp_otp_sms_message">
            <?php esc_html_e('SMS text template. Use placeholders like {OTP} and {MINUTES}.', 'wp-otp'); ?>
        </label>
        <textarea id="wp_otp_sms_message" name="wp_otp_settings[sms_message]" rows="3"
            class="large-text"><?php echo esc_textarea($message); ?></textarea>
        <?php
    }


    /**
     * Phone-only Auth Toggle Field.
     */
    public function field_phone_only_auth()
    {
        $options = wp_otp_get_settings();
        $checked = isset($options['phone_only_auth']) && $options['phone_only_auth'] === '1';
        ?>
        <label for="wp_otp_phone_only_auth">
            <input type="checkbox" id="wp_otp_phone_only_auth" name="wp_otp_settings[phone_only_auth]" value="1" <?php checked($checked); ?> />
            <?php esc_html_e('Enable phone-only login/registration (no username or password).', 'wp-otp'); ?>
        </label>
        <?php
    }

    public function field_otp_channels()
    {
        $options = wp_otp_get_settings();
        $channels = $options['otp_channels'] ?? [];

        ?>
        <label>
            <input type="checkbox" name="wp_otp_settings[otp_channels][]" value="email" <?php checked(in_array('email', $channels)); ?> />
            <?php esc_html_e('Email', 'wp-otp'); ?>
        </label><br>
        <label>
            <input type="checkbox" name="wp_otp_settings[otp_channels][]" value="sms" <?php checked(in_array('sms', $channels)); ?> /><?php esc_html_e('SMS', 'wp-otp'); ?>
        </label>
        <?php
    }

    public function field_otp_cooldown()
    {
        $options = wp_otp_get_settings();
        ?>
        <label for="wp_otp_otp_cooldown"><?php esc_html_e('Seconds before allowing resend.', 'wp-otp'); ?></label>
        <input type="number" id="wp_otp_otp_cooldown" name="wp_otp_settings[otp_cooldown]"
            value="<?php echo esc_attr($options['otp_cooldown'] ?? 30); ?>" min="10" />
        <?php
    }

    function field_otp_resend_limit()
    {
        $options = wp_otp_get_settings();
        ?>
        <input type="number" name="wp_otp_settings[otp_resend_limit]"
            value="<?php echo esc_attr($options['otp_resend_limit'] ?? 3); ?>" min="1" />
        <p class="description">Maximum times a user can request a new OTP within the resend window.</p>
        <?php
    }

    function field_otp_resend_window()
    {
        $options = wp_otp_get_settings();
        ?>
        <input type="number" name="wp_otp_settings[otp_resend_window]"
            value="<?php echo esc_attr($options['otp_resend_window'] ?? 15); ?>" min="1" />
        <p class="description">Time window (in minutes) for counting resend attempts.</p>
        <?php
    }

}
