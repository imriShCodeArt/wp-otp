<?php

if (!defined('ABSPATH')) {
    exit;
}
?>

<form id="wp-otp-verify-form" method="post">
    <label for="wp-otp-code"><?php esc_html_e('Enter OTP:', 'wp-otp'); ?></label>
    <input type="text" name="wp-otp-code" id="wp-otp-code" value="" />
    <button type="submit"><?php esc_html_e('Verify', 'wp-otp'); ?></button>
</form>