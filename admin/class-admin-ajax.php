<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Admin_Ajax
 *
 * Handles AJAX requests for admin and frontend OTP logic.
 */
class WP_OTP_Admin_Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_wp_otp_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_nopriv_wp_otp_send_otp', [$this, 'wp_otp_send_otp']);
        add_action('wp_ajax_wp_otp_send_otp', [$this, 'wp_otp_send_otp']);
        add_action('wp_ajax_nopriv_wp_otp_verify_otp', [$this, 'wp_otp_verify_otp']);
        add_action('wp_ajax_wp_otp_verify_otp', [$this, 'wp_otp_verify_otp']);
        
        // Log deletion handlers
        add_action('wp_ajax_wp_otp_delete_log', [$this, 'delete_log']);
        add_action('wp_ajax_wp_otp_delete_logs', [$this, 'delete_logs']);
        add_action('wp_ajax_wp_otp_delete_all_logs', [$this, 'delete_all_logs']);
    }

    /**
     * Handle OTP send AJAX request.
     *
     * @return void
     */
    public function wp_otp_send_otp()
    {
        $logger = new \WpOtp\WP_OTP_Logger();
        $logger->debug('send_otp AJAX called');
        
        try {
            check_ajax_referer('wp_otp_nonce', 'nonce');
            $logger->debug('Send nonce check passed');

            $contact = sanitize_text_field($_POST['contact'] ?? '');
            $channel = sanitize_text_field($_POST['channel'] ?? '');
            
            $logger->debug('Send - Contact: ' . $contact . ', Channel: ' . $channel);

            if (empty($contact)) {
                $logger->warning('Missing contact for send');
                wp_send_json_error([
                    'message' => __('Contact information is required.', 'wp-otp'),
                    'code' => 'missing_contact'
                ]);
            }

            $logger->debug('Creating manager for send');
            $manager = new \WpOtp\WP_OTP_Manager();
            $logger->debug('Manager created, calling send_otp');
            
            $result = $manager->send_otp($contact, $channel);
            $logger->debug('Send result: ' . print_r($result, true));

            if (!empty($result['success'])) {
                $logger->info('Send successful');
                wp_send_json_success([
                    'message' => $result['message'] ?? __('OTP sent successfully.', 'wp-otp'),
                    'code' => $result['code'] ?? ''
                ]);
            } else {
                $logger->error('Send failed');
                wp_send_json_error([
                    'message' => $result['message'] ?? __('Failed to send OTP.', 'wp-otp'),
                    'code' => $result['code'] ?? 'send_failed'
                ]);
            }
        } catch (Exception $e) {
            $logger->exception($e, $contact ?? 'unknown', $channel ?? 'unknown');
            wp_send_json_error([
                'message' => __('An error occurred while sending OTP.', 'wp-otp'),
                'code' => 'exception'
            ]);
        }
    }

    /**
     * Handle OTP verification via AJAX.
     *
     * @return void
     */
    public function wp_otp_verify_otp()
    {
        $logger = new \WpOtp\WP_OTP_Logger();
        $logger->debug('verify_otp AJAX called');
        
        try {
            check_ajax_referer('wp_otp_nonce', 'nonce');
            $logger->debug('Nonce check passed');

            $contact = sanitize_text_field($_POST['contact'] ?? '');
            $otp = sanitize_text_field($_POST['otp'] ?? '');
            
            $logger->debug('Contact: ' . $contact . ', OTP: ' . $otp);

            if (empty($contact) || empty($otp)) {
                $logger->warning('Missing contact or OTP');
                wp_send_json_error([
                    'message' => __('Both contact and OTP are required.', 'wp-otp'),
                    'code' => 'missing_fields'
                ]);
            }

            $logger->debug('Creating manager instance');
            $manager = new \WpOtp\WP_OTP_Manager();
            $logger->debug('Manager created, calling verify_otp');
            
            $result = $manager->verify_otp($contact, $otp);
            $logger->debug('Verify result: ' . print_r($result, true));

            if (!empty($result['success'])) {
                $logger->info('Verification successful');
                wp_send_json_success([
                    'message' => $result['message'] ?? __('OTP verified successfully.', 'wp-otp'),
                    'code' => $result['code'] ?? ''
                ]);
            } else {
                $logger->error('Verification failed');
                wp_send_json_error([
                    'message' => $result['message'] ?? __('Invalid or expired OTP.', 'wp-otp'),
                    'code' => $result['code'] ?? 'verify_failed'
                ]);
            }
        } catch (Exception $e) {
            $logger->exception($e, $contact ?? 'unknown');
            wp_send_json_error([
                'message' => __('An error occurred during verification.', 'wp-otp'),
                'code' => 'exception'
            ]);
        }
    }

    /**
     * Save plugin settings via AJAX.
     *
     * @return void
     */
    public function save_settings()
    {
        check_ajax_referer('wp_otp_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'wp-otp')
            ]);
        }

        if (empty($_POST['data']) || !is_array($_POST['data'])) {
            wp_send_json_error([
                'message' => __('Invalid request data.', 'wp-otp')
            ]);
        }

        $input = wp_unslash($_POST['data']);
        $admin_page = new WP_OTP_Admin_Page();
        $sanitized = $admin_page->sanitize_settings($input);

        update_option('wp_otp_settings', $sanitized);

        wp_send_json_success([
            'message' => __('Settings saved successfully.', 'wp-otp')
        ]);
    }

    /**
     * Delete a single log.
     */
    public function delete_log()
    {
        check_ajax_referer('wp_otp_logs_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'wp-otp')
            ]);
        }

        $log_id = intval($_POST['log_id'] ?? 0);

        if (!$log_id) {
            wp_send_json_error([
                'message' => __('Invalid log ID.', 'wp-otp')
            ]);
        }

        $logger = new \WpOtp\WP_OTP_Logger();
        $result = $logger->delete_log($log_id);

        if ($result) {
            wp_send_json_success([
                'message' => __('Log deleted successfully.', 'wp-otp')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to delete log.', 'wp-otp')
            ]);
        }
    }

    /**
     * Delete multiple logs.
     */
    public function delete_logs()
    {
        check_ajax_referer('wp_otp_logs_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'wp-otp')
            ]);
        }

        $log_ids = $_POST['log_ids'] ?? [];

        if (empty($log_ids) || !is_array($log_ids)) {
            wp_send_json_error([
                'message' => __('No logs selected for deletion.', 'wp-otp')
            ]);
        }

        $logger = new \WpOtp\WP_OTP_Logger();
        $deleted_count = $logger->delete_logs($log_ids);

        if ($deleted_count > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    _n(
                        '%d log deleted successfully.',
                        '%d logs deleted successfully.',
                        $deleted_count,
                        'wp-otp'
                    ),
                    $deleted_count
                )
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to delete logs.', 'wp-otp')
            ]);
        }
    }

    /**
     * Delete all logs with optional filtering.
     */
    public function delete_all_logs()
    {
        check_ajax_referer('wp_otp_logs_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'wp-otp')
            ]);
        }

        // Build filter arguments from request
        $filter_args = [];

        if (!empty($_POST['from_date'])) {
            $filter_args['from_date'] = sanitize_text_field($_POST['from_date']) . ' 00:00:00';
        }

        if (!empty($_POST['to_date'])) {
            $filter_args['to_date'] = sanitize_text_field($_POST['to_date']) . ' 23:59:59';
        }

        if (!empty($_POST['contact'])) {
            $filter_args['contact'] = sanitize_text_field($_POST['contact']);
        }

        if (!empty($_POST['channel'])) {
            $filter_args['channel'] = sanitize_text_field($_POST['channel']);
        }

        if (!empty($_POST['event_types']) && is_array($_POST['event_types'])) {
            $filter_args['event_types'] = array_map('sanitize_text_field', $_POST['event_types']);
        }

        $logger = new \WpOtp\WP_OTP_Logger();
        $deleted_count = $logger->delete_all_logs($filter_args);

        if ($deleted_count > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    _n(
                        '%d log deleted successfully.',
                        '%d logs deleted successfully.',
                        $deleted_count,
                        'wp-otp'
                    ),
                    $deleted_count
                )
            ]);
        } else {
            wp_send_json_error([
                'message' => __('No logs found to delete.', 'wp-otp')
            ]);
        }
    }

    /**
     * Handle complete OTP verification and user authentication.
     *
     * @return void
     */
    public function wp_otp_process_user()
    {
        $logger = new \WpOtp\WP_OTP_Logger();
        $logger->debug('process_user AJAX called');
        
        try {
            check_ajax_referer('wp_otp_nonce', 'nonce');
            $logger->debug('Process user nonce check passed');

            $contact = sanitize_text_field($_POST['contact'] ?? '');
            $otp = sanitize_text_field($_POST['otp'] ?? '');
            $action_type = sanitize_text_field($_POST['actionType'] ?? 'login');
            $channel = sanitize_text_field($_POST['channel'] ?? 'email');
            
            $logger->debug('Process user - Contact: ' . $contact . ', OTP: ' . $otp . ', Action: ' . $action_type . ', Channel: ' . $channel);

            if (empty($contact) || empty($otp)) {
                $logger->warning('Missing contact or OTP for process user');
                wp_send_json_error([
                    'message' => __('Both contact and OTP are required.', 'wp-otp'),
                    'code' => 'missing_fields'
                ]);
            }

            $logger->debug('Creating manager for process user');
            $manager = new \WpOtp\WP_OTP_Manager();
            $logger->debug('Manager created, calling verify_otp');
            
            $result = $manager->verify_otp($contact, $otp);
            $logger->debug('Verify result: ' . print_r($result, true));

            if (!empty($result['success'])) {
                $logger->info('Verification successful, processing user');
                
                // Find or create user based on contact
                $user = $this->get_or_create_user($contact, $channel);
                
                if ($user) {
                    // Log the user in
                    wp_set_current_user($user->ID);
                    wp_set_auth_cookie($user->ID);
                    
                    $logger->info('User logged in successfully');
                    
                    $redirect_url = $this->get_redirect_url($action_type);
                    
                    wp_send_json_success([
                        'message' => __('Login successful!', 'wp-otp'),
                        'code' => 'login_success',
                        'redirect' => $redirect_url
                    ]);
                } else {
                    $logger->error('Failed to create/find user');
                    wp_send_json_error([
                        'message' => __('Failed to create user account.', 'wp-otp'),
                        'code' => 'user_creation_failed'
                    ]);
                }
            } else {
                $logger->error('Verification failed in process user');
                wp_send_json_error([
                    'message' => $result['message'] ?? __('Invalid or expired OTP.', 'wp-otp'),
                    'code' => $result['code'] ?? 'verify_failed'
                ]);
            }
        } catch (Exception $e) {
            $logger->exception($e, $contact ?? 'unknown', $channel ?? 'unknown');
            wp_send_json_error([
                'message' => __('An error occurred during authentication.', 'wp-otp'),
                'code' => 'exception'
            ]);
        }
    }

    /**
     * Get or create user based on contact information.
     *
     * @param string $contact
     * @param string $channel
     * @return WP_User|false
     */
    private function get_or_create_user($contact, $channel)
    {
        // First try to find existing user by email or phone
        $user = null;
        
        if ($channel === 'email') {
            $user = get_user_by('email', $contact);
        } else {
            // For SMS, we need to search by user meta
            $users = get_users([
                'meta_key' => 'phone_number',
                'meta_value' => $contact,
                'number' => 1
            ]);
            if (!empty($users)) {
                $user = $users[0];
            }
        }
        
        if ($user) {
            return $user;
        }
        
        // Create new user
        $username = $this->generate_username($contact, $channel);
        $user_data = [
            'user_login' => $username,
            'user_email' => $channel === 'email' ? $contact : '',
            'user_pass' => wp_generate_password(),
            'display_name' => $this->get_display_name($contact, $channel),
            'role' => 'subscriber'
        ];
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            $logger = new \WpOtp\WP_OTP_Logger();
            $logger->error('User creation failed: ' . $user_id->get_error_message(), $contact, $channel);
            return false;
        }
        
        // Add phone number meta if SMS
        if ($channel === 'sms') {
            update_user_meta($user_id, 'phone_number', $contact);
        }
        
        return get_user_by('id', $user_id);
    }

    /**
     * Generate username from contact.
     *
     * @param string $contact
     * @param string $channel
     * @return string
     */
    private function generate_username($contact, $channel)
    {
        $base = $channel === 'email' ? explode('@', $contact)[0] : 'user_' . $contact;
        $username = sanitize_user($base);
        
        // Ensure unique username
        $counter = 1;
        $original_username = $username;
        
        while (username_exists($username)) {
            $username = $original_username . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }

    /**
     * Get display name from contact.
     *
     * @param string $contact
     * @param string $channel
     * @return string
     */
    private function get_display_name($contact, $channel)
    {
        if ($channel === 'email') {
            $name = explode('@', $contact)[0];
            return ucfirst($name);
        } else {
            return 'User ' . substr($contact, -4);
        }
    }

    /**
     * Get redirect URL based on action type.
     *
     * @param string $action_type
     * @return string
     */
    private function get_redirect_url($action_type)
    {
        $settings = wp_otp_get_settings();
        
        if ($action_type === 'login') {
            return isset($settings['login_redirect']) ? $settings['login_redirect'] : admin_url();
        } else {
            return isset($settings['register_redirect']) ? $settings['register_redirect'] : home_url();
        }
    }
}
