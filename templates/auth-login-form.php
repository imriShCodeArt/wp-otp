<?php
/**
 * Template: OTP Authentication Login Form
 * 
 * This template replaces the default WordPress login form with an OTP-based authentication system.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wp-otp-auth-container">
    <div class="wp-otp-auth-form">
        <div class="wp-otp-auth-header">
            <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
            <p class="wp-otp-auth-subtitle"><?php esc_html_e('Login with OTP', 'wp-otp'); ?></p>
        </div>

        <form id="wp-otp-auth-form" method="post" action="">
            <?php wp_nonce_field('wp_otp_auth_nonce', 'wp_otp_auth_nonce'); ?>
            <input type="hidden" name="wp_otp_auth" value="1" />
            
            <!-- Step 1: Contact Information -->
            <div id="wp-otp-step-1" class="wp-otp-step active">
                <div class="wp-otp-form-group">
                    <label for="wp_otp_contact" class="wp-otp-label">
                        <?php esc_html_e('Email or Phone Number', 'wp-otp'); ?>
                    </label>
                    <input 
                        type="text" 
                        id="wp_otp_contact" 
                        name="contact" 
                        class="wp-otp-input" 
                        placeholder="<?php esc_attr_e('Enter your email or phone number', 'wp-otp'); ?>"
                        required 
                    />
                    <div class="wp-otp-error" id="wp_otp_contact_error"></div>
                </div>

                <div class="wp-otp-form-group">
                    <label class="wp-otp-label"><?php esc_html_e('Send OTP via:', 'wp-otp'); ?></label>
                    <div class="wp-otp-channel-options">
                        <label class="wp-otp-radio">
                            <input type="radio" name="channel" value="email" checked />
                            <span class="wp-otp-radio-text"><?php esc_html_e('Email', 'wp-otp'); ?></span>
                        </label>
                        <label class="wp-otp-radio">
                            <input type="radio" name="channel" value="sms" />
                            <span class="wp-otp-radio-text"><?php esc_html_e('SMS', 'wp-otp'); ?></span>
                        </label>
                    </div>
                </div>

                <div class="wp-otp-form-actions">
                    <button type="button" id="wp-otp-send-btn" class="wp-otp-btn wp-otp-btn-primary">
                        <?php esc_html_e('Send OTP', 'wp-otp'); ?>
                    </button>
                </div>

                <div class="wp-otp-message" id="wp_otp_send_message"></div>
            </div>

            <!-- Step 2: OTP Verification -->
            <div id="wp-otp-step-2" class="wp-otp-step">
                <div class="wp-otp-form-group">
                    <label for="wp_otp_otp" class="wp-otp-label">
                        <?php esc_html_e('Enter OTP', 'wp-otp'); ?>
                    </label>
                    <input 
                        type="text" 
                        id="wp_otp_otp" 
                        name="otp" 
                        class="wp-otp-input wp-otp-otp-input" 
                        placeholder="<?php esc_attr_e('Enter 6-digit OTP', 'wp-otp'); ?>"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        required 
                    />
                    <div class="wp-otp-error" id="wp_otp_otp_error"></div>
                </div>

                <div class="wp-otp-form-actions">
                    <button type="button" id="wp-otp-verify-btn" class="wp-otp-btn wp-otp-btn-primary">
                        <?php esc_html_e('Verify & Login', 'wp-otp'); ?>
                    </button>
                    <button type="button" id="wp-otp-back-btn" class="wp-otp-btn wp-otp-btn-secondary">
                        <?php esc_html_e('Back', 'wp-otp'); ?>
                    </button>
                </div>

                <div class="wp-otp-resend-section">
                    <p class="wp-otp-resend-text">
                        <?php esc_html_e("Didn't receive the OTP?", 'wp-otp'); ?>
                    </p>
                    <button type="button" id="wp-otp-resend-btn" class="wp-otp-btn wp-otp-btn-link" disabled>
                        <?php esc_html_e('Resend OTP', 'wp-otp'); ?>
                    </button>
                    <span id="wp-otp-resend-timer" class="wp-otp-resend-timer"></span>
                </div>

                <div class="wp-otp-message" id="wp_otp_verify_message"></div>
            </div>

            <!-- Loading State -->
            <div id="wp-otp-loading" class="wp-otp-loading" style="display: none;">
                <div class="wp-otp-spinner"></div>
                <p><?php esc_html_e('Processing...', 'wp-otp'); ?></p>
            </div>
        </form>

        <!-- Error Messages -->
        <div id="wp-otp-error-container" class="wp-otp-error-container" style="display: none;">
            <div class="wp-otp-error-message" id="wp_otp_error_message"></div>
        </div>

        <!-- Success Messages -->
        <div id="wp-otp-success-container" class="wp-otp-success-container" style="display: none;">
            <div class="wp-otp-success-message" id="wp_otp_success_message"></div>
        </div>
    </div>

    <!-- Footer Links -->
    <div class="wp-otp-auth-footer">
        <p>
            <?php 
            printf(
                esc_html__('By logging in, you agree to our %1$sTerms of Service%2$s and %3$sPrivacy Policy%4$s.', 'wp-otp'),
                '<a href="' . esc_url(home_url('/terms/')) . '">',
                '</a>',
                '<a href="' . esc_url(home_url('/privacy/')) . '">',
                '</a>'
            ); 
            ?>
        </p>
    </div>
</div>

<script type="text/javascript">
// Initialize form state
document.addEventListener('DOMContentLoaded', function() {
    // Auto-detect channel based on input
    const contactInput = document.getElementById('wp_otp_contact');
    const emailRadio = document.querySelector('input[name="channel"][value="email"]');
    const smsRadio = document.querySelector('input[name="channel"][value="sms"]');

    contactInput.addEventListener('input', function() {
        const value = this.value.trim();
        if (value.includes('@')) {
            emailRadio.checked = true;
        } else if (value.match(/^\d+$/)) {
            smsRadio.checked = true;
        }
    });

    // OTP input formatting
    const otpInput = document.getElementById('wp_otp_otp');
    otpInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
    });
});
</script> 