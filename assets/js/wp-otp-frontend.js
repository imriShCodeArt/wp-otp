jQuery(function ($) {
  let cooldownSeconds = parseInt(wpOtpFrontend.cooldown);
  let isCooldown = false;
  let iti = null;

  const selectors = {
    form: $("#wp-otp-request-form"),
    initialSection: $("#wp-otp-initial-section"),
    verificationSection: $("#wp-otp-verification-section"),
    channelSection: $("#wp-otp-channel-section"),
    resendBtn: $("#wp-otp-resend-btn"),
    cooldownTimer: $("#wp-otp-cooldown-timer"),
    changeContactBtn: $("#wp-otp-change-contact-btn"),
    otpInput: $("#wp_otp_input"),
    contactInputs: $("#otp_phone, #otp_email"),
    nonceInput: $('input[name="nonce"]'),
    otpChannelInputs: $('input[name="otp_channel"]'),
    otpContactLabel: $("#otp_contact_label"),
  };

  function getSelectedChannel() {
    return (
      selectors.otpChannelInputs.filter(":checked").val() ||
      selectors.otpChannelInputs.val()
    );
  }

  function toggleSections({
    initial = false,
    verification = false,
    channel = false,
  }) {
    selectors.initialSection.toggle(initial);
    selectors.verificationSection.toggle(verification);
    selectors.channelSection.toggle(channel);
  }

  function ajaxRequest({ action, data, onSuccess, onError }) {
    // Use different nonce for authentication actions
    let nonceValue = selectors.nonceInput.val();
    if (action === "wp_otp_auth_verify" || action === "wp_otp_auth_send") {
      // For authentication actions, we need to create the auth nonce dynamically
      // since it's not available in the form
      nonceValue = wpOtpFrontend.authNonce || selectors.nonceInput.val();
    }
    
    $.ajax({
      url: wpOtpFrontend.ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        action,
        nonce: nonceValue,
        ...data,
      },
      success: function (response) {
        if (response.success) {
          onSuccess(response);
        } else {
          console.log("AJAX FAILURE", response);
          alert(response.data?.message || "Verification failed.");
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.log("AJAX ERROR", jqXHR.responseText, textStatus, errorThrown);
        alert(wpOtpFrontend.errorOccured);
        if (onError) onError();
      },
    });
  }

  function startCooldown() {
    let timeLeft = cooldownSeconds;
    isCooldown = true;
    selectors.resendBtn.prop("disabled", true);
    selectors.cooldownTimer.text(`${timeLeft}s`);
    const timer = setInterval(() => {
      timeLeft--;
      selectors.cooldownTimer.text(`${timeLeft}s`);
      if (timeLeft <= 0) {
        clearInterval(timer);
        selectors.resendBtn.prop("disabled", false);
        selectors.cooldownTimer.text("");
        isCooldown = false;
      }
    }, 1000);
  }

  function handleFormSubmit(e) {
    e.preventDefault();
    if (isCooldown) {
      alert(wpOtpFrontend.cooldownMessage);
      return;
    }

    selectors.channelSection.removeClass("d-flex").hide();

    let contactVal = "";

    if (getSelectedChannel()?.toLowerCase() === "sms" && iti) {
      contactVal = iti.getNumber();
    } else {
      contactVal = $("#otp_email").val();
    }

    if (!contactVal) {
      alert(wpOtpFrontend.contactInfoRequired);
      return;
    }

    ajaxRequest({
      action: "wp_otp_auth_send",
      data: {
        channel: getSelectedChannel(),
        contact: contactVal,
      },
      onSuccess: () => {
        toggleSections({ initial: false, verification: true });
        startCooldown();
      },
    });
  }

  function handleVerifyClick() {
    let contactVal = "";

    if (getSelectedChannel()?.toLowerCase() === "sms" && iti) {
      contactVal = iti.getNumber();
    } else {
      contactVal = $("#otp_email").val();
    }

    console.log("CONTACT:", contactVal);
    console.log("OTP:", selectors.otpInput.val());
    console.log("CHANNEL:", getSelectedChannel());

    ajaxRequest({
      action: "wp_otp_auth_verify",
      data: {
        contact: contactVal,
        otp: selectors.otpInput.val(),
        channel: getSelectedChannel(),
      },
      onSuccess: (response) => {
        console.log('OTP verification response:', response);
        
        if (response.data && response.data.is_logged_in) {
          console.log('User successfully logged in:', response.data.user_login);
          
          // Show success message
          alert(response.data.message || 'Login successful!');
          
          // Redirect after successful verification
          setTimeout(function() {
            if (response.data.redirect_url) {
              console.log('Redirecting to:', response.data.redirect_url);
              window.location.href = response.data.redirect_url;
            } else {
              console.log('No redirect URL, reloading page');
              window.location.reload();
            }
          }, 1500);
        } else {
          console.warn('User login may have failed:', response.data?.debug_info);
          alert(response.data?.message || 'Login successful!');
          location.reload();
        }
      },
    });
  }

  function handleChangeContact() {
    toggleSections({
      initial: true,
      verification: false,
      channel: true,
    });
  }

  function handleResendClick() {
    selectors.form.trigger("submit");
  }

  function handleChannelChange() {
    const channel = getSelectedChannel()?.toLowerCase();

    $("#otp_phone, #otp_email").hide();

    if (channel === "sms") {
      selectors.otpContactLabel.text(wpOtpFrontend.phoneNumber);

      $("#otp_phone").show().attr("placeholder", "e.g. 501234567");
      $("#otp_email").val(""); // clear email field

      if (!iti) {
        iti = window.intlTelInput(document.querySelector("#otp_phone"), {
          initialCountry: "il",
          separateDialCode: true,
          utilsScript:
            "https://cdn.jsdelivr.net/npm/intl-tel-input@19.5.7/build/js/utils.js",
        });
      }

      $("#otp_phone")
        .off("input")
        .on("input", function () {
          const raw = this.value.replace(/[^\d]/g, "");
          let minLength = 9;
          let maxLength = 9;

          let truncated = raw.slice(0, maxLength);
          this.value = truncated;

          if (truncated.length < minLength) {
            this.setCustomValidity(
              wpOtpFrontend.phoneLength.replace("%d", minLength)
            );
          } else if (!truncated.startsWith("5")) {
            this.setCustomValidity(wpOtpFrontend.phoneStartsWith5);
          } else {
            this.setCustomValidity("");
          }

          this.reportValidity();
        });
    } else {
      selectors.otpContactLabel.text(wpOtpFrontend.emailAddress);

      $("#otp_email").show().attr("placeholder", "you@example.com");
      $("#otp_phone").val(""); // clear phone field

      if (iti) {
        iti.destroy();
        iti = null;
      }
    }
  }

  handleChannelChange();

  // Event Bindings
  selectors.form.on("submit", handleFormSubmit);
  selectors.changeContactBtn.on("click", handleChangeContact);
  selectors.resendBtn.on("click", handleResendClick);
  $("#wp-otp-verify-btn").on("click", handleVerifyClick);
  selectors.otpChannelInputs.on("change", handleChannelChange);
});
