<form id="wp-otp-request-form" method="post" action="#" class="d-flex flex-column gap-3 p-3 border rounded">

    <?php if (count($channels) > 1): ?>
        <div id="wp-otp-channel-section" class="d-flex flex-row flex-wrap align-items-center gap-3">
            <label class="form-label mb-0">
                <?php esc_html_e('Choose OTP Channel:', 'wp-otp'); ?>
            </label>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($channels as $index => $channel): ?>
                    <?php
                    $is_checked = ($index === 0) ? 'checked' : '';
                    $id = 'otp_channel_' . $channel;
                    ?>
                    <div class="form-check form-check-inline">
                        <input id="<?php echo esc_attr($id); ?>" class="form-check-input" type="radio" name="otp_channel"
                            value="<?php echo esc_attr($channel); ?>" <?php echo $is_checked; ?> />
                        <label class="form-check-label" for="<?php echo esc_attr($id); ?>">
                            <?php echo esc_html(ucfirst($channel)); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <input type="hidden" name="otp_channel" value="<?php echo esc_attr($channels[0]); ?>" />
    <?php endif; ?>

    <div class="d-flex flex-row justify-content-between gap-3">

        <div class="flex-grow-1">
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
                    <button type="submit" id="wp-otp-send-btn" class="btn btn-outline-secondary btn-sm"
                        style="min-width:80px;">
                        <?php esc_html_e('Send OTP', 'wp-otp'); ?>
                    </button>
                </div>
            </div>

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
                    <button type="button" id="wp-otp-change-contact-btn"
                        class="btn btn-outline-secondary btn-sm wp-otp-btn">
                        <?php esc_html_e('Change Email/Phone', 'wp-otp'); ?>
                    </button>
                    <button type="button" id="wp-otp-resend-btn" class="btn btn-outline-secondary btn-sm wp-otp-btn"
                        disabled>
                        <?php esc_html_e('Resend OTP', 'wp-otp'); ?>
                    </button>
                </div>
            </div>

            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wp_otp_nonce')); ?>" />
        </div>

        <div class="d-flex align-items-center">
            <span id="wp-otp-cooldown-timer" class="text-muted small"></span>
        </div>
    </div>
</form>