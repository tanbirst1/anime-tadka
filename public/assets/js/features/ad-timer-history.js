/**
 * Ad Timer — dynamic countdown
 * Reads config from <body data-ad-timer-enabled data-ad-timer-minutes>
 */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var body = document.body;
    var timerEnabled = body.getAttribute('data-ad-timer-enabled') === '1';
    var timerMinutes = parseInt(body.getAttribute('data-ad-timer-minutes'), 10) || 0;
    if (timerEnabled && timerMinutes > 0) {
      startAdTimer(timerMinutes);
    } else {
      var banner = document.getElementById('sa-friendly-trial-banner');
      if (banner) banner.style.display = 'none';
    }
    function startAdTimer(minutes) {
      var timerEl = document.getElementById('sa-big-timer');
      var verifyBtn = document.getElementById('sa-verify-now-btn');
      if (!timerEl) return;
      var STORAGE_KEY = 'sa_ad_timer_end';
      var totalMs = minutes * 60 * 1000;
      var endTime = null;
      var stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        var parsed = parseInt(stored, 10);
        if (parsed > Date.now()) endTime = parsed;
      }
      if (!endTime) {
        endTime = Date.now() + totalMs;
        localStorage.setItem(STORAGE_KEY, String(endTime));
      }
      if (verifyBtn) {
        verifyBtn.addEventListener('click', function () {
          endTime = Date.now() + totalMs;
          localStorage.setItem(STORAGE_KEY, String(endTime));
          updateDisplay();
        });
      }
      function updateDisplay() {
        var remaining = endTime - Date.now();
        if (remaining <= 0) {
          timerEl.textContent = '00:00';
          endTime = Date.now() + totalMs;
          localStorage.setItem(STORAGE_KEY, String(endTime));
        }
        var m = Math.floor(remaining / 60000);
        var s = Math.floor((remaining % 60000) / 1000);
        timerEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
      }
      updateDisplay();
      setInterval(updateDisplay, 1000);
    }
  });
})();
