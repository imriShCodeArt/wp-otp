<?php

/**
 * Uninstall script for WP OTP plugin.
 *
 * This file is executed when the plugin is uninstalled via WordPress.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('wp_otp_settings');

// Drop custom OTP tables
global $wpdb;

// Drop OTP codes table
$codes_table = esc_sql($wpdb->prefix . 'otp_codes');
$wpdb->query("DROP TABLE IF EXISTS `$codes_table`");

// Drop OTP logs table
$logs_table = esc_sql($wpdb->prefix . 'otp_logs');
$wpdb->query("DROP TABLE IF EXISTS `$logs_table`");
