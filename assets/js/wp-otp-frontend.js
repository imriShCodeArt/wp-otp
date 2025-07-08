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
    contactInput: $("#otp_contact"),
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
    $.ajax({
      url: wpOtpFrontend.ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        action,
        nonce: selectors.nonceInput.val(),
        ...data,
      },
      success: function (response) {
        if (response.success) {
          onSuccess(response);
        } else {
          alert(response.data.message);
        }
      },
      error: function () {
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
      action: "wp_otp_send_otp",
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
    const channel = getSelectedChannel()?.toLowerCase();
    const actionType = wpOtpFrontend.actionType || "login";

    let contact = "";
    if (channel === "sms" && iti) {
      contact = iti.getNumber();
    } else {
      contact = $("#otp_email").val();
    }

    ajaxRequest({
      action: "wp_otp_process_user",
      data: {
        contact: contact,
        otp: selectors.otpInput.val(),
        actionType: actionType,
        channel: channel,
      },
      onSuccess: (response) => {
        if (response?.data?.message) {
          alert(response.data.message);
        } else {
          alert("No message returned from server.");
        }
        if (response?.data?.redirect) {
          window.location.href = response.data.redirect;
        }
      },
      onError: (xhr) => {
        const message =
          xhr?.responseJSON?.data?.message ||
          xhr?.statusText ||
          "Unknown error.";
        alert("AJAX error: " + message);
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
