<h2><?php esc_html_e('OTP Logs', 'wp-otp'); ?></h2>

<?php if (isset($stats) && !empty($stats)): ?>
    <div class="wp-otp-stats" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
        <h3><?php esc_html_e('Log Statistics', 'wp-otp'); ?></h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
            <div>
                <strong><?php esc_html_e('Total Logs:', 'wp-otp'); ?></strong> <?php echo esc_html($stats['total'] ?? 0); ?>
            </div>
            <div>
                <strong><?php esc_html_e('Last 24 Hours:', 'wp-otp'); ?></strong> <?php echo esc_html($stats['recent'] ?? 0); ?>
            </div>
            <?php if (!empty($stats['by_event_type'])): ?>
                <div>
                    <strong><?php esc_html_e('By Event Type:', 'wp-otp'); ?></strong>
                    <ul style="margin: 5px 0; padding-left: 20px;">
                        <?php foreach ($stats['by_event_type'] as $event_stat): ?>
                            <li><?php echo esc_html($event_stat->event_type); ?>: <?php echo esc_html($event_stat->count); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (!empty($stats['by_channel'])): ?>
                <div>
                    <strong><?php esc_html_e('By Channel:', 'wp-otp'); ?></strong>
                    <ul style="margin: 5px 0; padding-left: 20px;">
                        <?php foreach ($stats['by_channel'] as $channel_stat): ?>
                            <li><?php echo esc_html($channel_stat->channel); ?>: <?php echo esc_html($channel_stat->count); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

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
        <div class="event-type-multiselect" style="border: 1px solid #ddd; padding: 10px; background: #fff; max-height: 200px; overflow-y: auto;">
            <div style="margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                <button type="button" class="button button-small select-all-events" style="margin-right: 5px;">
                    <?php esc_html_e('Select All', 'wp-otp'); ?>
                </button>
                <button type="button" class="button button-small clear-all-events">
                    <?php esc_html_e('Clear All', 'wp-otp'); ?>
                </button>
            </div>
            <?php 
            $selected_event_types = isset($_GET['event_type']) ? (is_array($_GET['event_type']) ? $_GET['event_type'] : [$_GET['event_type']]) : [];
            $event_types = [
                'debug' => __('Debug', 'wp-otp'),
                'info' => __('Info', 'wp-otp'),
                'warning' => __('Warning', 'wp-otp'),
                'error' => __('Error', 'wp-otp'),
                'critical' => __('Critical', 'wp-otp'),
                'send_success' => __('Send Success', 'wp-otp'),
                'send_failed' => __('Send Failed', 'wp-otp'),
                'verify_success' => __('Verify Success', 'wp-otp'),
                'verify_failed' => __('Verify Failed', 'wp-otp'),
            ];
            ?>
            <?php foreach ($event_types as $value => $label): ?>
                <label style="display: block; margin: 5px 0;">
                    <input type="checkbox" name="event_type[]" value="<?php echo esc_attr($value); ?>" 
                           <?php checked(in_array($value, $selected_event_types)); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <small style="color: #666; display: block; margin-top: 5px;">
            <?php esc_html_e('Leave all unchecked to show all event types', 'wp-otp'); ?>
        </small>
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
                <th><?php esc_html_e('Channel', 'wp-otp'); ?></th>
                <th><?php esc_html_e('User ID', 'wp-otp'); ?></th>
                <th><?php esc_html_e('Message', 'wp-otp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html($log->created_at); ?></td>
                    <td>
                        <span class="log-event-type log-event-type-<?php echo esc_attr($log->event_type); ?>">
                            <?php echo esc_html($log->event_type); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($log->contact); ?></td>
                    <td><?php echo esc_html($log->channel ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($log->user_id ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($log->message); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<style>
.log-event-type {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}
.log-event-type-debug { background: #e7f3ff; color: #0066cc; }
.log-event-type-info { background: #e7f7e7; color: #006600; }
.log-event-type-warning { background: #fff3cd; color: #856404; }
.log-event-type-error { background: #f8d7da; color: #721c24; }
.log-event-type-critical { background: #f5c6cb; color: #721c24; }
.log-event-type-send_success { background: #d4edda; color: #155724; }
.log-event-type-send_failed { background: #f8d7da; color: #721c24; }
.log-event-type-verify_success { background: #d4edda; color: #155724; }
.log-event-type-verify_failed { background: #f8d7da; color: #721c24; }
</style>

<script>
jQuery(document).ready(function($) {
    // Select All functionality
    $('.select-all-events').on('click', function() {
        $('.event-type-multiselect input[type="checkbox"]').prop('checked', true);
    });
    
    // Clear All functionality
    $('.clear-all-events').on('click', function() {
        $('.event-type-multiselect input[type="checkbox"]').prop('checked', false);
    });
    
    // Update button states based on checkbox states
    function updateButtonStates() {
        var totalCheckboxes = $('.event-type-multiselect input[type="checkbox"]').length;
        var checkedCheckboxes = $('.event-type-multiselect input[type="checkbox"]:checked').length;
        
        if (checkedCheckboxes === 0) {
            $('.select-all-events').text('<?php echo esc_js(__('Select All', 'wp-otp')); ?>');
        } else if (checkedCheckboxes === totalCheckboxes) {
            $('.select-all-events').text('<?php echo esc_js(__('All Selected', 'wp-otp')); ?>');
        } else {
            $('.select-all-events').text('<?php echo esc_js(__('Select All', 'wp-otp')); ?> (' + checkedCheckboxes + '/' + totalCheckboxes + ')');
        }
    }
    
    // Update button states on checkbox change
    $('.event-type-multiselect input[type="checkbox"]').on('change', updateButtonStates);
    
    // Initialize button states
    updateButtonStates();
});
</script>