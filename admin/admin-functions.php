<?php

if (!defined('ABSPATH')) {
    exit;
}

function wp_otp_admin_notice($message, $type = 'success')
{
    printf(
        '<div class="notice notice-%1$s"><p>%2$s</p></div>',
        esc_attr($type),
        esc_html($message)
    );
}
