/**
 * WP OTP Authentication JavaScript
 * 
 * Handles OTP authentication form interactions and AJAX requests.
 */

(function($) {
    'use strict';

    // Configuration
    const CONFIG = {
        cooldownSeconds: 30,
        maxAttempts: 3,
        resendWindow: 15 * 60, // 15 minutes in seconds
    };

    // State management
    let state = {
        currentStep: 1,
        contact: '',
        channel: 'email',
        cooldownTimer: null,
        resendTimer: null,
        attempts: 0,
    };

    // DOM elements
    const elements = {
        form: $('#wp-otp-auth-form'),
        step1: $('#wp-otp-step-1'),
        step2: $('#wp-otp-step-2'),
        contactInput: $('#wp_otp_contact'),
        otpInput: $('#wp_otp_otp'),
        channelRadios: $('input[name="channel"]'),
        sendBtn: $('#wp-otp-send-btn'),
        verifyBtn: $('#wp-otp-verify-btn'),
        backBtn: $('#wp-otp-back-btn'),
        resendBtn: $('#wp-otp-resend-btn'),
        resendTimer: $('#wp-otp-resend-timer'),
        loading: $('#wp-otp-loading'),
        errorContainer: $('#wp_otp-error-container'),
        successContainer: $('#wp-otp-success-container'),
        errorMessage: $('#wp_otp_error_message'),
        successMessage: $('#wp_otp_success_message'),
        contactError: $('#wp_otp_contact_error'),
        otpError: $('#wp_otp_otp_error'),
        sendMessage: $('#wp_otp_send_message'),
        verifyMessage: $('#wp_otp_verify_message'),
    };

    /**
     * Initialize the authentication form
     */
    function init() {
        bindEvents();
        setupChannelDetection();
        setupOTPInput();
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Send OTP button
        elements.sendBtn.on('click', handleSendOTP);

        // Verify OTP button
        elements.verifyBtn.on('click', handleVerifyOTP);

        // Back button
        elements.backBtn.on('click', goToStep1);

        // Resend button
        elements.resendBtn.on('click', handleResendOTP);

        // Channel selection
        elements.channelRadios.on('change', handleChannelChange);

        // Form submission prevention
        elements.form.on('submit', function(e) {
            e.preventDefault();
        });

        // Enter key handling
        elements.contactInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                handleSendOTP();
            }
        });

        elements.otpInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                handleVerifyOTP();
            }
        });
    }

    /**
     * Setup automatic channel detection based on input
     */
    function setupChannelDetection() {
        elements.contactInput.on('input', function() {
            const value = $(this).val().trim();
            if (value.includes('@')) {
                $('input[name="channel"][value="email"]').prop('checked', true);
                state.channel = 'email';
            } else if (value.match(/^\d+$/)) {
                $('input[name="channel"][value="sms"]').prop('checked', true);
                state.channel = 'sms';
            }
        });
    }

    /**
     * Setup OTP input formatting
     */
    function setupOTPInput() {
        elements.otpInput.on('input', function() {
            // Only allow numbers
            $(this).val($(this).val().replace(/[^0-9]/g, '').slice(0, 6));
        });
    }

    /**
     * Handle channel selection change
     */
    function handleChannelChange() {
        state.channel = $('input[name="channel"]:checked').val();
    }

    /**
     * Handle send OTP request
     */
    function handleSendOTP() {
        const contact = elements.contactInput.val().trim();
        
        if (!contact) {
            showError('Please enter your email or phone number.', 'contact');
            return;
        }

        if (!isValidContact(contact)) {
            showError('Please enter a valid email address or phone number.', 'contact');
            return;
        }

        state.contact = contact;
        state.channel = $('input[name="channel"]:checked').val();

        showLoading(true);
        hideMessages();

        $.ajax({
            url: wpOtpAuth.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wp_otp_auth_send',
                nonce: wpOtpAuth.nonce,
                contact: contact,
                channel: state.channel
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showSuccess(response.data.message, 'send');
                    goToStep2();
                    startCooldown();
                } else {
                    showError(response.data.message, 'contact');
                }
            },
            error: function() {
                showLoading(false);
                showError(wpOtpAuth.strings.error, 'contact');
            }
        });
    }

    /**
     * Handle verify OTP request
     */
    function handleVerifyOTP() {
        const otp = elements.otpInput.val().trim();
        
        if (!otp) {
            showError('Please enter the OTP.', 'otp');
            return;
        }

        if (otp.length !== 6) {
            showError('Please enter a 6-digit OTP.', 'otp');
            return;
        }

        showLoading(true);
        hideMessages();

        $.ajax({
            url: wpOtpAuth.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wp_otp_auth_verify',
                nonce: wpOtpAuth.nonce,
                contact: state.contact,
                otp: otp
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showSuccess(response.data.message, 'verify');
                    
                    // Redirect after successful verification
                    setTimeout(function() {
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            window.location.reload();
                        }
                    }, 1500);
                } else {
                    showError(response.data.message, 'otp');
                    state.attempts++;
                    
                    if (state.attempts >= CONFIG.maxAttempts) {
                        disableVerifyButton();
                    }
                }
            },
            error: function() {
                showLoading(false);
                showError(wpOtpAuth.strings.error, 'otp');
            }
        });
    }

    /**
     * Handle resend OTP request
     */
    function handleResendOTP() {
        if (state.cooldownTimer) {
            return;
        }

        showLoading(true);
        hideMessages();

        $.ajax({
            url: wpOtpAuth.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wp_otp_auth_send',
                nonce: wpOtpAuth.nonce,
                contact: state.contact,
                channel: state.channel
            },
            success: function(response) {
                showLoading(false);
                
                if (response.success) {
                    showSuccess(response.data.message, 'send');
                    startCooldown();
                } else {
                    showError(response.data.message, 'otp');
                }
            },
            error: function() {
                showLoading(false);
                showError(wpOtpAuth.strings.error, 'otp');
            }
        });
    }

    /**
     * Go to step 1 (contact input)
     */
    function goToStep1() {
        state.currentStep = 1;
        elements.step1.addClass('active');
        elements.step2.removeClass('active');
        elements.contactInput.focus();
        hideMessages();
        resetState();
    }

    /**
     * Go to step 2 (OTP verification)
     */
    function goToStep2() {
        state.currentStep = 2;
        elements.step1.removeClass('active');
        elements.step2.addClass('active');
        elements.otpInput.focus();
        hideMessages();
    }

    /**
     * Start cooldown timer for resend button
     */
    function startCooldown() {
        let seconds = CONFIG.cooldownSeconds;
        
        elements.resendBtn.prop('disabled', true);
        elements.resendTimer.text(`Resend available in ${seconds} seconds`);
        
        state.cooldownTimer = setInterval(function() {
            seconds--;
            
            if (seconds <= 0) {
                clearInterval(state.cooldownTimer);
                state.cooldownTimer = null;
                elements.resendBtn.prop('disabled', false);
                elements.resendTimer.text('');
            } else {
                elements.resendTimer.text(`Resend available in ${seconds} seconds`);
            }
        }, 1000);
    }

    /**
     * Disable verify button after max attempts
     */
    function disableVerifyButton() {
        elements.verifyBtn.prop('disabled', true).text('Too many attempts');
        setTimeout(function() {
            goToStep1();
        }, 3000);
    }

    /**
     * Reset form state
     */
    function resetState() {
        state.attempts = 0;
        elements.otpInput.val('');
        elements.verifyBtn.prop('disabled', false).text(wpOtpAuth.strings.verifyOtp);
        
        if (state.cooldownTimer) {
            clearInterval(state.cooldownTimer);
            state.cooldownTimer = null;
        }
        
        elements.resendBtn.prop('disabled', false);
        elements.resendTimer.text('');
    }

    /**
     * Validate contact format
     */
    function isValidContact(contact) {
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (emailRegex.test(contact)) {
            return true;
        }

        // Phone validation (basic)
        const phoneRegex = /^\d{10,15}$/;
        return phoneRegex.test(contact.replace(/\D/g, ''));
    }

    /**
     * Show loading state
     */
    function showLoading(show) {
        if (show) {
            elements.loading.show();
            elements.sendBtn.prop('disabled', true);
            elements.verifyBtn.prop('disabled', true);
        } else {
            elements.loading.hide();
            elements.sendBtn.prop('disabled', false);
            elements.verifyBtn.prop('disabled', false);
        }
    }

    /**
     * Show error message
     */
    function showError(message, field = '') {
        elements.errorMessage.text(message);
        elements.errorContainer.show();
        
        if (field === 'contact') {
            elements.contactError.text(message).addClass('show');
        } else if (field === 'otp') {
            elements.otpError.text(message).addClass('show');
        }
    }

    /**
     * Show success message
     */
    function showSuccess(message, step = '') {
        elements.successMessage.text(message);
        elements.successContainer.show();
        
        if (step === 'send') {
            elements.sendMessage.text(message).addClass('success').show();
        } else if (step === 'verify') {
            elements.verifyMessage.text(message).addClass('success').show();
        }
    }

    /**
     * Hide all messages
     */
    function hideMessages() {
        elements.errorContainer.hide();
        elements.successContainer.hide();
        elements.sendMessage.removeClass('success error info').hide();
        elements.verifyMessage.removeClass('success error info').hide();
        elements.contactError.removeClass('show');
        elements.otpError.removeClass('show');
    }

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        init();
    });

})(jQuery); 