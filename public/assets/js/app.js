/* IHMS shared front-end: API client, toasts, modals, forms, data tables, shell behaviour. No framework. */
(function () {
  'use strict';
  const BASE = (document.querySelector('meta[name="app-base"]') || {}).content || '';
  const bootEl = document.getElementById('boot');
  const boot = bootEl ? JSON.parse(bootEl.textContent) : null;
  let csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const can = (p) => !!(boot && boot.user && boot.user.permissions.includes(p));
  const fmt = (s) => (s ? String(s).slice(0, 16).replace('T', ' ') : '<span class="muted">—</span>');
  const badge = (t, kind) => `<span class="badge b-${kind || 'mut'}">${esc(t)}</span>`;

  /* Inline SVG icons, mirroring the PHP helper so the flyout can be built in the browser. */
  const ICON_PATHS = {
    home: 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10',
    users: 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 21a8 8 0 0116 0',
    building: 'M4 21V4h10v17M14 9h6v12M8 8h2M8 12h2M8 16h2M17 13h1M17 17h1M2 21h20',
    shield: 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
    settings: 'M12 15a3 3 0 100-6 3 3 0 000 6zM19 12a7 7 0 00-.1-1.2l2-1.5-2-3.4-2.3.9a7 7 0 00-2-1.2L14 3h-4l-.6 2.6a7 7 0 00-2 1.2l-2.3-.9-2 3.4 2 1.5A7 7 0 005 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-.9a7 7 0 002 1.2L10 21h4l.6-2.6a7 7 0 002-1.2l2.3.9 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z',
    list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
    login: 'M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3',
    monitor: 'M3 5h18v11H3zM8 21h8M12 16v5',
    alert: 'M12 3l10 18H2zM12 10v5M12 18h.01',
    check: 'M20 6L9 17l-5-5',
    bell: 'M6 9a6 6 0 0112 0c0 6 2 7 2 8H4c0-1 2-2 2-8zM10 21h4',
    database: 'M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zM4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3',
    megaphone: 'M3 11v2a1 1 0 001 1h3l8 5V5L7 10H4a1 1 0 00-1 1zM19 8a5 5 0 010 8',
    chart: 'M4 20V10M10 20V4M16 20v-7M22 20H2',
    trend: 'M3 17l6-6 4 4 8-8M21 7h-5M21 7v5',
    key: 'M15 7a4 4 0 11-3.5 5.9L4 20.4V17h3v-3h3l1.4-1.4A4 4 0 0115 7z',
    save: 'M5 3h11l3 3v15H5zM8 3v6h7V3M8 14h8v7H8z',
    menu: 'M4 6h16M4 12h16M4 18h16',
  };
  const icon = (name) => `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${ICON_PATHS[name] || ICON_PATHS.list}"/></svg>`;

  /* ---- swift alerts: every process answers instantly, success or failure ------------- */
  const TOAST_TTL = 4200;
  const TOAST_MAX = 4;
  let toastCount = 0;      // raised by page code; api() uses it to avoid double alerts
  let pending = 0;         // requests in flight, drives the top progress bar

  const TOAST_ICON = {
    ok: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
    bad: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v5M12 16h.01M10.3 3.9L2.5 17.5A1.9 1.9 0 004.2 20.4h15.6a1.9 1.9 0 001.7-2.9L13.7 3.9a1.9 1.9 0 00-3.4 0z"/></svg>',
    warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l10 18H2zM12 10v5M12 18h.01"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8h.01M11 12h1v5h1"/><circle cx="12" cy="12" r="9"/></svg>',
  };

  function toastBox() {
    let box = document.querySelector('.toasts');
    if (!box) {
      box = document.createElement('div');
      box.className = 'toasts';
      box.setAttribute('role', 'status');
      box.setAttribute('aria-live', 'polite');
      document.body.appendChild(box);
    }
    return box;
  }

  /** Swift alert: appears immediately, shows its own kind, and retires itself. */
  function toast(msg, kind) {
    toastCount++;
    const k = kind || 'info';
    const box = toastBox();
    while (box.children.length >= TOAST_MAX) { box.firstElementChild.remove(); }
    const t = document.createElement('div');
    t.className = 'toast ' + k;
    t.setAttribute('role', k === 'bad' ? 'alert' : 'status');
    t.innerHTML = `<span class="t-ic">${TOAST_ICON[k] || TOAST_ICON.info}</span><span class="t-msg">${esc(msg)}</span><button class="t-x" type="button" aria-label="Dismiss">&times;</button><i class="t-bar"></i>`;
    const close = () => { t.classList.add('out'); setTimeout(() => t.remove(), 160); };
    t.querySelector('.t-x').onclick = close;
    t.onclick = (e) => { if (!e.target.closest('.t-x')) { close(); } };
    box.appendChild(t);
    setTimeout(close, TOAST_TTL);
    return t;
  }

  /** Thin bar across the top of the page while a request is on the wire. */
  function progress() {
    let bar = document.getElementById('topbar');
    if (!bar) { bar = document.createElement('div'); bar.id = 'topbar'; document.body.appendChild(bar); }
    pending++;
    bar.classList.add('on');
    return () => { pending = Math.max(0, pending - 1); if (!pending) { bar.classList.remove('on'); } };
  }

  /** Raise the automatic alert only if the page did not already answer the user. */
  function settle(before, fn) {
    setTimeout(() => { if (toastCount === before) { fn(); } }, 0);
  }

  let quietAlertsUntil = 0;   // an action alert already covered this moment

  async function api(method, path, body, opts) {
    opts = opts || {};
    const before = toastCount;
    const quiet = opts.quiet === true
      || path.indexOf('/auth/') === 0
      || /^\/notifications\/(read-all|\d+\/read)$/.test(path);
    const opts_ = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
    let url = BASE + '/api/v1' + path;
    if (method === 'GET') {
      const q = new URLSearchParams(Object.entries(body || {}).filter(([, v]) => v !== '' && v != null));
      if ([...q].length) url += '?' + q;
    } else {
      opts_.headers['Content-Type'] = 'application/json';
      opts_.headers['X-CSRF-Token'] = csrf;
      if (body) opts_.body = JSON.stringify(body);
    }
    const done = progress();
    let res, json = null;
    try { res = await fetch(url, opts_); } catch (e) { done(); const er = new Error('Network error. Check your connection.'); er.status = 0; er.errors = {}; throw er; }
    try { json = await res.json(); } catch (e) { /* non-JSON */ }
    done();
    if (res.status === 401 && !/\/(login|forgot-password|reset-password)$/.test(location.pathname)) { location.href = BASE + '/login'; }
    if (res.status === 403 && json && json.errors && json.errors.code === 'password_change_required' && !location.pathname.endsWith('/change-password')) { location.href = BASE + '/change-password'; }
    if (!res.ok) {
      const err = new Error((json && json.message) || 'Request failed (' + res.status + ').');
      err.status = res.status; err.errors = (json && json.errors) || {};
      if (!quiet) { settle(before, () => { if (!err.handled) { toast(err.message, 'bad'); } }); }
      throw err;
    }
    if (json && json.data && json.data.csrf_token) csrf = json.data.csrf_token;
    // Any process that did not raise its own alert still answers the user: swift, never silent.
    if (method !== 'GET' && !quiet) {
      settle(before, () => {
        const said = (json && json.message && json.message !== 'OK') ? json.message
          : ({ POST: 'Done.', PUT: 'Changes saved.', PATCH: 'Changes saved.', DELETE: 'Removed.' }[method] || 'Done.');
        toast(said, 'ok');
        quietAlertsUntil = Date.now() + 1500;
        if (window.IHMS && window.IHMS.notifications && window.IHMS.notifications.refresh) { window.IHMS.notifications.refresh(); }
      });
    }
    return json;
  }

  function modal({ title, body = '', buttons = [], size = '' }) {
    const ov = document.createElement('div'); ov.className = 'overlay';
    ov.innerHTML = `<div class="modal ${size}" role="dialog" aria-modal="true"><header><span>${esc(title)}</span><button class="iconbtn" aria-label="Close" data-x>✕</button></header><div class="body"></div><footer></footer></div>`;
    const bodyEl = ov.querySelector('.body'); const foot = ov.querySelector('footer');
    if (typeof body === 'string') bodyEl.innerHTML = body; else bodyEl.appendChild(body);
    const api_ = { el: ov, body: bodyEl, close: () => { ov.remove(); document.removeEventListener('keydown', onKey); } };
    const onKey = (e) => { if (e.key === 'Escape') api_.close(); };
    document.addEventListener('keydown', onKey);
    ov.addEventListener('mousedown', (e) => { if (e.target === ov) api_.close(); });
    ov.querySelector('[data-x]').onclick = api_.close;
    buttons.forEach((b) => {
      const el = document.createElement('button'); el.className = 'btn ' + (b.cls || ''); el.textContent = b.label;
      el.onclick = () => (b.onClick ? b.onClick(api_, el) : api_.close()); foot.appendChild(el); b.el = el;
    });
    if (!buttons.length) foot.remove();
    document.body.appendChild(ov);
    const first = ov.querySelector('input,select,textarea,.btn'); if (first) first.focus();
    return api_;
  }

  function confirmDialog(message, { confirmLabel = 'Confirm', danger = false, title = 'Please confirm' } = {}) {
    return new Promise((resolve) => {
      modal({ title, body: `<p>${esc(message)}</p>`, buttons: [
        { label: 'Cancel', onClick: (m) => { m.close(); resolve(false); } },
        { label: confirmLabel, cls: danger ? 'bad' : 'pri', onClick: (m) => { m.close(); resolve(true); } },
      ] });
    });
  }

  /** fields: [{name,label,type:text|email|password|number|date|textarea|select|checkboxes|switch, options:[{value,label}], required, help}] */
  function formModal({ title, fields, values = {}, submitLabel = 'Save', onSubmit, size = '' }) {
    const form = document.createElement('form'); form.noValidate = true;
    form.innerHTML = '<div class="alert bad" hidden data-top></div>' + fields.map((f) => {
      const v = values[f.name]; const req = f.required ? ' <span class="req">*</span>' : '';
      const help = f.help ? `<span class="help">${esc(f.help)}</span>` : '';
      let ctl;
      if (f.type === 'select') ctl = `<select class="in" name="${f.name}">${(f.placeholder !== undefined ? [{ value: '', label: f.placeholder }] : []).concat(f.options).map((o) => `<option value="${esc(o.value)}" ${String(v ?? '') === String(o.value) ? 'selected' : ''}>${esc(o.label)}</option>`).join('')}</select>`;
      else if (f.type === 'textarea') ctl = `<textarea class="in" name="${f.name}" rows="3">${esc(v ?? '')}</textarea>`;
      else if (f.type === 'checkboxes') { const sel = (v || []).map(String); ctl = `<div class="chk-grid">${f.options.map((o) => `<label class="chk"><input type="checkbox" name="${f.name}" value="${esc(o.value)}" ${sel.includes(String(o.value)) ? 'checked' : ''}> ${esc(o.label)}</label>`).join('')}</div>`; }
      else if (f.type === 'switch') return `<label class="chk f"><input type="checkbox" name="${f.name}" ${v ? 'checked' : ''}> ${esc(f.label)}</label>`;
      else ctl = `<input class="in" name="${f.name}" type="${f.type || 'text'}" value="${esc(v ?? '')}" autocomplete="off" ${f.readonly ? 'readonly' : ''}>`;
      return `<label class="f" data-f="${f.name}">${esc(f.label)}${req} ${help}${f.type === 'checkboxes' ? '' : ctl}${f.type === 'checkboxes' ? ctl : ''}<span class="err" hidden></span></label>`;
    }).join('');
    const collect = () => {
      const out = {};
      fields.forEach((f) => {
        const els = form.querySelectorAll(`[name="${f.name}"]`);
        if (f.type === 'checkboxes') out[f.name] = [...els].filter((e) => e.checked).map((e) => e.value);
        else if (f.type === 'switch') out[f.name] = els[0].checked;
        else out[f.name] = els[0].value;
      });
      return out;
    };
    const m = modal({ title, body: form, size, buttons: [
      { label: 'Cancel' },
      { label: submitLabel, cls: 'pri', onClick: async (mm, btn) => {
        form.querySelectorAll('.err').forEach((e) => { e.hidden = true; }); form.querySelectorAll('.has-err').forEach((e) => e.classList.remove('has-err')); form.querySelector('[data-top]').hidden = true;
        btn.disabled = true; const old = btn.textContent; btn.innerHTML = '<span class="spin"></span>Saving…';
        try { await onSubmit(collect(), mm); }
        catch (e) {
          let shown = false;
          Object.entries(e.errors || {}).forEach(([k, msg]) => { const w = form.querySelector(`[data-f="${k}"]`); if (w) { w.classList.add('has-err'); const s = w.querySelector('.err'); s.textContent = msg; s.hidden = false; shown = true; } });
          if (!shown || !Object.keys(e.errors || {}).length) { const t = form.querySelector('[data-top]'); t.textContent = e.message; t.hidden = false; }
          if (shown) { e.handled = true; }   // the field itself explains the problem; no toast needed
        } finally { btn.disabled = false; btn.textContent = old; }
      } },
    ] });
    form.addEventListener('submit', (e) => { e.preventDefault(); m.el.querySelector('footer .pri').click(); });
    return m;
  }

  /** Server-driven table: search/filter/sort/paginate with loading, empty and error states. */
  function DataTable(mount, o) {
    const st = { page: 1, sort: o.sort || '', dir: o.dir || 'desc', f: {}, timer: null };
    mount.innerHTML = `<div class="toolbar"></div><div class="tw"><table><thead></thead><tbody></tbody></table><div class="state" hidden></div></div><div class="pager"></div>`;
    const bar = mount.querySelector('.toolbar'), thead = mount.querySelector('thead'), tbody = mount.querySelector('tbody'), stateEl = mount.querySelector('.state'), pager = mount.querySelector('.pager');
    (o.filters || []).forEach((f) => {
      const w = document.createElement('label'); w.className = 'small muted'; w.innerHTML = esc(f.label || '');
      let el;
      if (f.type === 'select') { el = document.createElement('select'); el.className = 'in'; el.innerHTML = f.options.map((x) => `<option value="${esc(x.value)}">${esc(x.label)}</option>`).join(''); el.onchange = () => { st.f[f.name] = el.value; st.page = 1; load(); }; }
      else { el = document.createElement('input'); el.className = 'in'; el.type = f.type === 'date' ? 'date' : 'search'; el.placeholder = f.placeholder || ''; el.oninput = () => { clearTimeout(st.timer); st.timer = setTimeout(() => { st.f[f.name] = el.value; st.page = 1; load(); }, 300); }; if (f.type === 'date') el.onchange = () => { st.f[f.name] = el.value; st.page = 1; load(); }; }
      w.appendChild(el); bar.appendChild(w);
    });
    bar.insertAdjacentHTML('beforeend', '<span class="sp"></span>');
    (o.toolbar || []).forEach((b) => { const el = document.createElement('button'); el.className = 'btn ' + (b.cls || ''); el.textContent = b.label; el.onclick = () => b.onClick(api_); bar.appendChild(el); });
    const heads = () => {
      thead.innerHTML = '<tr>' + o.columns.map((c) => `<th ${c.sort ? `class="sortable" data-s="${c.sort}"` : ''}>${esc(c.label)}${st.sort === c.sort && c.sort ? (st.dir === 'asc' ? ' ▲' : ' ▼') : ''}</th>`).join('') + (o.actions ? '<th></th>' : '') + '</tr>';
      thead.querySelectorAll('.sortable').forEach((th) => { th.onclick = () => { const s = th.dataset.s; st.dir = st.sort === s && st.dir === 'asc' ? 'desc' : 'asc'; st.sort = s; load(); }; });
    };
    const show = (msg, retry) => { tbody.innerHTML = ''; stateEl.hidden = false; stateEl.innerHTML = msg + (retry ? ' <button class="btn sm" data-r>Retry</button>' : ''); const r = stateEl.querySelector('[data-r]'); if (r) r.onclick = load; };
    async function load() {
      heads(); show('<span class="spin"></span>Loading…'); pager.innerHTML = '';
      try {
        const res = await api('GET', o.endpoint, Object.assign({ page: st.page, per_page: o.perPage || 15, sort: st.sort, dir: st.dir }, o.params || {}, st.f));
        const rows = res.data, meta = res.meta || { page: 1, pages: 1, total: rows.length };
        if (!rows.length) { show(esc(o.empty || 'Nothing to show.')); return; }
        stateEl.hidden = true;
        tbody.innerHTML = '';
        rows.forEach((row) => {
          const tr = document.createElement('tr');
          tr.innerHTML = o.columns.map((c) => `<td>${c.render ? c.render(row) : esc(row[c.key])}</td>`).join('') + (o.actions ? '<td><div class="acts"></div></td>' : '');
          if (o.actions) o.actions(row, api_).forEach((a) => { const b = document.createElement('button'); b.className = 'btn sm ' + (a.danger ? 'bad' : ''); b.textContent = a.label; b.onclick = () => a.onClick(row, api_); tr.querySelector('.acts').appendChild(b); });
          tbody.appendChild(tr);
        });
        pager.innerHTML = `<span class="muted small">${meta.total} record${meta.total === 1 ? '' : 's'} · page ${meta.page} of ${meta.pages}</span><button class="btn sm" data-p ${meta.page <= 1 ? 'disabled' : ''}>Previous</button><button class="btn sm" data-n ${meta.page >= meta.pages ? 'disabled' : ''}>Next</button>`;
        pager.querySelector('[data-p]').onclick = () => { st.page--; load(); }; pager.querySelector('[data-n]').onclick = () => { st.page++; load(); };
      } catch (e) { show(`<span style="color:var(--bad)">${esc(e.message)}</span>`, true); }
    }
    const api_ = { reload: load, state: st };
    load();
    return api_;
  }

  // ---- Shell behaviour (sidebar, dropdowns, notifications)
  function initShell() {
    const body = document.body;
    if (localStorage.getItem('ihms.sb') === 'wide') body.classList.add('sb-wide');
    const tg = document.getElementById('sbToggle');
    if (tg) tg.onclick = () => {
      if (window.innerWidth <= 980) { body.classList.toggle('sb-open'); }
      else { body.classList.toggle('sb-wide'); localStorage.setItem('ihms.sb', body.classList.contains('sb-wide') ? 'wide' : 'rail'); }
    };
    document.addEventListener('click', (e) => {
      if (body.classList.contains('sb-open') && !e.target.closest('.sidebar') && !e.target.closest('#sbToggle')) body.classList.remove('sb-open');
      document.querySelectorAll('.dd.open').forEach((d) => { if (!d.contains(e.target)) d.classList.remove('open'); });
    });
    document.querySelectorAll('.dd > .iconbtn, .dd > .btn').forEach((b) => b.addEventListener('click', () => b.parentElement.classList.toggle('open')));
    const lo = document.getElementById('logoutBtn');
    if (lo) lo.onclick = async () => { try { await api('POST', '/auth/logout'); } catch (e) { /* already out */ } location.href = BASE + '/login'; };
    initRail();
    if (boot) initNotifications();
  }

  /**
   * Compact icon rail. Primary pages are pinned to the rail; every other page lives in a
   * group whose flyout opens on hover (desktop) or as an accordion (mobile drawer).
   */
  function initRail() {
    const menuEl = document.getElementById('menu');
    const nav = document.getElementById('nav');
    const fly = document.getElementById('flyout');
    if (!menuEl || !nav || !fly) { return; }
    let menu;
    try { menu = JSON.parse(menuEl.textContent); } catch (e) { return; }
    const groups = menu.groups || [];
    const active = menu.active || '';
    const isMobile = () => window.innerWidth <= 980;
    const canHover = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    const isWide = () => document.body.classList.contains('sb-wide');
    const itemHtml = (i) => `<a class="fly-i${active === i.href ? ' active' : ''}" href="${BASE + i.href}">${icon(i.icon)}<span>${esc(i.label)}</span></a>`;

    // one inline sub-list per group, used by the wide rail and the mobile drawer
    const subs = new Map();
    groups.forEach((g, gi) => {
      const btn = nav.querySelector(`.rail-btn.grp[data-group="${gi}"]`);
      if (!btn) { return; }
      const sub = document.createElement('div');
      sub.className = 'rail-sub';
      sub.innerHTML = g.items.map(itemHtml).join('');
      btn.after(sub);
      subs.set(gi, sub);
    });

    let closeTimer = null;

    const closeFly = () => {
      clearTimeout(closeTimer);
      fly.hidden = true;
      nav.querySelectorAll('.rail-btn.grp.open').forEach((b) => { b.classList.remove('open'); b.setAttribute('aria-expanded', 'false'); });
    };

    const openFly = (gi) => {
      if (isMobile()) { return; }
      const g = groups[gi];
      const btn = nav.querySelector(`.rail-btn.grp[data-group="${gi}"]`);
      if (!g || !btn) { return; }
      clearTimeout(closeTimer);
      fly.innerHTML = `<div class="fly-h"><b>${esc(g.name)}</b>${g.note ? `<span>${esc(g.note)}</span>` : ''}</div>` + g.items.map(itemHtml).join('');
      fly.hidden = false;
      const r = btn.getBoundingClientRect();
      fly.style.left = (r.right + 8) + 'px';
      const h = fly.offsetHeight;
      let top = r.top - 8;
      if (top + h > window.innerHeight - 12) { top = Math.max(12, window.innerHeight - h - 12); }
      fly.style.top = top + 'px';
      nav.querySelectorAll('.rail-btn.grp.open').forEach((b) => { if (b !== btn) { b.classList.remove('open'); b.setAttribute('aria-expanded', 'false'); } });
      btn.classList.add('open');
      btn.setAttribute('aria-expanded', 'true');
    };

    const openInline = (btn) => {
      nav.querySelectorAll('.rail-btn.grp.open').forEach((b) => {
        if (b !== btn) { b.classList.remove('open'); b.setAttribute('aria-expanded', 'false'); }
      });
      btn.classList.add('open');
      btn.setAttribute('aria-expanded', 'true');
    };

    nav.querySelectorAll('.rail-btn.grp').forEach((btn) => {
      const gi = Number(btn.dataset.group);
      btn.addEventListener('pointerenter', () => {
        if (isMobile()) {
          if (canHover() && document.body.classList.contains('sb-open')) { openInline(btn); }
          return;
        }
        clearTimeout(closeTimer);
        if (isWide()) {
          openInline(btn);
        } else {
          openFly(gi);
        }
      });
      btn.addEventListener('pointerleave', () => {
        if (!isMobile() && !isWide()) { closeTimer = setTimeout(closeFly, 260); }
      });
      btn.addEventListener('focus', () => {
        if (isMobile()) { return; }
        if (isWide()) {
          openInline(btn);
        } else {
          openFly(gi);
        }
      });
      btn.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); openFly(gi); const f = fly.querySelector('.fly-i'); if (f) { f.focus(); } }
        if (e.key === 'Escape') { closeFly(); }
      });
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        if (isMobile()) {
          const sub = subs.get(gi);
          if (!sub) { return; }
          const open = sub.classList.toggle('open');
          btn.classList.toggle('open', open);
          btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        } else if (isWide()) {
          btn.classList.add('open');
          btn.setAttribute('aria-expanded', 'true');
        } else {
          openFly(gi);
        }
      });
    });

    nav.addEventListener('pointerenter', () => clearTimeout(closeTimer));
    nav.addEventListener('pointerleave', () => {
      if (!isMobile() && isWide()) { closeTimer = setTimeout(closeFly, 200); }
      if (isMobile() && canHover()) { closeTimer = setTimeout(closeFly, 200); }
    });
    fly.addEventListener('mouseenter', () => clearTimeout(closeTimer));
    fly.addEventListener('mouseleave', () => { closeTimer = setTimeout(closeFly, 200); });
    fly.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeFly(); nav.querySelector('.rail-btn.grp')?.focus(); } });
    document.addEventListener('click', (e) => { if (!e.target.closest('.flyout') && !e.target.closest('.rail-btn.grp')) { closeFly(); } });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeFly(); } });
    window.addEventListener('resize', () => { if (isMobile()) { closeFly(); } });
  }

  function initNotifications() {
    const countEl = document.getElementById('notifCount'), list = document.getElementById('notifList');
    if (!countEl) return;
    const sev = { info: 'info', warning: 'warn', critical: 'bad' };
    const kindOf = (s) => (s === 'critical' ? 'bad' : s === 'warning' ? 'warn' : 'info');
    let baseline = null;      // first count seen on this page: never replayed as an alert

    async function refreshCount() {
      try {
        const r = await api('GET', '/notifications/unread-count');
        const c = Number(r.data.count) || 0;
        countEl.textContent = c > 99 ? '99+' : c;
        countEl.hidden = c === 0;
        const bell = document.getElementById('bellBtn');
        if (bell) { bell.classList.toggle('has-new', c > 0 && baseline !== null && c > baseline); }
        if (baseline === null) { baseline = c; }
        else if (c > baseline) { baseline = c; alertNewest(); }
        return c;
      } catch (e) { /* the bell must never break the page */ }
    }

    /** A new notification arrives: say so on screen instead of waiting for the badge to change. */
    async function alertNewest() {
      if (Date.now() < quietAlertsUntil) { return; }   // the action alert already covered this
      try {
        const r = await api('GET', '/notifications', { per_page: 1, unread: 1 });
        const n = (r.data || [])[0];
        if (!n) { return; }
        toast(n.title + ' — ' + n.message, kindOf(n.severity));
        const bell = document.getElementById('bellBtn');
        if (bell) { bell.classList.remove('has-new'); }
      } catch (e) { /* ignore */ }
    }

    async function refreshList() {
      list.innerHTML = '<div class="state"><span class="spin"></span>Loading…</div>';
      try {
        const r = await api('GET', '/notifications', { per_page: 10, unread: 1 });
        list.innerHTML = r.data.length ? r.data.map((n) => `<button class="item notification-item unread" data-id="${n.id}"><div class="notification-title">${badge(n.severity, sev[n.severity])}<strong>${esc(n.title)}</strong></div><div class="notification-message">${esc(n.message)}</div><div class="notification-time">${fmt(n.created_at)}</div></button>`).join('') : '<div class="state notification-empty">No unread notifications.</div>';
        list.querySelectorAll('.item').forEach((it) => { it.onclick = async () => { await api('POST', `/notifications/${it.dataset.id}/read`); refreshList(); refreshCount(); }; });
      } catch (e) { list.innerHTML = `<div class="state">${esc(e.message)}</div>`; }
    }

    document.getElementById('bellBtn').addEventListener('click', refreshList);
    document.getElementById('readAll').onclick = async () => {
      try {
        const r = await api('POST', '/notifications/read-all');
        toast((r.data && r.data.updated) ? (r.data.updated + ' notification(s) marked as read.') : 'Notifications marked as read.', 'ok');
        refreshList(); refreshCount();
      } catch (e) { toast(e.message, 'bad'); }
    };
    window.IHMS.notifications = { refresh: refreshCount, list: refreshList };
    refreshCount();
    setInterval(refreshCount, 30000);
  }

  window.IHMS = { BASE, boot, api, esc, can, fmt, badge, icon, toast, modal, confirmDialog, formModal, DataTable, notifications: {} };
  document.addEventListener('DOMContentLoaded', initShell);
})();
