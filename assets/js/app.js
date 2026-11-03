(function () {
  var body = document.body;

  // Mobile sidebar
  var menuBtn = document.getElementById('menu-btn');
  var scrim = document.getElementById('scrim');
  if (menuBtn) {
    menuBtn.addEventListener('click', function () { body.classList.toggle('menu-open'); });
  }
  if (scrim) {
    scrim.addEventListener('click', function () { body.classList.remove('menu-open'); });
  }

  // Dismiss alerts
  document.addEventListener('click', function (e) {
    var close = e.target.closest('.alert-close');
    if (close) { close.parentNode.remove(); }

    // Confirm before destructive actions
    var guard = e.target.closest('[data-confirm]');
    if (guard && !window.confirm(guard.getAttribute('data-confirm'))) {
      e.preventDefault();
    }

    // Print button
    if (e.target.closest('[data-print]')) { window.print(); }
  });

  // Live final grade + remark while a teacher types quarter grades
  var PASS = 75;
  function updateRow(row) {
    var vals = [];
    row.querySelectorAll('.grade-input').forEach(function (inp) {
      var v = parseFloat(inp.value);
      if (!isNaN(v) && v >= 0 && v <= 100) { vals.push(v); }
    });
    var finalEl = row.querySelector('.final');
    var remEl = row.querySelector('.remark');
    if (!finalEl || !remEl) { return; }
    if (!vals.length) {
      finalEl.textContent = '\u2014';
      remEl.textContent = '\u2014';
      remEl.className = 'remark';
      return;
    }
    var avg = vals.reduce(function (a, b) { return a + b; }, 0) / vals.length;
    finalEl.textContent = (Math.round(avg * 100) / 100).toString();
    var ok = avg >= PASS;
    remEl.textContent = ok ? 'Passed' : 'Failed';
    remEl.className = 'remark ' + (ok ? 'rem-pass' : 'rem-fail');
  }
  document.querySelectorAll('tr.grade-row').forEach(function (row) {
    row.addEventListener('input', function () { updateRow(row); });
  });
})();
