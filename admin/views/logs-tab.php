<h2><?php esc_html_e('OTP Logs', 'wp-otp'); ?></h2>

<form method="get" style="margin-bottom: 20px;">
    <input type="hidden" name="page" value="wp-otp">
    <input type="hidden" name="tab" value="logs">

    <label>
        <?php esc_html_e('From Date:', 'wp-otp'); ?>
        <input type="date" name="from_date" value="<?php echo esc_attr($_GET['from_date'] ?? ''); ?>">
    </label>

    <label>
        <?php esc_html_e('To Date:', 'wp-otp'); ?>
        <input type="date" name="to_date" value="<?php echo esc_attr($_GET['to_date'] ?? ''); ?>">
    </label>

    <label>
        <?php esc_html_e('Contact:', 'wp-otp'); ?>
        <input type="text" name="contact" value="<?php echo esc_attr($_GET['contact'] ?? ''); ?>">
    </label>

    <label>
        <?php esc_html_e('Channel:', 'wp-otp'); ?>
        <select name="channel">
            <option value=""><?php esc_html_e('All', 'wp-otp'); ?></option>
            <option value="email" <?php selected($_GET['channel'] ?? '', 'email'); ?>>Email</option>
            <option value="sms" <?php selected($_GET['channel'] ?? '', 'sms'); ?>>SMS</option>
        </select>
    </label>

    <label>
        <?php esc_html_e('Event Type:', 'wp-otp'); ?>
        <select name="event_type">
            <option value=""><?php esc_html_e('All', 'wp-otp'); ?></option>
            <option value="send_success" <?php selected($_GET['event_type'] ?? '', 'send_success'); ?>>
                <?php esc_html_e('Send Success', 'wp-otp'); ?>
            </option>
            <option value="send_failed" <?php selected($_GET['event_type'] ?? '', 'send_failed'); ?>>
                <?php esc_html_e('Send Failed', 'wp-otp'); ?>
            </option>
            <option value="verify_success" <?php selected($_GET['event_type'] ?? '', 'verify_success'); ?>>
                <?php esc_html_e('Verify Success', 'wp-otp'); ?>
            </option>
            <option value="verify_failed" <?php selected($_GET['event_type'] ?? '', 'verify_failed'); ?>>
                <?php esc_html_e('Verify Failed', 'wp-otp'); ?>
            </option>
        </select>
    </label>

    <button type="submit" class="button button-primary">
        <?php esc_html_e('Filter Logs', 'wp-otp'); ?>
    </button>

    <a href="<?php echo esc_url(add_query_arg(array_merge($_GET, ['download_csv' => '1']))); ?>" class="button">
        <?php esc_html_e('Download CSV', 'wp-otp'); ?>
    </a>

</form>

<?php if (empty($logs)): ?>
    <p><?php esc_html_e('No logs found.', 'wp-otp'); ?></p>
<?php else: ?>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Date', 'wp-otp'); ?></th>
                <th><?php esc_html_e('Event Type', 'wp-otp'); ?></th>
                <th><?php esc_html_e('Contact', 'wp-otp'); ?></th>
                <th><?php esc_html_e('Message', 'wp-otp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html($log->created_at); ?></td>
                    <td><?php echo esc_html($log->event_type); ?></td>
                    <td><?php echo esc_html($log->contact); ?></td>
                    <td><?php echo esc_html($log->message); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>