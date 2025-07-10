<?php
/**
 * Test file for WP OTP Dual Mode functionality
 * 
 * This file tests the dual-mode authentication functionality.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Load WordPress
require_once ABSPATH . 'wp-config.php';

// Test the dual-mode functionality
function test_dual_mode_functionality() {
    echo "<h1>WP OTP Dual Mode Test</h1>\n";
    
    // Test 1: Check if settings are loaded correctly
    $settings = get_option('wp_otp_settings', []);
    echo "<h2>Test 1: Settings Check</h2>\n";
    echo "<p>Current settings:</p>\n";
    echo "<pre>" . print_r($settings, true) . "</pre>\n";
    
    // Test 2: Check OTP-only mode status
    $is_otp_only = isset($settings['phone_only_auth']) && $settings['phone_only_auth'] === '1';
    echo "<h2>Test 2: OTP-Only Mode Status</h2>\n";
    echo "<p>OTP-only mode enabled: " . ($is_otp_only ? 'Yes' : 'No') . "</p>\n";
    
    // Test 3: Check if auth overrides class exists
    echo "<h2>Test 3: Auth Overrides Class</h2>\n";
    if (class_exists('WpOtp\\WP_OTP_Auth_Overrides')) {
        echo "<p>✓ Auth Overrides class exists</p>\n";
        
        // Test the is_otp_only_auth_enabled method
        $auth_overrides = new \WpOtp\WP_OTP_Auth_Overrides();
        $reflection = new ReflectionClass($auth_overrides);
        $method = $reflection->getMethod('is_otp_only_auth_enabled');
        $method->setAccessible(true);
        $result = $method->invoke($auth_overrides);
        echo "<p>is_otp_only_auth_enabled() returns: " . ($result ? 'true' : 'false') . "</p>\n";
    } else {
        echo "<p>✗ Auth Overrides class not found</p>\n";
    }
    
    // Test 4: Check if required files exist
    echo "<h2>Test 4: Required Files</h2>\n";
    $required_files = [
        'includes/class-auth-overrides.php',
        'assets/css/wp-otp-auth.css',
        'assets/js/wp-otp-auth.js',
        'templates/auth-login-form.php'
    ];
    
    foreach ($required_files as $file) {
        $file_path = WP_OTP_PATH . $file;
        if (file_exists($file_path)) {
            echo "<p>✓ {$file} exists</p>\n";
        } else {
            echo "<p>✗ {$file} missing</p>\n";
        }
    }
    
    // Test 5: Check admin settings
    echo "<h2>Test 5: Admin Settings</h2>\n";
    if (class_exists('WpOtp\\WP_OTP_Admin_Fields')) {
        echo "<p>✓ Admin Fields class exists</p>\n";
    } else {
        echo "<p>✗ Admin Fields class not found</p>\n";
    }
    
    echo "<h2>Test Complete</h2>\n";
    echo "<p>If all tests pass, the dual-mode functionality should work correctly.</p>\n";
}

// Run the test if this file is accessed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    test_dual_mode_functionality();
} 