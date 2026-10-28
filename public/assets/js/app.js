(function () {
  'use strict';

  // ---- Collapsible / responsive sidebar -------------------------------------
  var shell = document.getElementById('appShell');
  var toggle = document.getElementById('sidebarToggle');
  var backdrop = document.getElementById('sidebarBackdrop');
  var mobile = window.matchMedia('(max-width: 991.98px)');
  if (shell && toggle) {
    try { if (!mobile.matches && localStorage.getItem('sidebarCollapsed') === '1') shell.classList.add('collapsed'); } catch (e) {}
    toggle.addEventListener('click', function () {
      if (mobile.matches) {
        shell.classList.toggle('sidebar-open');
      } else {
        shell.classList.toggle('collapsed');
        try { localStorage.setItem('sidebarCollapsed', shell.classList.contains('collapsed') ? '1' : '0'); } catch (e) {}
      }
    });
    if (backdrop) backdrop.addEventListener('click', function () { shell.classList.remove('sidebar-open'); });
  }

  // ---- Confirmation dialog: <form data-confirm="Are you sure?"> -------------
  var modalEl = document.getElementById('confirmModal');
  if (modalEl && window.bootstrap) {
    var modal = new bootstrap.Modal(modalEl);
    var pendingForm = null;
    document.addEventListener('submit', function (ev) {
      var form = ev.target;
      if (form.dataset && form.dataset.confirm && !form.dataset.confirmed) {
        ev.preventDefault();
        pendingForm = form;
        document.getElementById('confirmModalBody').textContent = form.dataset.confirm;
        modal.show();
      }
    }, true);
    document.getElementById('confirmModalOk').addEventListener('click', function () {
      if (pendingForm) { pendingForm.dataset.confirmed = '1'; modal.hide(); pendingForm.requestSubmit(); }
    });
  }

  // ---- Loading state on submit: <form data-loading> -------------------------
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (ev.defaultPrevented || !form.hasAttribute || !form.hasAttribute('data-loading')) return;
    var btn = form.querySelector('button[type=submit]');
    if (btn) {
      setTimeout(function () {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Please wait...';
      }, 0);
    }
  });

  // ---- Auto-dismiss success notices -----------------------------------------
  document.querySelectorAll('.flash-message.alert-success').forEach(function (el) {
    setTimeout(function () {
      if (window.bootstrap) bootstrap.Alert.getOrCreateInstance(el).close();
    }, 5000);
  });

  // ---- Charts (data comes from escaped data-* attributes; no inline JS) -----
  if (window.Chart) {
    document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
      var type = canvas.dataset.chart;
      var labels = JSON.parse(canvas.dataset.labels || '[]');
      var values = JSON.parse(canvas.dataset.values || '[]');
      var currency = canvas.dataset.currency === '1';
      var isBar = type === 'bar';
      new Chart(canvas, {
        type: type,
        data: {
          labels: labels,
          datasets: [{
            label: canvas.dataset.label || '',
            data: values,
            backgroundColor: isBar ? '#2563eb' : ['#f59e0b', '#16a34a', '#64748b', '#dc2626', '#9333ea'],
            borderRadius: isBar ? 6 : 0,
            borderWidth: isBar ? 0 : 2
          }]
        },
        options: {
          maintainAspectRatio: false,
          plugins: { legend: { display: !isBar, position: 'bottom' } },
          scales: isBar ? { y: { beginAtZero: true, ticks: { callback: function (v) { return currency ? '\u20B1' + v : v; } } } } : {}
        }
      });
    });
  }
})();

// ---- Part 4 helpers: print button, auto-submit selects, passenger mode toggle ----
(function () {
  'use strict';
  document.addEventListener('click', function (ev) {
    if (ev.target.closest && ev.target.closest('[data-print]')) { window.print(); }
  });
  document.addEventListener('change', function (ev) {
    var el = ev.target;
    if (el.matches && el.matches('select[data-autosubmit]') && el.form) { el.form.submit(); }
  });
  var existing = document.getElementById('existingBox');
  var fresh = document.getElementById('newBox');
  if (existing && fresh) {
    var sync = function () {
      var isNew = document.getElementById('pm_new').checked;
      existing.style.display = isNew ? 'none' : '';
      fresh.style.display = isNew ? '' : 'none';
    };
    document.querySelectorAll('input[name=passenger_mode]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  }
})();
