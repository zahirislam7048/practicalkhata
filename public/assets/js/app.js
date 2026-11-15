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
