/* AlphaOmega School Management - small progressive-enhancement helpers (no inline scripts, CSP-safe). */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  // Mobile sidebar
  var sb = $('#sidebar'), bd = $('#sidebarBackdrop'), tg = $('#sidebarToggle');
  function toggleSide(open) {
    if (!sb) return;
    sb.classList.toggle('open', open);
    if (bd) bd.classList.toggle('show', open);
  }
  if (tg) tg.addEventListener('click', function () { toggleSide(!sb.classList.contains('open')); });
  if (bd) bd.addEventListener('click', function () { toggleSide(false); });

  // Auto-submit selects/inputs (year switcher, filters)
  $$('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });

  // Confirmation before destructive/important POSTs
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var msg = f.getAttribute && f.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
    // Prevent double submission: disable submit buttons once the form is on its way.
    if (!e.defaultPrevented && f.hasAttribute && !f.hasAttribute('data-allow-resubmit')) {
      setTimeout(function () {
        $$('button[type=submit], button:not([type]), input[type=submit]', f).forEach(function (b) { b.disabled = true; });
      }, 0);
    }
  });
  // Re-enable buttons when coming back via the browser Back button
  window.addEventListener('pageshow', function (ev) {
    if (ev.persisted) $$('form button, form input[type=submit]').forEach(function (b) { b.disabled = false; });
  });

  // Dependent class -> section selects: <select data-sections-target="#section">
  function filterSections(classSel) {
    var target = $(classSel.getAttribute('data-sections-target'));
    if (!target) return;
    var cls = classSel.value;
    var firstValid = null;
    $$('option', target).forEach(function (o) {
      var c = o.getAttribute('data-class');
      if (!c) { return; }                       // placeholder option stays visible
      var show = !cls || c === cls;
      o.hidden = !show; o.disabled = !show;
      if (show && firstValid === null) firstValid = o;
    });
    var cur = target.options[target.selectedIndex];
    if (cur && cur.getAttribute('data-class') && cur.hidden) target.value = '';
  }
  $$('[data-sections-target]').forEach(function (s) {
    filterSections(s);
    s.addEventListener('change', function () { filterSections(s); });
  });

  // Print / back buttons
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  $$('[data-back]').forEach(function (b) { b.addEventListener('click', function () { if (history.length > 1) history.back(); else window.close(); }); });

  // Student picker: <div data-student-picker data-year="1"> with input.picker-q, hidden input.picker-id, ul.picker-results
  $$('[data-student-picker]').forEach(function (box) {
    var q = $('.picker-q', box), id = $('.picker-id', box), list = $('.picker-results', box), chosen = $('.picker-chosen', box);
    var base = box.getAttribute('data-url'), timer = null;
    function render(rows) {
      list.innerHTML = '';
      if (!rows.length) { list.innerHTML = '<li class="list-group-item text-muted small">No matching student</li>'; list.hidden = false; return; }
      rows.forEach(function (r) {
        var li = document.createElement('li');
        li.className = 'list-group-item list-group-item-action';
        li.style.cursor = 'pointer';
        li.textContent = r.full_name + ' (Adm ' + r.admission_no + (r.class_name ? ', ' + r.class_name + '-' + r.section_name : '') + ')';
        li.addEventListener('click', function () {
          id.value = r.id; q.value = ''; list.hidden = true;
          if (chosen) chosen.textContent = li.textContent;
          id.dispatchEvent(new Event('change', { bubbles: true }));
        });
        list.appendChild(li);
      });
      list.hidden = false;
    }
    q.addEventListener('input', function () {
      clearTimeout(timer);
      if (q.value.trim().length < 2) { list.hidden = true; return; }
      timer = setTimeout(function () {
        fetch(base + '&q=' + encodeURIComponent(q.value.trim()), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); }).then(render).catch(function () { list.hidden = true; });
      }, 250);
    });
  });

  // Show/hide a field group depending on a select value: data-show-when="#other" data-show-value="cash" (hide when equal)
  $$('[data-hide-when-value]').forEach(function (el) {
    var src = $(el.getAttribute('data-hide-when-source'));
    var val = el.getAttribute('data-hide-when-value');
    function upd() { el.hidden = src.value === val; }
    if (src) { src.addEventListener('change', upd); upd(); }
  });

  // Marks sheet: "absent" checkbox clears and disables the mark box
  $$('.absent-box').forEach(function (cb) {
    var input = $('#' + cb.getAttribute('data-for'));
    function upd() { if (!input) return; input.disabled = cb.checked; if (cb.checked) input.value = ''; }
    cb.addEventListener('change', upd); upd();
  });

  // Reversal / void modal: fill the form action fields from the button's data attributes
  $$('[data-fill-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var m = document.getElementById(btn.getAttribute('data-fill-modal'));
      if (!m) return;
      Object.keys(btn.dataset).forEach(function (k) {
        var t = m.querySelector('[data-target="' + k + '"]');
        if (t) { if (t.tagName === 'INPUT') t.value = btn.dataset[k]; else t.textContent = btn.dataset[k]; }
      });
    });
  });
})();
