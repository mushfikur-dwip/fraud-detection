/**
 * Device Fingerprinting Script
 * Collects browser and device information for fraud detection
 */
(function ($) {
  "use strict";

  // Wait for DOM ready
  $(document).ready(function () {
    collectFingerprint();
  });

  /**
   * Collect device fingerprint data
   */
  function collectFingerprint() {
    // Screen resolution
    var screenData =
      screen.width + "x" + screen.height + "x" + screen.colorDepth;
    setCookie("fraud_detection_screen", screenData, 365);

    // Timezone offset
    var timezone = new Date().getTimezoneOffset();
    setCookie("fraud_detection_tz", timezone, 365);

    // Canvas fingerprint
    var canvas = getCanvasFingerprint();
    if (canvas) {
      setCookie("fraud_detection_canvas", canvas, 365);
    }

    // WebGL fingerprint
    var webgl = getWebGLFingerprint();
    if (webgl) {
      setCookie("fraud_detection_webgl", webgl, 365);
    }

    // Browser plugins
    var plugins = getPlugins();
    setCookie("fraud_detection_plugins", plugins, 365);

    // Fonts detection
    var fonts = detectFonts();
    setCookie("fraud_detection_fonts", fonts, 365);

    // Hardware concurrency (CPU cores)
    if (navigator.hardwareConcurrency) {
      setCookie("fraud_detection_cores", navigator.hardwareConcurrency, 365);
    }

    // Device memory
    if (navigator.deviceMemory) {
      setCookie("fraud_detection_memory", navigator.deviceMemory, 365);
    }

    // Touch support
    var touchSupport = "ontouchstart" in window || navigator.maxTouchPoints > 0;
    setCookie("fraud_detection_touch", touchSupport ? "1" : "0", 365);
  }

  /**
   * Get canvas fingerprint
   */
  function getCanvasFingerprint() {
    try {
      var canvas = document.createElement("canvas");
      var ctx = canvas.getContext("2d");

      if (!ctx) return null;

      canvas.height = 50;
      canvas.width = 200;

      ctx.textBaseline = "top";
      ctx.font = "14px Arial";
      ctx.textBaseline = "alphabetic";
      ctx.fillStyle = "#f60";
      ctx.fillRect(125, 1, 62, 20);
      ctx.fillStyle = "#069";
      ctx.fillText("FraudDetect", 2, 15);
      ctx.fillStyle = "rgba(102, 204, 0, 0.7)";
      ctx.fillText("FraudDetect", 4, 17);

      var dataURL = canvas.toDataURL();
      return hashCode(dataURL);
    } catch (e) {
      return null;
    }
  }

  /**
   * Get WebGL fingerprint
   */
  function getWebGLFingerprint() {
    try {
      var canvas = document.createElement("canvas");
      var gl =
        canvas.getContext("webgl") || canvas.getContext("experimental-webgl");

      if (!gl) return null;

      var debugInfo = gl.getExtension("WEBGL_debug_renderer_info");
      if (!debugInfo) return null;

      var vendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL);
      var renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);

      return hashCode(vendor + "|" + renderer);
    } catch (e) {
      return null;
    }
  }

  /**
   * Get browser plugins
   */
  function getPlugins() {
    var plugins = [];

    if (navigator.plugins) {
      for (var i = 0; i < navigator.plugins.length; i++) {
        plugins.push(navigator.plugins[i].name);
      }
    }

    return hashCode(plugins.join(","));
  }

  /**
   * Detect installed fonts
   */
  function detectFonts() {
    var baseFonts = ["monospace", "sans-serif", "serif"];
    var testFonts = [
      "Arial",
      "Verdana",
      "Times New Roman",
      "Courier New",
      "Georgia",
      "Palatino",
      "Garamond",
      "Bookman",
      "Comic Sans MS",
      "Trebuchet MS",
      "Impact",
    ];

    var detectedFonts = [];

    var canvas = document.createElement("canvas");
    var ctx = canvas.getContext("2d");

    if (!ctx) return hashCode("");

    var width = 100;
    var height = 100;
    canvas.width = width;
    canvas.height = height;

    var baseFontSizes = {};
    baseFonts.forEach(function (baseFont) {
      ctx.font = "72px " + baseFont;
      ctx.fillText("mmmmmmmmmmlli", 0, 50);
      baseFontSizes[baseFont] = ctx.measureText("mmmmmmmmmmlli").width;
    });

    testFonts.forEach(function (font) {
      var detected = false;
      baseFonts.forEach(function (baseFont) {
        ctx.font = "72px " + font + ", " + baseFont;
        var currentWidth = ctx.measureText("mmmmmmmmmmlli").width;
        if (currentWidth !== baseFontSizes[baseFont]) {
          detected = true;
        }
      });
      if (detected) {
        detectedFonts.push(font);
      }
    });

    return hashCode(detectedFonts.join(","));
  }

  /**
   * Simple hash function
   */
  function hashCode(str) {
    var hash = 0;
    if (str.length === 0) return hash;

    for (var i = 0; i < str.length; i++) {
      var char = str.charCodeAt(i);
      hash = (hash << 5) - hash + char;
      hash = hash & hash; // Convert to 32bit integer
    }

    return Math.abs(hash).toString(36);
  }

  /**
   * Set cookie
   */
  function setCookie(name, value, days) {
    var expires = "";
    if (days) {
      var date = new Date();
      date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
      expires = "; expires=" + date.toUTCString();
    }

    var secure = window.location.protocol === "https:" ? "; secure" : "";
    document.cookie =
      name +
      "=" +
      (value || "") +
      expires +
      "; path=/" +
      secure +
      "; SameSite=Lax";
  }
})(jQuery);
