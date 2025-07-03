<?php

if (!defined('ABSPATH')) {
    exit;
}
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