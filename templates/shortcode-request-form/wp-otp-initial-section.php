<div id="wp-otp-initial-section">
    <div class="mb-3 d-flex align-items-center gap-2">
        <label for="otp_contact" id="otp_contact_label" class="form-label mb-0" style="min-width:110px;">
            <?php
            if (!empty($channels)) {
                echo esc_html(
                    ucfirst($channels[0]) === 'Sms'
                    ? __('Phone Number:', 'wp-otp')
                    : __('Email Address:', 'wp-otp')
                );
            } else {
                echo esc_html__('Email or Phone:', 'wp-otp');
            }
            ?>
        </label>

        <input type="text" class="form-control flex-grow-1" name="otp_contact" id="otp_contact" required />

        <button type="submit" id="wp-otp-send-btn" class="btn btn-outline-secondary btn-sm" style="min-width:80px;">
            <?php esc_html_e('Send OTP', 'wp-otp'); ?>
        </button>
    </div>
</div>