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
    <div class="wp-otp-logs-actions" style="margin-bottom: 15px;">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <button type="button" id="select-all-logs" class="button button-small">
                <?php esc_html_e('Select All', 'wp-otp'); ?>
            </button>
            <button type="button" id="deselect-all-logs" class="button button-small">
                <?php esc_html_e('Deselect All', 'wp-otp'); ?>
            </button>
            <button type="button" id="delete-selected-logs" class="button button-small button-link-delete" disabled>
                <?php esc_html_e('Delete Selected', 'wp-otp'); ?>
            </button>
            <button type="button" id="delete-all-filtered-logs" class="button button-small button-link-delete">
                <?php esc_html_e('Delete All Filtered', 'wp-otp'); ?>
            </button>
            <span id="selected-count" style="color: #666; font-size: 12px;"></span>
        </div>
    </div>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 30px;">
                    <input type="checkbox" id="select-all-checkbox">
                </th>
                <th><?php esc_html_e('Date', 'wp-otp'); ?></th>
                <th><?php esc_html_e('Type', 'wp-otp'); ?></th>
                <th><?php esc_html_e('Subject', 'wp-otp'); ?></th>
                <th><?php esc_html_e('Contact', 'wp-otp'); ?></th>
                <th><?php esc_html_e('Channel', 'wp-otp'); ?></th>
                <th><?php esc_html_e('User ID', 'wp-otp'); ?></th>
                <th><?php esc_html_e('Message', 'wp-otp'); ?></th>
                <th style="width: 80px;"><?php esc_html_e('Actions', 'wp-otp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="log-checkbox" value="<?php echo esc_attr($log->id); ?>">
                    </td>
                    <td><?php echo esc_html($log->created_at); ?></td>
                    <td>
                        <span class="log-event-type log-event-type-<?php echo esc_attr($log->event_type); ?>">
                            <?php echo esc_html(ucfirst($log->event_type)); ?>
                        </span>
                    </td>
                    <td>
                        <span class="log-subject log-subject-<?php echo esc_attr($log->subject); ?>">
                            <?php echo esc_html($log->subject ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($log->contact); ?></td>
                    <td><?php echo esc_html($log->channel ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($log->user_id ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($log->message); ?></td>
                    <td>
                        <button type="button" class="button button-small button-link-delete delete-single-log" 
                                data-log-id="<?php echo esc_attr($log->id); ?>">
                            <?php esc_html_e('Delete', 'wp-otp'); ?>
                        </button>
                    </td>
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

.log-subject {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 500;
    background: #f8f9fa;
    color: #495057;
    border: 1px solid #dee2e6;
}

.log-subject-auth_success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-auth_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-auth_attempt { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
.log-subject-user_lookup_start { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-user_found { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-user_not_found { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
.log-subject-user_creation_start { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-user_creation_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-user_ready { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-login_attempt { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-login_verification { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-otp_verified { background: #d4edda; color: #155724; border-color: #c3e6cb; }

.log-subject-db_validation_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-db_otp_saved { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-db_otp_save_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-db_otp_not_found { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
.log-subject-db_status_updated { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-db_status_update_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-db_attempts_incremented { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-db_attempts_increment_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-db_cleanup_completed { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-db_high_attempt_count { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
.log-subject-db_error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-db_expired_otps_found { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }

.log-subject-send_success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-send_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-send_blocked { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
.log-subject-verify_success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-verify_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }

.log-subject-sms_sent { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-sms_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-sms_balance_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }

.log-subject-ajax_send_otp { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_nonce_check { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_send_params { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_missing_contact { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
.log-subject-ajax_manager_creation { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_send_call { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_send_result { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_send_success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-ajax_send_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-ajax_send_exception { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }

.log-subject-ajax_verify_otp { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_verify_params { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_missing_fields { background: #fff3cd; color: #856404; border-color: #ffeaa7; }
.log-subject-ajax_verify_call { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_verify_result { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_verify_success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-ajax_verify_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-ajax_verify_exception { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }

.log-subject-ajax_process_user { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_process_params { background: #e7f3ff; color: #0066cc; border-color: #b3d9ff; }
.log-subject-ajax_process_success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-ajax_login_success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.log-subject-ajax_user_creation_failed { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.log-subject-ajax_process_exception { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
</style>

<script>
jQuery(document).ready(function($) {
    // Event type multiselect functionality
    $('.select-all-events').on('click', function() {
        $('.event-type-multiselect input[type="checkbox"]').prop('checked', true);
    });
    
    $('.clear-all-events').on('click', function() {
        $('.event-type-multiselect input[type="checkbox"]').prop('checked', false);
    });
    
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
    
    $('.event-type-multiselect input[type="checkbox"]').on('change', updateButtonStates);
    updateButtonStates();

    // Log deletion functionality
    var nonce = '<?php echo wp_create_nonce('wp_otp_logs_nonce'); ?>';
    var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';

    // Select all logs
    $('#select-all-logs').on('click', function() {
        $('.log-checkbox').prop('checked', true);
        updateLogSelection();
    });

    // Deselect all logs
    $('#deselect-all-logs').on('click', function() {
        $('.log-checkbox').prop('checked', false);
        updateLogSelection();
    });

    // Select all checkbox in header
    $('#select-all-checkbox').on('change', function() {
        $('.log-checkbox').prop('checked', this.checked);
        updateLogSelection();
    });

    // Individual log checkboxes
    $(document).on('change', '.log-checkbox', function() {
        updateLogSelection();
    });

    function updateLogSelection() {
        var checkedBoxes = $('.log-checkbox:checked');
        var totalBoxes = $('.log-checkbox').length;
        
        $('#selected-count').text(checkedBoxes.length + ' of ' + totalBoxes + ' selected');
        
        if (checkedBoxes.length > 0) {
            $('#delete-selected-logs').prop('disabled', false);
        } else {
            $('#delete-selected-logs').prop('disabled', true);
        }
        
        // Update header checkbox
        if (checkedBoxes.length === totalBoxes) {
            $('#select-all-checkbox').prop('checked', true);
        } else {
            $('#select-all-checkbox').prop('checked', false);
        }
    }

    // Delete single log
    $(document).on('click', '.delete-single-log', function() {
        var logId = $(this).data('log-id');
        var row = $(this).closest('tr');
        
        if (confirm('<?php echo esc_js(__('Are you sure you want to delete this log?', 'wp-otp')); ?>')) {
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wp_otp_delete_log',
                    nonce: nonce,
                    log_id: logId
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove();
                            updateLogSelection();
                        });
                        showMessage(response.data.message, 'success');
                    } else {
                        showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    showMessage('<?php echo esc_js(__('An error occurred while deleting the log.', 'wp-otp')); ?>', 'error');
                }
            });
        }
    });

    // Delete selected logs
    $('#delete-selected-logs').on('click', function() {
        var selectedIds = $('.log-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selectedIds.length === 0) {
            showMessage('<?php echo esc_js(__('No logs selected for deletion.', 'wp-otp')); ?>', 'error');
            return;
        }
        
        if (confirm('<?php echo esc_js(__('Are you sure you want to delete the selected logs?', 'wp-otp')); ?>')) {
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wp_otp_delete_logs',
                    nonce: nonce,
                    log_ids: selectedIds
                },
                success: function(response) {
                    if (response.success) {
                        $('.log-checkbox:checked').closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            updateLogSelection();
                        });
                        showMessage(response.data.message, 'success');
                    } else {
                        showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    showMessage('<?php echo esc_js(__('An error occurred while deleting the logs.', 'wp-otp')); ?>', 'error');
                }
            });
        }
    });

    // Delete all filtered logs
    $('#delete-all-filtered-logs').on('click', function() {
        var totalLogs = $('.log-checkbox').length;
        
        if (totalLogs === 0) {
            showMessage('<?php echo esc_js(__('No logs to delete.', 'wp-otp')); ?>', 'error');
            return;
        }
        
        if (confirm('<?php echo esc_js(__('Are you sure you want to delete all filtered logs? This action cannot be undone.', 'wp-otp')); ?>')) {
            // Get current filter values
            var filterData = {
                from_date: $('input[name="from_date"]').val(),
                to_date: $('input[name="to_date"]').val(),
                contact: $('input[name="contact"]').val(),
                channel: $('select[name="channel"]').val(),
                event_types: $('input[name="event_type[]"]:checked').map(function() {
                    return $(this).val();
                }).get()
            };
            
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wp_otp_delete_all_logs',
                    nonce: nonce,
                    ...filterData
                },
                success: function(response) {
                    if (response.success) {
                        $('.log-checkbox').closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                        updateLogSelection();
                        showMessage(response.data.message, 'success');
                        
                        // Reload page after a short delay to refresh statistics
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showMessage(response.data.message, 'error');
                    }
                },
                error: function() {
                    showMessage('<?php echo esc_js(__('An error occurred while deleting the logs.', 'wp-otp')); ?>', 'error');
                }
            });
        }
    });

    function showMessage(message, type) {
        var messageClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + messageClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Initialize log selection
    updateLogSelection();
});
</script>