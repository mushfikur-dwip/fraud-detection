/**
 * Admin JavaScript
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // Confirm before removing entries
    $(".fraud-detection-admin").on(
      "submit",
      "form[data-confirm]",
      function (e) {
        var message = $(this).data("confirm");
        if (!confirm(message)) {
          e.preventDefault();
          return false;
        }
      }
    );

    // Toggle forms
    $(".fraud-detection-admin").on("click", ".toggle-form", function (e) {
      e.preventDefault();
      var target = $(this).data("target");
      $(target).slideToggle();
    });

    // Close form buttons
    $(".fraud-detection-admin").on("click", ".close-form", function (e) {
      e.preventDefault();
      $(this).closest(".form-wrapper").slideUp();
    });

    // Validate phone numbers
    $("#entry_value").on("blur", function () {
      var type = $("#entry_type").val();
      var value = $(this).val();

      if (type === "phone" && value) {
        // Basic phone validation
        var phonePattern = /^[0-9\+\-\s\(\)]+$/;
        if (!phonePattern.test(value)) {
          alert("Please enter a valid phone number");
          $(this).focus();
        }
      }

      if (type === "email" && value) {
        // Basic email validation
        var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(value)) {
          alert("Please enter a valid email address");
          $(this).focus();
        }
      }
    });

    // Auto-hide notices after 5 seconds
    setTimeout(function () {
      $(".notice.is-dismissible").fadeOut();
    }, 5000);

    // Bulk actions (placeholder for future enhancement)
    $(".fraud-detection-admin").on("change", ".check-all", function () {
      $(".entry-checkbox").prop("checked", $(this).prop("checked"));
    });

    // Search on enter
    $(".fraud-detection-admin").on(
      "keypress",
      'input[type="search"]',
      function (e) {
        if (e.which === 13) {
          $(this).closest("form").submit();
        }
      }
    );

    // Tooltips (if you want to add them later)
    if (typeof $.fn.tooltip !== "undefined") {
      $(".fraud-detection-admin [data-tooltip]").tooltip();
    }
  });
})(jQuery);
