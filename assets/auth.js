// Sign-in page: one tap fills the public judge login.
(function () {
  'use strict';
  var btn = document.querySelector('[data-fill-demo]');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var form = document.querySelector('form.form');
    form.elements.email.value = 'demo';
    form.elements.password.value = 'Demo509';
    form.querySelector('button[type="submit"]').focus();
  });
})();
