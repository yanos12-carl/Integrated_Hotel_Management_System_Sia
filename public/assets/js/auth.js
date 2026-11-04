/* Sign-in, forgot/reset password and forced password change forms. */
(function () {
  const { api, BASE } = window.IHMS;
  const form = document.querySelector('form[data-auth]');
  if (!form) return;
  const msg = document.getElementById('msg');
  const say = (t, kind) => { msg.className = 'alert ' + kind; msg.textContent = t; msg.hidden = false; };
  form.addEventListener('submit', async (e) => {
    e.preventDefault(); msg.hidden = true;
    form.querySelectorAll('.err').forEach((x) => { x.hidden = true; });
    const data = Object.fromEntries(new FormData(form));
    const btn = form.querySelector('button[type=submit]'); btn.disabled = true; const old = btn.textContent; btn.textContent = 'Please wait…';
    try {
      const kind = form.dataset.auth;
      if (kind === 'login') {
        const r = await api('POST', '/auth/login', data);
        const security = r.data.user.department && r.data.user.department.code === 'SECURITY';
        location.href = BASE + (r.data.user.must_change_password == 1 ? '/change-password' : security ? '/security' : '/home');
        return;
      }
      if (kind === 'forgot') { const r = await api('POST', '/auth/forgot-password', data); say(r.message, 'ok'); form.reset(); }
      if (kind === 'reset') {
        if (data.new_password !== data.confirm) { say('The two passwords do not match.', 'bad'); }
        else { const r = await api('POST', '/auth/reset-password', { token: data.token, new_password: data.new_password }); say(r.message, 'ok'); setTimeout(() => { location.href = BASE + '/login'; }, 1500); }
      }
      if (kind === 'change') {
        if (data.new_password !== data.confirm) { say('The two passwords do not match.', 'bad'); }
        else {
          const r = await api('POST', '/auth/change-password', { current_password: data.current_password, new_password: data.new_password });
          say('Password changed. Redirecting…', 'ok');
          setTimeout(() => { location.href = BASE + (r.data.security_launch ? '/security' : '/home'); }, 900);
        }
      }
    } catch (err) {
      const first = Object.values(err.errors || {}).filter((v) => typeof v === 'string')[0];
      say(first || err.message, 'bad');
    } finally { btn.disabled = false; btn.textContent = old; }
  });
})();
