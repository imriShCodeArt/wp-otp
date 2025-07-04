jQuery(function ($) {
  let cooldownSeconds = parseInt(wpOtpFrontend.cooldown);
  let isCooldown = false;

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
        alert("An error occurred.");
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
      alert("Please wait until the cooldown finishes.");
      return;
    }

    selectors.channelSection.removeClass("d-flex").hide();

    ajaxRequest({
      action: "wp_otp_send_otp",
      data: {
        channel: getSelectedChannel(),
        contact: selectors.contactInput.val(),
      },
      onSuccess: () => {
        toggleSections({ initial: false, verification: true });
        startCooldown();
      },
    });
  }

  function handleVerifyClick() {
    ajaxRequest({
      action: "wp_otp_verify_otp",
      data: {
        contact: selectors.contactInput.val(),
        otp: selectors.otpInput.val(),
        channel: getSelectedChannel(),
      },
      onSuccess: (response) => {
        alert(response.data.message);
        location.reload();
      },
    });
  }

  function handleChangeContact() {
    toggleSections({ initial: true, verification: false, channel: true });
  }

  function handleResendClick() {
    selectors.form.trigger("submit");
  }

  function handleChannelChange() {
    const channel = getSelectedChannel()?.toLowerCase();
    if (channel === "sms") {
      selectors.otpContactLabel.text("Phone Number:");
    } else {
      selectors.otpContactLabel.text("Email Address:");
    }
  }

  // Event Bindings
  selectors.form.on("submit", handleFormSubmit);
  selectors.changeContactBtn.on("click", handleChangeContact);
  selectors.resendBtn.on("click", handleResendClick);
  $("#wp-otp-verify-btn").on("click", handleVerifyClick);
  selectors.otpChannelInputs.on("change", handleChannelChange);
});
