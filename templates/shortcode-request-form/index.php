<form id="wp-otp-request-form" method="post" action="#" class="d-flex flex-column gap-3 p-3 border rounded">

    <?php include __DIR__ . '/wp-otp-channel-selector.php'; ?>

    <div class="d-flex flex-row justify-content-between gap-3">
        <div class="flex-grow-1">

            <?php include __DIR__ . '/wp-otp-initial-section.php'; ?>

            <?php include __DIR__ . '/wp-otp-verification-section.php'; ?>

            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('wp_otp_nonce')); ?>" />
        </div>

        <div class="d-flex align-items-center">
            <span id="wp-otp-cooldown-timer" class="text-muted small"></span>
        </div>
    </div>

</form>
