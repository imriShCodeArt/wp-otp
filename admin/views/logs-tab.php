<h2><?php esc_html_e('OTP Logs', 'wp-otp'); ?></h2>

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