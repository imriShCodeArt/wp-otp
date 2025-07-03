<?php
/**
 * Shortcode template: Request OTP Form
 *
 * @var array $channels
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<form id="wp-otp-request-form" method="post" action="#">

    <!-- Initial section -->
    <div id="wp-otp-initial-section">
        <div id="wp-otp-contact-section">
            <?php if (count($channels) > 1): ?>
                <label><?php esc_html_e('Choose OTP Channel:', 'wp-otp'); ?></label><br />
                <?php foreach ($channels as $channel): ?>
                    <label>
                        <input type="radio" name="otp_channel" value="<?php echo esc_attr($channel); ?>" />
                        <?php echo esc_html(ucfirst($channel)); ?>
                    </label><br />
                <?php endforeach; ?>
            <?php else: ?>
                <input type="hidden" name="otp_channel" value="<?php echo esc_attr($channels[0]); ?>" />
            <?php endif; ?>

            <label for="otp_contact"><?php esc_html_e('Email or Phone:', 'wp-otp'); ?></label><br />
            <input type="text" name="otp_contact" id="otp_contact" required />

            <button type="submit" id="wp-otp-send-btn">
                <?php esc_html_e('Send OTP', 'wp-otp'); ?>
            </button>
        </div>

        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wp_otp_nonce')); ?>" />
    </div>

    <!-- Verification section -->
    <div id="wp-otp-verification-section" style="display:none; margin-top:15px;">
        <label for="wp_otp_input"><?php esc_html_e('Enter OTP:', 'wp-otp'); ?></label><br />
        <input type="text" id="wp_otp_input" name="wp_otp_input" />

        <button type="button" id="wp-otp-verify-btn">
            <?php esc_html_e('Verify OTP', 'wp-otp'); ?>
        </button>

        <button type="button" id="wp-otp-change-contact-btn">
            <?php esc_html_e('Change Email/Phone', 'wp-otp'); ?>
        </button>

        <button type="button" id="wp-otp-resend-btn" disabled>
            <?php esc_html_e('Resend OTP', 'wp-otp'); ?>
        </button>

        <span id="wp-otp-cooldown-timer"></span>
    </div>

</form>