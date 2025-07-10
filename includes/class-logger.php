<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Logger
 *
 * Handles inserting logs into the wp_otp_logs table and provides
 * different logging levels and methods.
 */
class WP_OTP_Logger
{
    /**
     * @var string The full table name for logs.
     */
    protected $table_name;

    /**
     * @var bool Whether to also log to WordPress debug log.
     */
    protected $debug_log;

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * WP_OTP_Logger constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'otp_logs';
        $this->debug_log = defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Log an event to the logs table.
     *
     * @param string      $event_type Event type (debug/info/warning/error/critical).
     * @param string      $contact    The contact info (email or phone).
     * @param string|null $message    Optional log message.
     * @param string|null $channel    Channel used (email|sms).
     * @param int|null    $user_id    Optional user ID associated with the event.

     *
     * @return bool Whether the log was successfully inserted.
     */
    public function log($event_type, $contact, $message, $channel = null, $user_id = null)
    {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            [
                'event_type' => $event_type,
                'contact' => $contact,
                'message' => $message,
                'channel' => $channel,
                'user_id' => $user_id,
                'created_at' => current_time('mysql', 1),
            ],
            [
                '%s',
                '%s',
                '%s',
                $channel !== null ? '%s' : null,
                $user_id !== null ? '%d' : null,
                '%s',
            ]
        );

        // Also log to WordPress debug log if enabled
        if ($this->debug_log) {
            $log_message = sprintf(
                '[WP OTP] [%s] Contact: %s, Channel: %s, User: %s - %s',
                strtoupper($event_type),
                $contact,
                $channel ?? 'N/A',
                $user_id ?? 'N/A',
                $message ?? 'No message'
            );
            error_log($log_message);
        }

