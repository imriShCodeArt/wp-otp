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
                        <?php echo esc_html__(ucfirst($channel), 'wp-otp'); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <input type="hidden" name="otp_channel" value="<?php echo esc_attr($channels[0]); ?>" />
<?php endif; ?>