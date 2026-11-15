// Confirm dangerous actions
document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-confirm]');
  if (el && !confirm(el.getAttribute('data-confirm'))) {
    e.preventDefault();
  }
  // Copy payment number
  var c = e.target.closest('[data-copy]');
  if (c && navigator.clipboard) {
    e.preventDefault();
    navigator.clipboard.writeText(c.getAttribute('data-copy')).then(function () {
      var small = c.querySelector('small');
      if (small) { var t = small.textContent; small.textContent = '(কপি হয়েছে ✓)'; setTimeout(function () { small.textContent = t; }, 1500); }
    });
    var radio = c.closest('label') && c.closest('label').querySelector('input[type=radio]');
    if (radio) radio.checked = true;
  }
});
// Prevent double-submit
document.addEventListener('submit', function (e) {
  var b = e.target.querySelector('button:not([name])');
  if (b) setTimeout(function () { b.disabled = true; }, 0);
});
// Admin orders list: quick status change (asks the due amount for "Payment Due")
function pkQuickStatus(sel) {
  var f = sel.form;
  if (sel.value === 'due') {
    var amt = prompt('কত টাকা বাকি? (যেমন 50)');
    if (!amt || isNaN(parseFloat(amt))) { sel.value = sel.querySelector('option[selected]').value; return; }
    f.querySelector('[name=due_amount]').value = amt;
  } else if (sel.value === 'rejected' && !confirm('অর্ডারটি Decline করবেন?')) {
    sel.value = sel.querySelector('option[selected]').value; return;
  }
  f.submit();
}

// Home slider
document.querySelectorAll('[data-slider]').forEach(function (sl) {
  var track = sl.querySelector('.slides'), slides = track.children, n = slides.length, i = 0, timer;
  var dots = sl.querySelector('.sl-dots');
  if (n < 2) return;
  for (var k = 0; k < n; k++) { var d = document.createElement('button'); d.type = 'button'; d.setAttribute('aria-label', 'স্লাইড ' + (k + 1)); (function (k) { d.onclick = function () { go(k); }; })(k); dots.appendChild(d); }
  function go(k) { i = (k + n) % n; track.style.transform = 'translateX(' + (-100 * i) + '%)'; [].forEach.call(dots.children, function (b, j) { b.className = j === i ? 'on' : ''; }); restart(); }
  function restart() { clearInterval(timer); timer = setInterval(function () { go(i + 1); }, 5000); }
  sl.querySelector('.prev').onclick = function () { go(i - 1); };
  sl.querySelector('.next').onclick = function () { go(i + 1); };
  var x0 = null;
  sl.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
  sl.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 40) go(i + (dx < 0 ? 1 : -1)); x0 = null; });
  go(0);
});

// Flash sale countdown
document.querySelectorAll('.countdown[data-end]').forEach(function (c) {
  var end = +c.getAttribute('data-end');
  var bn = function (s) { return String(s).replace(/\d/g, function (d) { return '০১২৩৪৫৬৭৮৯'[d]; }); };
  var pad = function (v) { return bn(v < 10 ? '0' + v : v); };
  (function tick() {
    var s = Math.max(0, Math.floor((end - Date.now()) / 1000));
    c.querySelector('[data-h]').textContent = pad(Math.floor(s / 3600));
    c.querySelector('[data-m]').textContent = pad(Math.floor(s / 60) % 60);
    c.querySelector('[data-s]').textContent = pad(s % 60);
    if (s > 0) setTimeout(tick, 1000);
  })();
});

// "All categories" menu: tap to open on touch screens
document.querySelectorAll('.allcats-btn').forEach(function (b) {
  b.addEventListener('click', function (e) { e.stopPropagation(); b.parentNode.classList.toggle('open'); });
});
document.addEventListener('click', function () { document.querySelectorAll('.allcats.open').forEach(function (a) { a.classList.remove('open'); }); });

// Admin uploads: show a progress bar with % and speed instead of a frozen page
document.querySelectorAll('form[data-upload-progress]').forEach(function (form) {
  form.addEventListener('submit', function (e) {
    var hasFile = [].some.call(form.querySelectorAll('input[type=file]'), function (i) { return i.files.length; });
    if (!hasFile || !window.FormData) return; // normal submit
    e.preventDefault();
    var box = form.querySelector('.upload-progress'), bar = box.querySelector('.up-bar span'),
        pct = box.querySelector('.up-text b'), info = box.querySelector('.up-text .muted'),
        btn = form.querySelector('button.btn'), t0 = Date.now();
    box.hidden = false; if (btn) btn.disabled = true;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', form.getAttribute('action') || location.href);
    xhr.upload.onprogress = function (ev) {
      if (!ev.lengthComputable) return;
      var p = Math.round(ev.loaded / ev.total * 100), secs = (Date.now() - t0) / 1000,
          speed = ev.loaded / Math.max(secs, 0.1), left = (ev.total - ev.loaded) / Math.max(speed, 1);
      bar.style.width = p + '%'; pct.textContent = p + '%';
      info.textContent = '(' + (ev.loaded / 1048576).toFixed(1) + ' / ' + (ev.total / 1048576).toFixed(1) + ' MB · ' +
        (speed / 1048576 >= 1 ? (speed / 1048576).toFixed(1) + ' MB/s' : Math.round(speed / 1024) + ' KB/s') +
        (p < 100 ? ' · আর ~' + Math.ceil(left) + ' সেকেন্ড)' : ')');
      if (p === 100) pct.textContent = '100% — সেভ হচ্ছে…';
    };
    xhr.onload = function () {
      if (xhr.responseURL && xhr.responseURL.split('#')[0] !== location.href.split('#')[0]) { location.href = xhr.responseURL; return; }
      document.open(); document.write(xhr.responseText); document.close();
    };
    xhr.onerror = function () { box.querySelector('.up-text').textContent = 'আপলোড ব্যর্থ হয়েছে — ইন্টারনেট সংযোগ দেখে আবার চেষ্টা করুন।'; if (btn) btn.disabled = false; };
    xhr.send(new FormData(form));
  });
});
