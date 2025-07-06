<?php

namespace WpOtp;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_OTP_SMS_019_Client
 *
 * Handles communication with the 019 SMS API for sending SMS messages,
 * checking balance, and generating new API tokens.
 *
 * @package WpOtp
 */
class WP_OTP_SMS_019_Client
{
    /**
     * @var string 019 API endpoint URL.
     */
    protected $api_url = 'https://019sms.co.il/api';

    /**
     * @var string Username used for API authentication.
     */
    protected $username;

    /**
     * @var string Password used for API authentication.
     */
    protected $password;

    /**
     * @var string|null API token used for authorization headers.
     */
    protected $api_token;

    /**
     * @var string Sender name to appear as SMS sender.
     */
    protected $sender_name;

    /**
     * @var WP_OTP_Logger Logger instance for writing log entries.
     */
    protected $logger;

    /**
     * WP_OTP_SMS_019_Client constructor.
     *
     * Initializes the class properties with plugin settings.
     */
    public function __construct()
    {
        $settings = wp_otp_get_settings();

        $this->username = $settings['sms_sender'] ?? '';
        $this->api_key = $settings['sms_api_key'] ?? '';
        $this->api_secret = $settings['sms_api_secret'] ?? '';

        $this->api_url = 'https://019sms.co.il/api';
        $this->logger = new WP_OTP_Logger();

        if (empty($this->api_key)) {
            $result = $this->generate_token();

            if (!empty($result['success']) && !empty($result['token'])) {
                add_action('admin_notices', function () use ($result) {
                    echo '<div class="notice notice-success is-dismissible"><p>'
                        . esc_html__('New SMS API token generated automatically:', 'wp-otp')
                        . ' <code>' . esc_html($result['token']) . '</code></p></div>';
                });
            } else {
                add_action('admin_notices', function () use ($result) {
                    echo '<div class="notice notice-error is-dismissible"><p>'
                        . esc_html__('Failed to generate SMS API token automatically.', 'wp-otp')
                        . ' ' . esc_html($result['message'] ?? '') . '</p></div>';
                });
            }
        }
    }


    /**
     * Sends an OTP SMS message to the specified phone number.
     *
     * @param string $phone   Recipient phone number.
     * @param string $message The message body to send.
     *
     * @return array {
     *     @type bool   $success Whether the message was successfully sent.
     *     @type string $message Informative message for the user.
     *     @type string $code    Machine-readable result code.
     * }
     */
    public function send_otp_sms($phone, $message)
    {
        $xml = $this->build_sms_xml($phone, $message);

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'Content-Type' => 'text/plain',
                'Authorization' => $this->api_token,
            ],
            'body' => $xml,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            $this->logger->log('sms_failed', $phone, "HTTP error: $error_msg", 'sms', get_current_user_id());
            return [
                'success' => false,
                'message' => $error_msg,
                'code' => 'http_error',
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $parsed = $this->parse_xml_response($body);

        if ($parsed['result'] === 'OK') {
            $this->logger->log('sms_sent', $phone, 'SMS sent successfully.', 'sms', get_current_user_id());
            return [
                'success' => true,
                'message' => __('SMS sent successfully.', 'wp-otp'),
                'code' => 'sms_sent',
            ];
        }

        $error_msg = $parsed['message'] ?? 'Unknown error from SMS gateway.';
        $this->logger->log('sms_failed', $phone, $error_msg, 'sms', get_current_user_id());

        return [
            'success' => false,
            'message' => $error_msg,
            'code' => 'sms_failed',
        ];
    }

    /**
     * Queries the 019 API for the SMS balance.
     *
     * @return array {
     *     @type bool   $success Whether the request succeeded.
     *     @type string $message User-friendly message.
     *     @type int    $balance Remaining SMS credits (if successful).
     *     @type string $code    Machine-readable result code.
     * }
     */
    public function get_balance()
    {
        $xml = $this->build_balance_xml();

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'Content-Type' => 'application/xml',
                'Authorization' => $this->api_token,
            ],
            'body' => $xml,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            $this->logger->log('sms_balance_failed', $this->username, "HTTP error: $error_msg", 'sms', get_current_user_id());
            return [
                'success' => false,
                'message' => $error_msg,
                'code' => 'http_error',
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $parsed = $this->parse_xml_response($body);

        if ($parsed['result'] === 'OK') {
            return [
                'success' => true,
                'balance' => $parsed['balance'] ?? 0,
                'message' => __('Balance fetched successfully.', 'wp-otp'),
                'code' => 'balance_success',
            ];
        }

        $error_msg = $parsed['message'] ?? 'Unknown error fetching balance.';
        $this->logger->log('sms_balance_failed', $this->username, $error_msg, 'sms', get_current_user_id());

        return [
            'success' => false,
            'message' => $error_msg,
            'code' => 'balance_failed',
        ];
    }