        return $result !== false;
    }

    /**
     * Log a debug message.
     *
     * @param string      $message The debug message.
     * @param string|null $contact The contact info.
     * @param string|null $channel The channel used.
     * @param int|null    $user_id The user ID.
     *
     * @return bool
     */
    public function debug($message, $contact = null, $channel = null, $user_id = null)
    {
        return $this->log(self::LEVEL_DEBUG, $contact ?? 'system', $message, $channel, $user_id);
    }

    /**
     * Log an info message.
     *
     * @param string      $message The info message.
     * @param string|null $contact The contact info.
     * @param string|null $channel The channel used.
     * @param int|null    $user_id The user ID.
     *
     * @return bool
     */
    public function info($message, $contact = null, $channel = null, $user_id = null)
    {
        return $this->log(self::LEVEL_INFO, $contact ?? 'system', $message, $channel, $user_id);
    }

    /**
     * Log a warning message.
     *
     * @param string      $message The warning message.
     * @param string|null $contact The contact info.
     * @param string|null $channel The channel used.
     * @param int|null    $user_id The user ID.
     *
     * @return bool
     */
    public function warning($message, $contact = null, $channel = null, $user_id = null)
    {
        return $this->log(self::LEVEL_WARNING, $contact ?? 'system', $message, $channel, $user_id);
    }

    /**
     * Log an error message.
     *
     * @param string      $message The error message.
     * @param string|null $contact The contact info.
     * @param string|null $channel The channel used.
     * @param int|null    $user_id The user ID.
     *
     * @return bool
     */
    public function error($message, $contact = null, $channel = null, $user_id = null)
    {
        return $this->log(self::LEVEL_ERROR, $contact ?? 'system', $message, $channel, $user_id);
    }

    /**
     * Log a critical error message.
     *
     * @param string      $message The critical error message.
     * @param string|null $contact The contact info.
     * @param string|null $channel The channel used.
     * @param int|null    $user_id The user ID.
     *
     * @return bool
     */
    public function critical($message, $contact = null, $channel = null, $user_id = null)
    {
        return $this->log(self::LEVEL_CRITICAL, $contact ?? 'system', $message, $channel, $user_id);
    }

    /**
     * Log an exception.
     *
     * @param \Exception  $exception The exception to log.
     * @param string|null $contact   The contact info.
     * @param string|null $channel   The channel used.
     * @param int|null    $user_id   The user ID.
     *
     * @return bool
     */
    public function exception(\Exception $exception, $contact = null, $channel = null, $user_id = null)
    {
        $message = sprintf(
            'Exception: %s in %s:%d - %s',
            get_class($exception),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getMessage()
        );

        return $this->error($message, $contact, $channel, $user_id);
    }

    /**
     * Log a system message (for initialization, loading, etc.).
     *
     * @param string $message The system message.
     * @param string $level   The log level (default: info).
     *
     * @return bool
     */
    public function system($message, $level = self::LEVEL_INFO)
    {
        return $this->log($level, 'system', $message, null, null);
    }

    /**
     * Get logs with optional filtering.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_logs($args = [])
    {
        global $wpdb;

        $defaults = [
            'event_type' => null,
            'event_types' => null,
            'contact' => null,
            'channel' => null,
            'user_id' => null,
            'from_date' => null,
            'to_date' => null,
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);
        $where_conditions = [];
        $where_values = [];

        if ($args['event_type']) {
            $where_conditions[] = 'event_type = %s';
            $where_values[] = $args['event_type'];
        }

        if ($args['event_types'] && is_array($args['event_types']) && !empty($args['event_types'])) {
            $placeholders = array_fill(0, count($args['event_types']), '%s');
            $where_conditions[] = 'event_type IN (' . implode(', ', $placeholders) . ')';
            $where_values = array_merge($where_values, $args['event_types']);
        }



        if ($args['contact']) {
            $where_conditions[] = 'contact LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like($args['contact']) . '%';
        }

        if ($args['channel']) {
            $where_conditions[] = 'channel = %s';
            $where_values[] = $args['channel'];
        }

        if ($args['user_id']) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        if ($args['from_date']) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['from_date'];
        }

        if ($args['to_date']) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['to_date'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $order_clause = sprintf('ORDER BY %s %s', $args['orderby'], $args['order']);
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);

        $sql = "SELECT * FROM {$this->table_name} {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, ...$where_values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Clear old logs.
     *
     * @param int $days_to_keep Number of days to keep logs.
     * @return int Number of deleted records.
     */
    public function clear_old_logs($days_to_keep = 30)
    {
        global $wpdb;

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $cutoff_date
            )
        );
    }

    /**
     * Get log statistics.
     *
     * @return array
     */
    public function get_statistics()
    {
        global $wpdb;

        $stats = [];

        // Total logs
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        // Logs by event type
        $stats['by_event_type'] = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count FROM {$this->table_name} GROUP BY event_type"
        );



        // Logs by channel
        $stats['by_channel'] = $wpdb->get_results(
            "SELECT channel, COUNT(*) as count FROM {$this->table_name} WHERE channel IS NOT NULL GROUP BY channel"
        );

        // Recent activity (last 24 hours)
        $stats['recent'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE created_at > %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            )
        );

        return $stats;
    }

    /**
     * Delete a single log by ID.
     *
     * @param int $log_id
     * @return bool
     */
    public function delete_log($log_id)
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $log_id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete multiple logs by IDs.
     *
     * @param array $log_ids
     * @return int Number of deleted records
     */
    public function delete_logs($log_ids)
    {
        if (empty($log_ids) || !is_array($log_ids)) {
            return 0;
        }

        global $wpdb;

        $ids = array_map('intval', $log_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)",
                ...$ids
            )
        );

        return $result !== false ? $result : 0;
    }

    /**
     * Delete all logs with optional filtering.
     *
     * @param array $args Filter arguments (same as get_logs)
     * @return int Number of deleted records
     */
    public function delete_all_logs($args = [])
    {
        global $wpdb;

        $defaults = [
            'event_type' => null,
            'event_types' => null,
            'contact' => null,
            'channel' => null,
            'user_id' => null,
            'from_date' => null,
            'to_date' => null,
        ];

        $args = wp_parse_args($args, $defaults);
        $where_conditions = [];
        $where_values = [];

        if ($args['event_type']) {
            $where_conditions[] = 'event_type = %s';
            $where_values[] = $args['event_type'];
        }

        if ($args['event_types'] && is_array($args['event_types']) && !empty($args['event_types'])) {
            $placeholders = array_fill(0, count($args['event_types']), '%s');
            $where_conditions[] = 'event_type IN (' . implode(', ', $placeholders) . ')';
            $where_values = array_merge($where_values, $args['event_types']);
        }

        if ($args['contact']) {
            $where_conditions[] = 'contact LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like($args['contact']) . '%';
        }

        if ($args['channel']) {
            $where_conditions[] = 'channel = %s';
            $where_values[] = $args['channel'];
        }

        if ($args['user_id']) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        if ($args['from_date']) {
            $where_conditions[] = 'created_at >= %s';
            $where_values[] = $args['from_date'];
        }

        if ($args['to_date']) {
            $where_conditions[] = 'created_at <= %s';
            $where_values[] = $args['to_date'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        $sql = "DELETE FROM {$this->table_name} {$where_clause}";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, ...$where_values);
        }

        $result = $wpdb->query($sql);

        return $result !== false ? $result : 0;
    }
}
