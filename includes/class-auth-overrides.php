<?php

namespace WpOtp;

use WpOtp\WP_OTP_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_Auth_Overrides
 *
 * Handles OTP-only authentication, bypassing WordPress default login.
 */
class WP_OTP_Auth_Overrides
{
    /**
     * @var WP_OTP_Manager
     */
    protected $otp_manager;

    /**
     * @var WP_OTP_Repository
     */
    protected $repository;

    /**
     * @var WP_OTP_Logger
     */
    protected $logger;

    /**
     * Session key for OTP authentication
     */
    const OTP_SESSION_KEY = 'wp_otp_auth_session';

    public function __construct()
    {
        $this->otp_manager = new WP_OTP_Manager();
        $this->repository = new WP_OTP_Repository();
        $this->logger = new WP_OTP_Logger();

        // Always register AJAX handlers for OTP send/verify
        add_action('wp_ajax_nopriv_wp_otp_auth_send', [$this, 'handle_auth_send_otp']);
        add_action('wp_ajax_wp_otp_auth_send', [$this, 'handle_auth_send_otp']);
        add_action('wp_ajax_nopriv_wp_otp_auth_verify', [$this, 'handle_auth_verify_otp']);
        add_action('wp_ajax_wp_otp_auth_verify', [$this, 'handle_auth_verify_otp']);

        // Only enable OTP-only auth if the setting is enabled
        if ($this->is_otp_only_auth_enabled()) {
            $this->init_hooks();
        }
    }

    /**
     * Initialize WordPress hooks for OTP authentication.
     */
    private function init_hooks()
    {
        // Override login form
        add_action('login_init', [$this, 'override_wp_login_ui']);
        add_filter('authenticate', [$this, 'maybe_authenticate_user'], 30, 3);
        add_action('wp_logout', [$this, 'clear_otp_session']);
        
        // Override registration
        add_action('register_form', [$this, 'override_registration_form']);
        add_action('user_register', [$this, 'handle_user_registration']);
        
        // Redirect after login
        add_filter('login_redirect', [$this, 'handle_login_redirect'], 10, 3);
        
        // Prevent default WordPress login form submission only in OTP-only mode
        if ($this->is_otp_only_auth_enabled()) {
            add_action('login_form', [$this, 'prevent_default_login'], 5);
        }
    }

    /**
     * Check if OTP-only authentication is enabled.
     *
     * @return bool
     */
    private function is_otp_only_auth_enabled()
    {
        $settings = wp_otp_get_settings();
        return isset($settings['phone_only_auth']) && $settings['phone_only_auth'] === '1';
    }

    /**
     * Prevent default WordPress login form from being rendered.
     */
    public function prevent_default_login()
    {
        // Remove default WordPress login form
        remove_action('login_form', 'wp_login_form');
    }

    /**
     * Override WordPress login UI with OTP form.
     */
    public function override_wp_login_ui()
    {
        // Only override if we're on the login page
        if (!isset($_GET['action']) || $_GET['action'] === 'login') {
            if ($this->is_otp_only_auth_enabled()) {
                // OTP-only mode: Remove default WordPress login form completely
                remove_action('login_form', 'wp_login_form');
                add_action('login_form', [$this, 'render_otp_login_form']);
            }
            // Note: In dual mode (checkbox unchecked), we don't override anything
            // The default WordPress login form will remain untouched
        }
    }



    /**
     * Render the OTP login form.
     */
    public function render_otp_login_form()
    {
        // Enqueue necessary scripts and styles
        wp_enqueue_script('jquery');
        wp_enqueue_style(
            'wp-otp-auth-style',
            WP_OTP_URL . 'assets/css/wp-otp-auth.css',
            [],
            WP_OTP_VERSION
        );
        wp_enqueue_script(
            'wp-otp-auth-script',
            WP_OTP_URL . 'assets/js/wp-otp-auth.js',
            ['jquery'],
            WP_OTP_VERSION,
            true
        );

        wp_localize_script('wp-otp-auth-script', 'wpOtpAuth', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_otp_auth_nonce'),
            'strings' => [
                'enterContact' => __('Enter your email or phone number', 'wp-otp'),
                'enterOtp' => __('Enter the OTP sent to your contact', 'wp-otp'),
                'sendOtp' => __('Send OTP', 'wp-otp'),
                'verifyOtp' => __('Verify & Login', 'wp-otp'),
                'otpSent' => __('OTP sent successfully!', 'wp-otp'),
                'otpVerified' => __('OTP verified! Logging you in...', 'wp-otp'),
                'error' => __('An error occurred. Please try again.', 'wp-otp'),
            ]
        ]);

