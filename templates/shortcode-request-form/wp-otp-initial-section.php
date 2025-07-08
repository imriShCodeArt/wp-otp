<div id="wp-otp-initial-section">
    <div class="mb-3 d-flex align-items-center gap-2">
        <label id="otp_contact_label" class="form-label mb-0" style="min-width:110px;">
            <?php
            echo esc_html__('Email or Phone:', 'wp-otp');
            ?>
        </label>

        <input id="otp_phone" type="tel" name="otp_phone" class="form-control flex-grow-1" style="display: none;"
            minlength="9" maxlength="9" />

        <input id="otp_email" type="email" name="otp_email" class="form-control flex-grow-1" style="display: none;" />

        <button type="submit" id="wp-otp-send-btn" class="btn btn-outline-secondary btn-sm" style="min-width:80px;">
            <?php esc_html_e('Send OTP', 'wp-otp'); ?>
        </button>
    </div>
</div>