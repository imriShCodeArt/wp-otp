<?php

if (!defined('ABSPATH')) {
    exit;
}
?>

<form id="wp-otp-form" method="post">
    <label for="wp-otp-phone"><?php esc_html_e('Phone Number:', 'wp-otp'); ?></label>
    <input type="text" name="wp-otp-phone" id="wp-otp-phone" value="" />
    <button type="submit"><?php esc_html_e('Send OTP', 'wp-otp'); ?></button>
</form>