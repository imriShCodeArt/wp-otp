<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Admin_Fields
 *
 * Renders all WP OTP settings fields in the admin panel.
 *
 * @package WpOtp
 */
class WP_OTP_Admin_Fields
{
    /**
     * Render the OTP Length input field.
     *
     * @return void
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
     * Render the OTP Expiry input field.
     *
     * @return void
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
     * Render the Email Subject input field.
     *
     * @return void
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
     * Render the Email Body textarea field.
     *
     * @return void
     */
    public function field_email_body()
    {
        $options = wp_otp_get_settings();
        $body = $options['email_body'] ?? __('Your OTP code is: {OTP}. It will expire in {MINUTES} minutes.', 'wp-otp');
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
     * Render the SMS Sender Name input field.
     *
     * @return void
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
        <input type="text" id="wp_otp_sms_sender" name="wp_otp_settings[sms_sender]"
               value="<?php echo esc_attr($sender); ?>" class="regular-text" />
        <?php
    }

    /**
     * Render the SMS Message Template textarea field.
     *
     * @return void
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
     * Render the Phone-only Auth toggle field.
     *
     * @return void
     */
    public function field_phone_only_auth()
    {
        $options = wp_otp_get_settings();
        $checked = isset($options['phone_only_auth']) && $options['phone_only_auth'] === '1';
        ?>
        <label for="wp_otp_phone_only_auth">
            <input type="checkbox" id="wp_otp_phone_only_auth" name="wp_otp_settings[phone_only_auth]"
                   value="1" <?php checked($checked); ?> />
            <?php esc_html_e('Enable phone-only login/registration (no username or password).', 'wp-otp'); ?>
        </label>
        <?php
    }

    /**
     * Render the OTP Channels checkbox fields.
     *
     * @return void
     */
    public function field_otp_channels()
    {
        $options = wp_otp_get_settings();
        $channels = $options['otp_channels'] ?? [];
        ?>
        <label>
            <input type="checkbox" name="wp_otp_settings[otp_channels][]"
                   value="email" <?php checked(in_array('email', $channels)); ?> />
            <?php esc_html_e('Email', 'wp-otp'); ?>
        </label><br>
        <label>
            <input type="checkbox" name="wp_otp_settings[otp_channels][]"
                   value="sms" <?php checked(in_array('sms', $channels)); ?> />
            <?php esc_html_e('SMS', 'wp-otp'); ?>
        </label>
        <?php
    }

    /**
     * Render the OTP Cooldown field.
     *
     * @return void
     */
    public function field_otp_cooldown()
    {
        $options = wp_otp_get_settings();
        ?>
        <label for="wp_otp_otp_cooldown"><?php esc_html_e('Seconds before allowing resend.', 'wp-otp'); ?></label>
        <input type="number" id="wp_otp_otp_cooldown" name="wp_otp_settings[otp_cooldown]"
               value="<?php echo esc_attr($options['otp_cooldown'] ?? 30); ?>" min="10" />
        <?php
    }

    /**
     * Render the OTP Resend Limit field.
     *
     * @return void
     */
    public function field_otp_resend_limit()
    {
        $options = wp_otp_get_settings();
        ?>
        <input type="number" name="wp_otp_settings[otp_resend_limit]"
               value="<?php echo esc_attr($options['otp_resend_limit'] ?? 3); ?>" min="1" />
        <p class="description"><?php esc_html_e('Maximum times a user can request a new OTP within the resend window.', 'wp-otp'); ?></p>
        <?php
    }

    /**
     * Render the OTP Resend Window field.
     *
     * @return void
     */
    public function field_otp_resend_window()
    {
        $options = wp_otp_get_settings();
        ?>
        <input type="number" name="wp_otp_settings[otp_resend_window]"
               value="<?php echo esc_attr($options['otp_resend_window'] ?? 15); ?>" min="1" />
        <p class="description"><?php esc_html_e('Time window (in minutes) for counting resend attempts.', 'wp-otp'); ?></p>
        <?php
    }
}
