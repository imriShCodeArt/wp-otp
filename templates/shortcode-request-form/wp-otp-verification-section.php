<div id="wp-otp-verification-section" style="display:none;">
    <div class="mb-3 d-flex align-items-center gap-2">
        <label for="wp_otp_input" class="form-label mb-0">
            <?php esc_html_e('Enter OTP:', 'wp-otp'); ?>
        </label>
        <input type="text" class="form-control flex-grow-1" id="wp_otp_input" name="wp_otp_input" />
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button type="button" id="wp-otp-verify-btn" class="btn btn-outline-secondary btn-sm wp-otp-btn">
            <?php esc_html_e('Verify OTP', 'wp-otp'); ?>
        </button>
        <button type="button" id="wp-otp-change-contact-btn" class="btn btn-outline-secondary btn-sm wp-otp-btn">
            <?php esc_html_e('Change Email/Phone', 'wp-otp'); ?>
        </button>
        <button type="button" id="wp-otp-resend-btn" class="btn btn-outline-secondary btn-sm wp-otp-btn" disabled>
            <?php esc_html_e('Resend OTP', 'wp-otp'); ?>
        </button>
    </div>
</div>