        // Include the login form template
        include WP_OTP_PATH . 'templates/auth-login-form.php';
    }

    /**
     * Handle OTP authentication instead of WordPress default.
     *
     * @param WP_User|WP_Error|null $user
     * @param string $username
     * @param string $password
     * @return WP_User|WP_Error|null
     */
    public function maybe_authenticate_user($user, $username, $password)
    {
        // If user is already authenticated, return
        if ($user instanceof \WP_User) {
            return $user;
        }

        // Check if this is an OTP authentication attempt
        if (isset($_POST['wp_otp_auth']) && $_POST['wp_otp_auth'] === '1') {
            $contact = sanitize_text_field($_POST['contact'] ?? '');
            $otp = sanitize_text_field($_POST['otp'] ?? '');

            if (empty($contact) || empty($otp)) {
                return new \WP_Error('invalid_otp', __('Please provide both contact and OTP.', 'wp-otp'));
            }

            // Verify OTP
            $result = $this->otp_manager->verify_otp($contact, $otp);

            if ($result['success']) {
                // Find or create user
                $user = $this->get_or_create_user($contact);
                
                if ($user instanceof \WP_User) {
                    // Set OTP session
                    $this->set_otp_session($user->ID, $contact);
                    
                    $this->logger->info(
                        "User authenticated via OTP: {$user->user_login}",
                        $contact,
                        null,
                        $user->ID
                    );
                    
                    return $user;
                } else {
                    return new \WP_Error('user_creation_failed', __('Failed to create user account.', 'wp-otp'));
                }
            } else {
                $this->logger->error(
                    "OTP authentication failed: {$result['message']}",
                    $contact,
                    null,
                    null
                );
                
                return new \WP_Error('invalid_otp', $result['message']);
            }
        }

        // If OTP-only mode is enabled and this is not an OTP auth attempt, block regular auth
        if ($this->is_otp_only_auth_enabled() && !empty($username)) {
            return new \WP_Error('otp_only_mode', __('This site requires OTP authentication. Please use the OTP login option.', 'wp-otp'));
        }

        // If not OTP auth, return original user (or null for default WordPress auth)
        return $user;
    }

    /**
     * Get existing user or create new one based on contact.
     *
     * @param string $contact
     * @return WP_User|WP_Error
     */
    private function get_or_create_user($contact)
    {
        $this->logger->debug(
            "Starting user lookup/creation process",
            $contact,
            null,
            null
        );

        // Try to find existing user by email or phone
        $user = $this->find_user_by_contact($contact);

        if ($user) {
            $this->logger->info(
                "Existing user found: {$user->user_login} (ID: {$user->ID})",
                $contact,
                null,
                $user->ID
            );
            return $user;
        }

        $this->logger->info(
            "No existing user found, creating new user",
            $contact,
            null,
            null
        );

        // Create new user
        return $this->create_user_from_contact($contact);
    }

    /**
     * Find user by email or phone number.
     *
     * @param string $contact
     * @return WP_User|null
     */
    private function find_user_by_contact($contact)
    {
        // First try by email
        if (is_email($contact)) {
            $user = get_user_by('email', $contact);
            if ($user) {
                return $user;
            }
        }

        // Then try by phone (stored in user meta)
        $users = get_users([
            'meta_key' => 'wp_otp_phone',
            'meta_value' => $contact,
            'number' => 1,
        ]);

        return !empty($users) ? $users[0] : null;
    }

    /**
     * Create new user from contact information.
     *
     * @param string $contact
     * @return WP_User|WP_Error
     */
    private function create_user_from_contact($contact)
    {
        $is_email = is_email($contact);
        
        $this->logger->info(
            "Starting user creation - Contact type: " . ($is_email ? 'email' : 'phone'),
            $contact,
            null,
            null
        );

        // Check if user registration is enabled in WordPress
        $registration_enabled = get_option('users_can_register');
        $this->logger->debug(
            "WordPress registration enabled: " . ($registration_enabled ? 'YES' : 'NO'),
            $contact,
            null,
            null
        );
        
        // Generate username
        $username = $this->generate_unique_username($contact);
        
        $this->logger->debug(
            "Generated username: {$username}",
            $contact,
            null,
            null
        );
        
        // Create user data
        $user_data = [
            'user_login' => $username,
            'user_email' => $is_email ? $contact : '',
            'user_pass' => wp_generate_password(32, true, true), // Random secure password
            'display_name' => $is_email ? $contact : $contact,
            'role' => 'subscriber',
        ];

        $this->logger->debug(
            "User data prepared for creation",
            $contact,
            null,
            null
        );

        // Temporarily enable user registration if it's disabled
        $original_registration = get_option('users_can_register');
        if (!$original_registration) {
            update_option('users_can_register', 1);
            $this->logger->info(
                "Temporarily enabled user registration for OTP user creation",
                $contact,
                null,
                null
            );
        }

        // Insert user
        $user_id = wp_insert_user($user_data);

        // Restore original registration setting
        if (!$original_registration) {
            update_option('users_can_register', $original_registration);
            $this->logger->info(
                "Restored original user registration setting",
                $contact,
                null,
                null
            );
        }

        if (is_wp_error($user_id)) {
            $this->logger->error(
                "wp_insert_user failed: " . $user_id->get_error_message(),
                $contact,
                null,
                null
            );
            return $user_id;
        }

        $this->logger->info(
            "User created successfully with ID: {$user_id}",
            $contact,
            null,
            $user_id,
            'user_created_success'
        );

        $user = get_user_by('id', $user_id);

        if (!$user) {
            $this->logger->error(
                "Failed to retrieve created user with ID: {$user_id}",
                $contact,
                null,
                null,
                'user_retrieval_failed'
            );
            return new \WP_Error('user_retrieval_failed', 'Failed to retrieve created user');
        }

        // Store contact info in user meta
        if ($is_email) {
            update_user_meta($user_id, 'wp_otp_email', $contact);
        } else {
            update_user_meta($user_id, 'wp_otp_phone', $contact);
        }

        $this->logger->info(
            "User meta updated for user ID: {$user_id}",
            $contact,
            null,
            $user_id,
            'user_meta_updated'
        );

        $this->logger->info(
            "New user created via OTP: {$username}",
            $contact,
            null,
            $user_id,
            'user_created'
        );

        return $user;
    }

    /**
     * Generate unique username from contact.
     *
     * @param string $contact
     * @return string
     */
    private function generate_unique_username($contact)
    {
        $base = is_email($contact) ? explode('@', $contact)[0] : 'user_' . preg_replace('/\D/', '', $contact);
        $username = sanitize_user($base);
        $counter = 1;
        $original_username = $username;

        while (username_exists($username)) {
            $username = $original_username . '_' . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Handle AJAX request to send OTP for authentication.
     */
    public function handle_auth_send_otp()
    {
        check_ajax_referer('wp_otp_auth_nonce', 'nonce');

        $contact = sanitize_text_field($_POST['contact'] ?? '');
        $channel = sanitize_text_field($_POST['channel'] ?? 'email');

        if (empty($contact)) {
            wp_send_json_error([
                'message' => __('Please provide your email or phone number.', 'wp-otp')
            ]);
        }

        // Validate contact format
        if (!is_email($contact) && !$this->is_valid_phone($contact)) {
            wp_send_json_error([
                'message' => __('Please provide a valid email address or phone number.', 'wp-otp')
            ]);
        }

        // Send OTP
        $result = $this->otp_manager->send_otp($contact, $channel);

        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message']
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message']
            ]);
        }
    }

    /**
     * Handle AJAX request to verify OTP for authentication.
     */
    public function handle_auth_verify_otp()
    {
        check_ajax_referer('wp_otp_auth_nonce', 'nonce');

        $contact = sanitize_text_field($_POST['contact'] ?? '');
        $otp = sanitize_text_field($_POST['otp'] ?? '');

        if (empty($contact) || empty($otp)) {
            wp_send_json_error([
                'message' => __('Please provide both contact and OTP.', 'wp-otp')
            ]);
        }

        // Log the verification attempt
        $this->logger->info(
            "OTP verification attempt started",
            $contact,
            null,
            null
        );

        // Verify OTP
        $result = $this->otp_manager->verify_otp($contact, $otp);

        if ($result['success']) {
            // Log successful OTP verification
            $this->logger->info(
                "OTP verified successfully, proceeding to user creation/login",
                $contact,
                null,
                null
            );

            // Get or create user
            $user = $this->get_or_create_user($contact);

            if (is_wp_error($user)) {
                $this->logger->error(
                    "User creation failed: " . $user->get_error_message(),
                    $contact,
                    null,
                    null
                );
                
                wp_send_json_error([
                    'message' => $user->get_error_message()
                ]);
            }

            // Log successful user creation/finding
            $this->logger->info(
                "User ready for login: {$user->user_login} (ID: {$user->ID})",
                $contact,
                null,
                $user->ID
            );

            // Log in the user
            $login_result = wp_set_current_user($user->ID);
            $cookie_result = wp_set_auth_cookie($user->ID, true);

            // Log login attempt results
            $this->logger->info(
                "Login attempt - set_current_user: " . ($login_result ? 'success' : 'failed') . 
                ", set_auth_cookie: " . ($cookie_result ? 'success' : 'failed'),
                $contact,
                null,
                $user->ID
            );

            // Set OTP session
            $this->set_otp_session($user->ID, $contact);

            // Verify user is actually logged in
            $current_user = wp_get_current_user();
            $is_logged_in = $current_user->exists() && $current_user->ID === $user->ID;

            $this->logger->debug(
                "Login verification - User logged in: " . ($is_logged_in ? 'YES' : 'NO') . 
                ", Current user ID: " . $current_user->ID . 
                ", Expected user ID: " . $user->ID,
                $contact,
                null,
                $user->ID
            );

            // Log successful authentication
            $this->logger->info(
                "User authenticated via OTP: {$user->user_login} (ID: {$user->ID})",
                $contact,
                null,
                $user->ID
            );

            wp_send_json_success([
                'message' => __('Login successful!', 'wp-otp'),
                'redirect_url' => $this->get_login_redirect_url($user),
                'user_id' => $user->ID,
                'user_login' => $user->user_login,
                'is_logged_in' => $is_logged_in,
                'debug_info' => [
                    'current_user_id' => $current_user->ID,
                    'expected_user_id' => $user->ID,
                    'login_result' => $login_result,
                    'cookie_result' => $cookie_result
                ]
            ]);
        } else {
            // Log failed authentication
            $this->logger->error(
                "OTP verification failed: {$result['message']}",
                $contact,
                null,
                null
            );

            wp_send_json_error([
                'message' => $result['message']
            ]);
        }
    }

    /**
     * Validate phone number format.
     *
     * @param string $phone
     * @return bool
     */
    private function is_valid_phone($phone)
    {
        // Basic phone validation - adjust as needed for your region
        $phone = preg_replace('/\D/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }

    /**
     * Set OTP authentication session.
     *
     * @param int $user_id
     * @param string $contact
     */
    private function set_otp_session($user_id, $contact)
    {
        $session_data = [
            'user_id' => $user_id,
            'contact' => $contact,
            'timestamp' => time(),
        ];

        wp_cache_set(self::OTP_SESSION_KEY, $session_data, '', 3600); // 1 hour
    }

    /**
     * Clear OTP session on logout.
     */
    public function clear_otp_session()
    {
        wp_cache_delete(self::OTP_SESSION_KEY);
    }

    /**
     * Get redirect URL after successful login.
     *
     * @param WP_User $user
     * @return string
     */
    private function get_login_redirect_url($user)
    {
        $redirect_to = $_REQUEST['redirect_to'] ?? '';
        
        if (!empty($redirect_to)) {
            return $redirect_to;
        }

        // Default redirect based on user role
        if (in_array('administrator', $user->roles)) {
            return admin_url();
        }

        return home_url();
    }

    /**
     * Handle login redirect.
     *
     * @param string $redirect_to
     * @param string $requested_redirect_to
     * @param WP_User $user
     * @return string
     */
    public function handle_login_redirect($redirect_to, $requested_redirect_to, $user)
    {
        if (!empty($requested_redirect_to)) {
            return $requested_redirect_to;
        }

        if (in_array('administrator', $user->roles)) {
            return admin_url();
        }

        return home_url();
    }

    /**
     * Override registration form to use OTP.
     */
    public function override_registration_form()
    {
        // This will be handled by the login form since we're doing OTP-only auth
        // Users will be created automatically when they authenticate via OTP
    }

    /**
     * Handle user registration (called when user is created via OTP).
     *
     * @param int $user_id
     */
    public function handle_user_registration($user_id)
    {
        // Additional registration logic can be added here
        // For now, the user creation is handled in get_or_create_user()
    }
}
