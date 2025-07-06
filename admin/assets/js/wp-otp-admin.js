jQuery(function ($) {
  $("#wp-otp-export-logs").on("click", function (e) {
    e.preventDefault();

    let params = new URLSearchParams(window.location.search);
    params.set("action", "wp_otp_download_logs_csv");

    window.location.href = ajaxurl + "?" + params.toString();
  });
});
