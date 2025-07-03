jQuery(function ($) {
  let cooldownSeconds = parseInt(wpOtpFrontend.cooldown);
  let isCooldown = false;

  $("#wp-otp-request-form").on("submit", function (e) {
    e.preventDefault();

    if (isCooldown) {
      alert("Please wait until the cooldown finishes.");
      return;
    }

    var channel =
      $('input[name="otp_channel"]:checked').val() ||
      $('input[name="otp_channel"]').val();
    var contact = $("#otp_contact").val();
    var nonce = $('input[name="nonce"]').val();

    $.ajax({
      url: wpOtpFrontend.ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        action: "wp_otp_send_otp",
        nonce: nonce,
        channel: channel,
        contact: contact,
      },
      success: function (response) {
        if (response.success) {
          $("#wp-otp-initial-section").hide();
          $("#wp-otp-verification-section").show();
          startCooldown();
        } else {
          alert(response.data.message);
        }
      },
      error: function () {
        alert("Error sending OTP.");
      },
    });
  });

  $("#wp-otp-change-contact-btn").on("click", function () {
    $("#wp-otp-verification-section").show();
    $("#wp-otp-initial-section").show();
  });

  $("#wp-otp-verify-btn").on("click", function () {
    var otp = $("#wp_otp_input").val();
    var contact = $("#otp_contact").val();
    var nonce = $('input[name="nonce"]').val();
    var channel =
      $('input[name="otp_channel"]:checked').val() ||
      $('input[name="otp_channel"]').val();

    $.ajax({
      url: wpOtpFrontend.ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        action: "wp_otp_verify_otp",
        nonce: nonce,
        contact: contact,
        otp: otp,
        channel: channel,
      },
      success: function (response) {
        alert(response.data.message);
        if (response.success) {
          location.reload();
        }
      },
      error: function () {
        alert("Error verifying OTP.");
      },
    });
  });

  $("#wp-otp-resend-btn").on("click", function () {
    $("#wp-otp-request-form").submit();
  });

  function startCooldown() {
    let timeLeft = cooldownSeconds;
    isCooldown = true;

    $("#wp-otp-resend-btn").prop("disabled", true);
    $("#wp-otp-cooldown-timer").text(timeLeft + "s");

    let timer = setInterval(function () {
      timeLeft--;
      $("#wp-otp-cooldown-timer").text(timeLeft + "s");
      if (timeLeft <= 0) {
        clearInterval(timer);
        $("#wp-otp-resend-btn").prop("disabled", false);
        $("#wp-otp-cooldown-timer").text("");
        isCooldown = false;
      }
    }, 1000);
  }
});