    /**
     * Generates a new API token for the 019 account.
     *
     * @return array {
     *     @type bool   $success Whether the token generation succeeded.
     *     @type string $token   New API token (if successful).
     *     @type string $message User-friendly message.
     *     @type string $code    Machine-readable result code.
     * }
     */
    public function generate_token()
    {
        $xml = $this->build_token_xml();

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'Content-Type' => 'application/xml',
            ],
            'body' => $xml,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            $this->logger->log(
                'sms_token_failed',
                $this->username,
                "HTTP error: $error_msg",
                'sms',
                get_current_user_id()
            );

            add_action('admin_notices', function () use ($error_msg) {
                echo '<div class="notice notice-error is-dismissible"><p>'
                    . esc_html__('Failed to generate SMS API token.', 'wp-otp')
                    . ' ' . esc_html($error_msg) . '</p></div>';
            });

            return [
                'success' => false,
                'message' => $error_msg,
                'code' => 'http_error',
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $parsed = $this->parse_xml_response($body);

        if ($parsed['result'] === 'OK' && !empty($parsed['apiToken'])) {
            $new_token = $parsed['apiToken'];

            // Save the token to wp_otp_settings option
            $settings = wp_otp_get_settings();
            $settings['sms_api_key'] = $new_token;
            update_option('wp_otp_settings', $settings);

            $this->logger->log(
                'sms_token_success',
                $this->username,
                'New API token generated and saved.',
                'sms',
                get_current_user_id()
            );

            add_action('admin_notices', function () use ($new_token) {
                echo '<div class="notice notice-success is-dismissible"><p>'
                    . esc_html__('New SMS API token generated and saved:', 'wp-otp')
                    . ' <code>' . esc_html($new_token) . '</code></p></div>';
            });

            return [
                'success' => true,
                'token' => $new_token,
                'message' => __('New API token generated and saved.', 'wp-otp'),
                'code' => 'token_success',
            ];
        }

        $error_msg = $parsed['message'] ?? 'Unknown error generating API token.';
        $this->logger->log(
            'sms_token_failed',
            $this->username,
            $error_msg,
            'sms',
            get_current_user_id()
        );

        add_action('admin_notices', function () use ($error_msg) {
            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html__('Failed to generate SMS API token.', 'wp-otp')
                . ' ' . esc_html($error_msg) . '</p></div>';
        });

        return [
            'success' => false,
            'message' => $error_msg,
            'code' => 'token_failed',
        ];
    }



    /**
     * Builds the XML payload for sending an SMS.
     *
     * @param string $phone   Phone number to send to.
     * @param string $message SMS message body.
     *
     * @return string XML payload.
     */
    protected function build_sms_xml($phone, $message)
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
            '<sms>' .
            '<user><username>' . esc_html($this->username) . '</username></user>' .
            '<source>' . esc_html($this->sender_name) . '</source>' .
            '<destinations><phone id="1">' . esc_html($phone) . '</phone></destinations>' .
            '<message>' . esc_html($message) . '</message>' .
            '<add_unsubscribe>3</add_unsubscribe>' .
            '</sms>';
    }

    /**
     * Builds the XML payload for checking balance.
     *
     * @return string XML payload.
     */
    protected function build_balance_xml()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
            '<balance>' .
            '<user><username>' . esc_html($this->username) . '</username></user>' .
            '</balance>';
    }

    /**
     * Builds the XML payload for generating a new API token.
     *
     * @return string XML payload.
     */
    protected function build_token_xml()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
            '<getApiToken>' .
            '<user><username>' . esc_html($this->username) . '</username>' .
            '<password>' . esc_html($this->password) . '</password></user>' .
            '<username>' . esc_html($this->username) . '</username>' .
            '<action>new</action>' .
            '</getApiToken>';
    }

    /**
     * Parses the XML response string into an associative array.
     *
     * @param string $xml XML string returned from the API.
     *
     * @return array Associative array containing response data.
     */
    protected function parse_xml_response($xml)
    {
        $result = [];

        libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);

        if (!$parsed) {
            return [
                'result' => 'ERROR',
                'message' => __('Invalid XML response.', 'wp-otp'),
            ];
        }

        foreach ($parsed as $key => $value) {
            $result[$key] = (string) $value;
        }

        if (!isset($result['result'])) {
            $result['result'] = 'ERROR';
        }

        return $result;
    }
}
