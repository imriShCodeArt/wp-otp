<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up options
delete_option('wp_otp_settings');

// Drop custom tables if needed
