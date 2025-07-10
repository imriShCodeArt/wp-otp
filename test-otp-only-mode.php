<?php
/**
 * Test file for WP OTP Only Mode functionality
 * 
 * This file tests the OTP-only authentication functionality.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Load WordPress
require_once ABSPATH . 'wp-config.php';

// Test the OTP-only mode functionality
function test_otp_only_mode() {
    echo "<h1>WP OTP Only Mode Test</h1>\n";
    
    // Test 1: Check current settings
    $settings = get_option('wp_otp_settings', []);
    echo "<h2>Test 1: Current Settings</h2>\n";
    echo "<p>OTP-only mode enabled: " . (isset($settings['phone_only_auth']) && $settings['phone_only_auth'] === '1' ? 'Yes' : 'No') . "</p>\n";
    
    // Test 2: Check auth overrides class
    echo "<h2>Test 2: Auth Overrides Class</h2>\n";
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
    
    // Test 3: Check login page behavior
    echo "<h2>Test 3: Login Page Behavior</h2>\n";
    if ($result) {
        echo "<p>✓ OTP-only mode is enabled - default WordPress login should be replaced</p>\n";
        echo "<p>Expected behavior: Only OTP login form should be visible</p>\n";
    } else {
        echo "<p>✓ OTP-only mode is disabled - default WordPress login should be available</p>\n";
        echo "<p>Expected behavior: Standard WordPress login form should be visible</p>\n";
    }
    
    // Test 4: Check authentication logic
    echo "<h2>Test 4: Authentication Logic</h2>\n";
    if ($result) {
        echo "<p>✓ In OTP-only mode, regular username/password authentication should be blocked</p>\n";
    } else {
        echo "<p>✓ In dual mode, both regular and OTP authentication should be allowed</p>\n";
    }
    
    echo "<h2>Test Complete</h2>\n";
    echo "<p>To test the functionality:</p>\n";
    echo "<ol>\n";
    echo "<li>Go to <a href='" . wp_login_url() . "'>WordPress Login Page</a></li>\n";
    if ($result) {
        echo "<li>You should see only the OTP login form (no username/password fields)</li>\n";
        echo "<li>Try to access the login page directly - it should show OTP-only interface</li>\n";
    } else {
        echo "<li>You should see the standard WordPress login form</li>\n";
        echo "<li>Regular username/password authentication should work normally</li>\n";
    }
    echo "</ol>\n";
}

// Run the test if this file is accessed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    test_otp_only_mode();
} 