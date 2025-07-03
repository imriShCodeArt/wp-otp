<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_OTP_Auth_Overrides
{

    public function __construct()
    {
        add_action('login_form', [$this, 'render_login_form']);
        add_action('register_form', [$this, 'render_register_form']);
    }

    public function render_login_form()
    {
        // Replace login form if phone-only
    }

    public function render_register_form()
    {
        // Replace registration form if phone-only
    }
}
