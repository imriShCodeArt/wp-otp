<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('wp_otp_settings');

// Drop custom OTP table
global $wpdb;
$table_name = $wpdb->prefix . 'otp_codes';

$wpdb->query("DROP TABLE IF EXISTS $table_name");
