/* Admin department pages. Each page mounts into #page; every control calls the central API. */
(function () {
  'use strict';
  const { api, esc, can, fmt, badge, toast, modal, confirmDialog, formModal, DataTable } = window.IHMS;
  const CH = window.IHMS.charts;
  const root = document.getElementById('page');
  const pageKey = document.body.dataset.page;
  const yesNo = (v) => (Number(v) ? badge('Yes', 'ok') : badge('No', 'mut'));
  const sevKind = { info: 'info', warning: 'warn', critical: 'bad' };
  const loading = () => { root.innerHTML = '<div class="state"><span class="spin"></span>Loading…</div>'; };
  const fail = (e) => { root.innerHTML = `<div class="alert bad">${esc(e.message)}</div>`; };
  const deptOptions = async () => (await api('GET', '/admin/departments')).data.map((d) => ({ value: d.id, label: d.name + (Number(d.is_active) ? '' : ' (inactive)') }));
  const pre = (obj) => `<pre class="json">${esc(JSON.stringify(obj, null, 2))}</pre>`;
  const showTemp = (title, pw) => modal({ title, body: `<div class="alert info">Share this temporary password securely. It is shown <strong>only once</strong> and the employee must change it at first sign-in.</div><label class="f">Temporary password<input class="in" id="tp" readonly value="${esc(pw)}"></label>`, buttons: [{ label: 'Copy', onClick: async () => { try { await navigator.clipboard.writeText(pw); toast('Copied', 'ok'); } catch (e) { document.getElementById('tp').select(); } } }, { label: 'Done', cls: 'pri' }] });

  /* ---------- shared analytics helpers ---------- */
  const ICONS = {
    bed: 'M3 18V8M3 13h18M21 18v-7a3 3 0 00-3-3H9v10M6 11h.01M9 11h.01',
    coin: 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v10M15.5 9.6c-.6-1-1.9-1.6-3.5-1.6-2 0-3.5 1-3.5 2.5 0 3.5 7 1.5 7 5 0 1.5-1.5 2.5-3.5 2.5-1.8 0-3.1-.7-3.7-1.6',
    users: 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM4 21a8 8 0 0116 0',
    chart: 'M4 20V10M10 20V4M16 20v-7M22 20H2',
    shield: 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
    bell: 'M6 9a6 6 0 0112 0c0 6 2 7 2 8H4c0-1 2-2 2-8zM10 21h4',
    key: 'M15 7a4 4 0 11-3.5 5.9L4 20.4V17h3v-3h3l1.4-1.4A4 4 0 0115 7z',
    cart: 'M3 4h2l2.5 11h10L20 7H6M9 20a1 1 0 100-2 1 1 0 000 2zM17 20a1 1 0 100-2 1 1 0 000 2z',
    wrench: 'M14.7 6.3a4 4 0 105.4 5.4l-8 8-3-3 8-8z',
    brush: 'M9.5 14.5L4 20l5.5-5.5M11 13l6-6 3 3-6 6M14.5 9.5l-9 9',
    alert: 'M12 3l10 18H2zM12 10v5M12 18h.01',
    inbox: 'M3 12h5l2 3h4l2-3h5M5 5h14l2 7v7H3v-7z',
    clock: 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2',
  };
  const ico = (n) => `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${ICONS[n] || ICONS.chart}"/></svg>`;
  const money = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const int = (v) => Number(v || 0).toLocaleString('en-PH');
  const pct = (v) => (Math.round(Number(v || 0) * 10) / 10) + '%';
  const RANGES = [7, 14, 30, 90];
  const rangeSwitch = (days) => `<div class="range">${RANGES.map((d) => `<button type="button" data-d="${d}" class="${d === days ? 'on' : ''}">${d} days</button>`).join('')}</div>`;
  const bindRange = (onPick) => {
    root.querySelectorAll('.range button').forEach((b) => { b.onclick = () => onPick(Number(b.dataset.d)); });
  };
  const metric = (label, value, note, tone, icon, spark) => `<div class="metric">
    <div class="chip chip-${tone}">${ico(icon)}</div>
    <div class="l">${esc(label)}</div><div class="v">${esc(value)}</div><div class="d">${esc(note)}</div>${spark || ''}</div>`;
  const panel = (title, body, extra) => `<div class="panel"><div class="hd"><h2>${esc(title)}</h2><span class="sp"></span>${extra || ''}</div>${body}</div>`;
  const queueRow = (icon, tone, name, sub, value, cls) => `<div class="queue">
    <span class="qt chip-${tone}">${ico(icon)}</span>
    <div><div class="qn">${esc(name)}</div><div class="qs">${esc(sub)}</div></div>
    <div class="qv ${cls || ''}">${esc(value)}</div></div>`;
  const sparkOf = (pts, color) => CH.spark((pts || []).map((p) => p.value), color);
  const signSeries = (pts) => (pts || []).map((p) => ({ x: p.label, y: p.value }));

  const pages = {};
  let dashDays = 14;

  pages.dashboard = async () => {
    loading();
    let s, a;
    try {
      [s, a] = await Promise.all([
        api('GET', '/admin/summary').then((r) => r.data),
        api('GET', '/admin/analytics', { days: dashDays }).then((r) => r.data),
      ]);
    } catch (e) { return fail(e); }
    const k = a.kpi;
    const stat = (v, l, cls) => `<div class="card stat ${cls || ''}"><div class="v">${esc(v)}</div><div class="l">${esc(l)}</div></div>`;
    const hour = new Date().getHours();
    const greet = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
    const who = (window.IHMS.boot && window.IHMS.boot.user && window.IHMS.boot.user.name) || 'there';
    const first = String(who).split(' ')[0];
    const today = new Date().toLocaleDateString('en-PH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    root.innerHTML = `
    <div class="hero"><div class="in">
      <div style="flex:1;min-width:240px">
        <h2>${esc(greet)}, ${esc(first)}</h2>
        <p>${esc(today)} · live figures from the central database</p>
        <div class="facts">
          <span class="fact">Occupancy <b>${esc(pct(k.occupancy_rate))}</b></span>
          <span class="fact">In-house guests <b>${esc(int(k.in_house_guests))}</b></span>
          <span class="fact">Active staff <b>${esc(int(k.employees_active))}</b></span>
          <span class="fact">Pending approvals <b>${esc(int(k.pending_approvals))}</b></span>
        </div>
      </div>
      <div style="text-align:right;min-width:170px">
        <div style="font-size:2.6rem;font-weight:700;line-height:1">${esc(pct(k.occupancy_rate))}</div>
        <div style="font-size:.78rem;color:rgba(255,255,255,.78);letter-spacing:.06em;text-transform:uppercase">Hotel occupancy</div>
      </div>
    </div></div>

    <div class="grid cards">
      ${stat(s.users.active + ' / ' + s.users.total, 'Active / total employees', 't-rose')}
      ${stat(s.users.locked, 'Locked accounts', s.users.locked ? 'warn' : 't-khaki')}
      ${stat(s.active_sessions, 'Signed-in sessions (30 min)', 't-sky')}
      ${stat(s.failed_logins_24h, 'Failed sign-ins (24 h)', s.failed_logins_24h > 10 ? 'warn' : 't-khaki')}
      ${stat(s.successful_logins_24h, 'Successful sign-ins (24 h)', 't-sage')}
      ${stat(s.errors_24h, 'System errors (24 h)', s.errors_24h ? 'bad' : 't-sage')}
      ${stat(s.pending_approvals, 'Pending approvals', s.pending_approvals ? 'warn' : 't-khaki')}
      ${stat(s.unread_notifications, 'Your unread notifications', 't-rose')}
    </div>

    <div class="page-head" style="margin:1.6rem 0 .9rem">
      <div><h2 style="margin:0;font-size:1.2rem">Analytics</h2><p class="page-sub">Occupancy, revenue, workload and access activity · last ${esc(a.range.days)} days</p></div>
      <span class="sp"></span>
      ${rangeSwitch(a.range.days)}
    </div>

    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(330px,1fr))">
      ${panel('Occupancy right now', CH.donut(a.occupancy.by_status, { centreLabel: 'Rooms', title: 'Room status' }) || '', `<span class="sub">${esc(int(k.rooms_occupied))} of ${esc(int(k.rooms_total))} rooms occupied</span>`)}
      ${panel('Sign-in activity', CH.line([
        { name: 'Successful', color: '#5d8b58', points: signSeries(a.workforce.signins.ok) },
        { name: 'Failed', color: '#b1463d', points: signSeries(a.workforce.signins.failed) },
      ], { title: 'Sign-in activity' }), `<span class="sub">${esc(int(a.workforce.signins.failed.reduce((t, p) => t + p.value, 0)))} failed attempts</span>`)}
      ${panel('Revenue collected', CH.column(a.revenue.series.map((p) => ({ label: p.label, value: p.value })), { title: 'Revenue' }), `<span class="sub">${esc(money(a.revenue.total))} in ${esc(a.range.days)} days</span>`)}
      ${panel('Booking channels', CH.donut(a.front_office.by_source, { centreLabel: 'Reservations', title: 'Booking channels' }) || '', `<span class="sub">${esc(int(a.front_office.reservations))} reservations on file</span>`)}
      ${panel('Reservations by status', CH.bars(a.front_office.by_status.map((status) => ({ label: status.label, value: status.value, tone: 'rose' })), { empty: 'No reservations yet.' }), `<span class="sub">${esc(int(a.front_office.reservations))} reservations on file</span>`)}
    </div>

    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(330px,1fr));margin-top:1rem">
      ${panel('Employees by department', CH.bars(a.workforce.by_department.filter((d) => d.total > 0).map((d) => ({ label: d.label, value: d.active, tone: 'rose' })), { empty: 'No employees yet.' }), `<span class="sub">${esc(int(k.employees_active))} active of ${esc(int(k.employees_total))}</span>`)}
      ${panel('Top actions in the audit log', CH.bars(a.audit.by_action.map((x) => ({ label: x.label, value: x.value, tone: 'khaki' })), { empty: 'No audit activity yet.' }), `<span class="sub">${esc(int(a.audit.series.reduce((t, p) => t + p.value, 0)))} entries</span>`)}
      ${panel('Room types', CH.bars(a.occupancy.by_type.map((t) => ({ label: t.label, value: t.occupied, tone: 'sage' })), { empty: 'No room types configured yet.' }), `<span class="sub">Occupied rooms per type</span>`)}
    </div>

    <div class="page-head" style="margin:1.6rem 0 .9rem"><div><h2 style="margin:0;font-size:1.2rem">Departmental pulse</h2><p class="page-sub">Work queues waiting on each department</p></div></div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(330px,1fr));margin-bottom:1rem">
      ${panel('Open work by department', CH.bars([
        { label: 'Housekeeping', value: a.operations.housekeeping.pending, tone: 'khaki' },
        { label: 'Maintenance', value: a.operations.maintenance.open, tone: 'sky' },
        { label: 'Security', value: a.operations.security.open, tone: 'rose' },
        { label: 'Kitchen & F&B', value: a.operations.kitchen.open_orders, tone: 'amber' },
      ], { empty: 'No open work in the selected departments.' }), '<span class="sub">Current open work items</span>')}
    </div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(270px,1fr))">
      ${panel('Housekeeping', `<div class="queue"><span class="qt chip-khaki">${ico('brush')}</span><div><div class="qn">Awaiting a cleaner</div><div class="qs">Pending and assigned tasks</div></div><div class="qv">${esc(int(a.operations.housekeeping.pending))}</div></div>
        <div class="queue"><span class="qt chip-rose">${ico('clock')}</span><div><div class="qn">Overdue</div><div class="qs">Past the due time</div></div><div class="qv ${a.operations.housekeeping.overdue ? 'b-bad' : ''}">${esc(int(a.operations.housekeeping.overdue))}</div></div>
        <div class="queue"><span class="qt chip-sage">${ico('check')}</span><div><div class="qn">Awaiting inspection</div><div class="qs">Cleaned, not yet signed off</div></div><div class="qv">${esc(int(a.operations.housekeeping.inspect))}</div></div>`)}
      ${panel('Maintenance', `<div class="queue"><span class="qt chip-sky">${ico('wrench')}</span><div><div class="qn">Open work orders</div><div class="qs">Open, assigned or in progress</div></div><div class="qv">${esc(int(a.operations.maintenance.open))}</div></div>
        <div class="queue"><span class="qt chip-brick">${ico('alert')}</span><div><div class="qn">Overdue</div><div class="qs">Past the due time</div></div><div class="qv ${a.operations.maintenance.overdue ? 'b-bad' : ''}">${esc(int(a.operations.maintenance.overdue))}</div></div>
        <div class="queue"><span class="qt chip-amber">${ico('key')}</span><div><div class="qn">Blocking a room</div><div class="qs">Room out of service</div></div><div class="qv">${esc(int(a.operations.maintenance.blocking))}</div></div>`)}
      ${panel('Security', `<div class="queue"><span class="qt chip-rose">${ico('shield')}</span><div><div class="qn">Open incidents</div><div class="qs">Reported to not yet closed</div></div><div class="qv">${esc(int(a.operations.security.open))}</div></div>
        <div class="queue"><span class="qt chip-brick">${ico('alert')}</span><div><div class="qn">High or critical</div><div class="qs">Needs attention now</div></div><div class="qv ${a.operations.security.critical ? 'b-bad' : ''}">${esc(int(a.operations.security.critical))}</div></div>
        <div class="queue"><span class="qt chip-sage">${ico('check')}</span><div><div class="qn">Resolved in period</div><div class="qs">Closed or resolved</div></div><div class="qv">${esc(int(a.operations.security.resolved))}</div></div>`)}
      ${panel('Kitchen & F&amp;B', `<div class="queue"><span class="qt chip-amber">${ico('cart')}</span><div><div class="qn">Orders in flight</div><div class="qs">Placed to ready</div></div><div class="qv">${esc(int(a.operations.kitchen.open_orders))}</div></div>
        <div class="queue"><span class="qt chip-rose">${ico('inbox')}</span><div><div class="qn">Low stock items</div><div class="qs">At or below reorder level</div></div><div class="qv ${a.operations.kitchen.low_stock ? 'b-bad' : ''}">${esc(int(a.operations.kitchen.low_stock))}</div></div>
        <div class="queue"><span class="qt chip-sage">${ico('clock')}</span><div><div class="qn">Waste records</div><div class="qs">Logged in this period</div></div><div class="qv">${esc(int(a.operations.kitchen.waste_records))}</div></div>`)}
    </div>

    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(330px,1fr));margin-top:1rem">
      ${panel('Recent activity', s.recent_audit.length ? `<div class="tw"><table><tbody>${s.recent_audit.map((x) => `<tr><td class="nowrap small muted">${fmt(x.created_at)}</td><td><strong>${esc(x.action)}</strong><div class="small muted">${esc(x.actor)} · ${esc(x.entity_type)}${x.entity_id ? ' #' + esc(x.entity_id) : ''}</div></td></tr>`).join('')}</tbody></table></div>` : '<div class="state">No activity yet.</div>')}
      ${panel('Front office flow', CH.line([
        { name: 'Arrivals', color: '#c4736f', points: signSeries(a.front_office.arrivals) },
        { name: 'Departures', color: '#b3a76f', points: signSeries(a.front_office.departures) },
      ], { title: 'Arrivals and departures' }), `<span class="sub">${esc(int(a.front_office.nights_booked))} nights booked</span>`)}
    </div>`;

    bindRange((d) => { dashDays = d; pages.dashboard(); });
  };

  pages.analytics = async () => {
    loading();
    let a;
    try { a = (await api('GET', '/admin/analytics', { days: dashDays })).data; } catch (e) { return fail(e); }
    const k = a.kpi;

    root.innerHTML = `
    <div class="hero"><div class="in">
      <div style="flex:1;min-width:240px">
        <h2>Analytics</h2>
        <p>${esc(a.range.from)} → ${esc(a.range.to)} · every figure computed live from the central database</p>
        <div class="facts">
          <span class="fact">Occupancy <b>${esc(pct(k.occupancy_rate))}</b></span>
          <span class="fact">Revenue <b>${esc(money(a.revenue.total))}</b></span>
          <span class="fact">Audit events <b>${esc(int(a.audit.series.reduce((t, p) => t + p.value, 0)))}</b></span>
          <span class="fact">Sign-in failures <b>${esc(int(a.workforce.signins.failed.reduce((t, p) => t + p.value, 0)))}</b></span>
        </div>
      </div>
      <div>${rangeSwitch(a.range.days)}</div>
    </div></div>

    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(215px,1fr))">
      ${metric('Occupancy rate', pct(k.occupancy_rate), `${int(k.rooms_occupied)} of ${int(k.rooms_total)} rooms`, 'rose', 'bed')}
      ${metric('In-house guests', int(k.in_house_guests), `${int(k.in_house_stays)} stays open`, 'khaki', 'users')}
      ${metric('Arrivals today', int(k.arrivals_today), `${int(k.departures_today)} departures due`, 'sage', 'inbox')}
      ${metric('Reservations ahead', int(k.reservations_upcoming), 'Next seven days', 'sky', 'chart')}
      ${metric('Revenue collected', money(a.revenue.total), `${int(a.revenue.count)} payments in ${a.range.days} days`, 'amber', 'coin', sparkOf(a.revenue.series, '#bd873c'))}
      ${metric('Outstanding balance', money(k.outstanding_balance), `${int(k.open_folios)} open folios`, 'brick', 'coin')}
      ${metric('Failed sign-ins', int(k.failed_logins_24h), `Last 24 hours · ${int(k.successful_logins_24h)} succeeded`, 'rose', 'key', sparkOf(a.workforce.signins.failed, '#b1463d'))}
      ${metric('System errors', int(k.errors_24h), 'Last 24 hours', 'brick', 'alert', sparkOf(a.audit.errors, '#b1463d'))}
    </div>

    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(330px,1fr));margin-top:1rem">
      ${panel('Revenue over time', CH.column(a.revenue.series.map((p) => ({ label: p.label, value: p.value })), { title: 'Revenue' }), `<span class="sub">Average ${esc(money(a.revenue.average))} per payment</span>`)}
      ${panel('Revenue by method', CH.bars(a.revenue.by_method.map((m) => ({ label: m.label, value: m.value, tone: 'rose' })), { format: money, empty: 'No payments recorded in this period yet.' }), `<span class="sub">${esc(int(a.revenue.count))} payments</span>`)}
      ${panel('Room status', CH.donut(a.occupancy.by_status, { centreLabel: 'Rooms', title: 'Room status' }) || '', `<span class="sub">${esc(int(k.rooms_available))} ready to sell</span>`)}
      ${panel('Occupancy by room type', CH.bars(a.occupancy.by_type.map((t) => ({ label: t.label, value: t.occupied, tone: 'sage' })), { empty: 'No room types configured yet.' }), `<span class="sub">${esc(int(a.occupancy.by_type.length))} room types</span>`)}
    </div>

    <div class="page-head" style="margin:1.6rem 0 .9rem"><div><h2 style="margin:0;font-size:1.2rem">Access &amp; compliance</h2><p class="page-sub">Who signs in, what fails and what is recorded</p></div></div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(330px,1fr))">
      ${panel('Sign-in activity', CH.line([
        { name: 'Successful', color: '#5d8b58', points: signSeries(a.workforce.signins.ok) },
        { name: 'Failed', color: '#b1463d', points: signSeries(a.workforce.signins.failed) },
      ], { title: 'Sign-in activity' }))}
      ${panel('Why sign-ins failed', CH.bars(a.workforce.failure_reasons.map((f) => ({ label: f.label, value: f.value, tone: 'brick' })), { empty: 'No failed sign-ins in this period.' }))}
      ${panel('Audit activity', CH.line([{ name: 'Audit entries', color: '#b3a76f', points: signSeries(a.audit.series) }, { name: 'System errors', color: '#b1463d', points: signSeries(a.audit.errors) }], { title: 'Audit activity' }))}
    </div>

    <div class="page-head" style="margin:1.6rem 0 .9rem"><div><h2 style="margin:0;font-size:1.2rem">Departments</h2><p class="page-sub">People and roles across the hotel</p></div></div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(330px,1fr))">
      ${panel('Employees by department', CH.bars(a.workforce.by_department.filter((d) => d.total > 0).map((d) => ({ label: d.label, value: d.active, tone: 'rose' })), { empty: 'No employees yet.' }), `<span class="sub">Active staff only</span>`)}
      ${panel('Roles in use', CH.bars(a.workforce.by_role.filter((r) => r.value > 0).map((r) => ({ label: r.label, value: r.value, tone: 'khaki' })), { empty: 'No roles assigned yet.' }))}
      ${panel('Top actions in the audit log', CH.bars(a.audit.by_action.map((x) => ({ label: x.label, value: x.value, tone: 'khaki' })), { empty: 'No audit activity yet.' }))}
    </div>

    <div class="page-head" style="margin:1.6rem 0 .9rem"><div><h2 style="margin:0;font-size:1.2rem">Operations</h2><p class="page-sub">Open work in every department</p></div></div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(330px,1fr))">
      ${panel('Housekeeping', CH.bars([
        { label: 'Pending', value: a.operations.housekeeping.pending, tone: 'khaki' },
        { label: 'In progress', value: a.operations.housekeeping.active, tone: 'rose' },
        { label: 'Awaiting inspection', value: a.operations.housekeeping.inspect, tone: 'sage' },
        { label: 'Overdue', value: a.operations.housekeeping.overdue, tone: 'brick' },
        { label: 'Completed in period', value: a.operations.housekeeping.completed, tone: 'sky' },
      ], { empty: 'No housekeeping tasks yet.' }))}
      ${panel('Maintenance', CH.bars([
        { label: 'Open work orders', value: a.operations.maintenance.open, tone: 'sky' },
        { label: 'Overdue', value: a.operations.maintenance.overdue, tone: 'brick' },
        { label: 'Blocking a room', value: a.operations.maintenance.blocking, tone: 'amber' },
        { label: 'Closed in period', value: a.operations.maintenance.done, tone: 'sage' },
      ], { empty: 'No work orders yet.' }))}
      ${panel('Security', CH.bars(a.operations.security.by_severity.map((v) => ({ label: v.label, value: v.value, tone: 'rose' })), { empty: 'No open incidents.' }), `<span class="sub">${esc(int(a.operations.security.open))} open · ${esc(int(a.operations.security.critical))} high or critical</span>`)}
      ${panel('Kitchen &amp; F&amp;B', CH.bars([
        { label: 'Orders in flight', value: a.operations.kitchen.open_orders, tone: 'amber' },
        { label: 'Orders today', value: a.operations.kitchen.orders_today, tone: 'rose' },
        { label: 'Low stock items', value: a.operations.kitchen.low_stock, tone: 'brick' },
        { label: 'Waste records', value: a.operations.kitchen.waste_records, tone: 'khaki' },
      ], { empty: 'No kitchen activity yet.' }))}
    </div>`;

    bindRange((d) => { dashDays = d; pages.analytics(); });
  };

  pages.users = async () => {
    const [depts, roles] = await Promise.all([deptOptions(), api('GET', '/admin/roles').then((r) => r.data).catch(() => [])]);
    const roleOpts = roles.map((r) => ({ value: r.id, label: r.name }));
    const manage = can('user.manage');
    const createAccounts = manage && can('admin.dashboard');
    const userFields = (create) => [
      ...(create ? [{ name: 'username', label: 'Username', required: true, help: 'letters, numbers . _ -' }] : []),
      { name: 'first_name', label: 'First name', required: true }, { name: 'last_name', label: 'Last name', required: true },
      { name: 'email', label: 'Email', type: 'email', required: true }, { name: 'phone', label: 'Phone' },
      { name: 'department_id', label: 'Department', type: 'select', options: depts, required: true, placeholder: 'Select department…' },
      ...(create ? [{ name: 'role_ids', label: 'Roles', type: 'checkboxes', options: roleOpts, help: 'Department roles only fit employees in that department.' }] : []),
    ];
    const t = DataTable(root, {
      endpoint: '/admin/users', sort: 'created', empty: 'No employees match your filters.',
      filters: [{ name: 'q', placeholder: 'Search name, username, email…', label: 'Search' }, { name: 'department_id', label: 'Department', type: 'select', options: [{ value: '', label: 'All' }, ...depts] }, { name: 'status', label: 'Status', type: 'select', options: [{ value: '', label: 'All' }, { value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }, { value: 'locked', label: 'Locked' }] }],
      toolbar: createAccounts ? [{ label: '+ New employee', cls: 'pri', onClick: () => formModal({ title: 'New employee account', fields: userFields(true), submitLabel: 'Create account', onSubmit: async (v, m) => { const r = await api('POST', '/admin/users', v); m.close(); t.reload(); showTemp('Account created: ' + r.data.user.username, r.data.temporary_password); } }) }] : [],
      columns: [
        { label: 'Employee', sort: 'name', render: (u) => `<strong>${esc(u.first_name)} ${esc(u.last_name)}</strong><div class="small muted">${esc(u.employee_no)} · ${esc(u.username)}</div>` },
        { label: 'Department', sort: 'department', key: 'department' }, { label: 'Roles', render: (u) => esc(u.roles || '—') },
        { label: 'Status', sort: 'status', render: (u) => (Number(u.is_locked) ? badge('Locked', 'warn') : u.status === 'active' ? badge('Active', 'ok') : badge('Inactive', 'mut')) },
        { label: 'Last sign-in', sort: 'last_login', render: (u) => fmt(u.last_login_at) },
      ],
      actions: manage ? (u) => [
        { label: 'Edit', onClick: () => formModal({ title: 'Edit ' + u.username, fields: userFields(false), values: u, onSubmit: async (v, m) => { await api('PUT', '/admin/users/' + u.id, v); m.close(); toast('Account updated', 'ok'); t.reload(); } }) },
        { label: 'Roles', onClick: () => formModal({ title: 'Roles for ' + u.username, fields: [{ name: 'role_ids', label: 'Assigned roles', type: 'checkboxes', options: roleOpts }], values: { role_ids: (u.role_ids || '').split(',').filter(Boolean) }, onSubmit: async (v, m) => { await api('PUT', `/admin/users/${u.id}/roles`, v); m.close(); toast('Roles updated', 'ok'); t.reload(); } }) },
        { label: 'Reset password', onClick: async () => { if (await confirmDialog(`Generate a new temporary password for ${u.username}? Their current password and sessions will stop working.`, { confirmLabel: 'Reset password', danger: true })) { try { const r = await api('POST', `/admin/users/${u.id}/reset-password`); showTemp('New password for ' + u.username, r.data.temporary_password); t.reload(); } catch (e) { toast(e.message, 'bad'); } } } },
        ...(Number(u.is_locked) ? [{ label: 'Unlock', onClick: async () => { try { await api('POST', `/admin/users/${u.id}/status`, { action: 'unlock' }); toast('Account unlocked', 'ok'); t.reload(); } catch (e) { toast(e.message, 'bad'); } } }] : []),
        u.status === 'active'
          ? { label: 'Deactivate', danger: true, onClick: async () => { if (await confirmDialog(`Deactivate ${u.username}? They will be signed out immediately.`, { confirmLabel: 'Deactivate', danger: true })) { try { await api('POST', `/admin/users/${u.id}/status`, { action: 'deactivate' }); toast('Account deactivated', 'ok'); t.reload(); } catch (e) { toast(e.message, 'bad'); } } } }
          : { label: 'Activate', onClick: async () => { try { await api('POST', `/admin/users/${u.id}/status`, { action: 'activate' }); toast('Account activated', 'ok'); t.reload(); } catch (e) { toast(e.message, 'bad'); } } },
      ] : null,
    });
  };

  pages.departments = async () => {
    const manage = can('department.manage');
    const t = DataTable(root, {
      endpoint: '/admin/departments', perPage: 20, empty: 'No departments.',
      columns: [{ label: 'Code', key: 'code' }, { label: 'Department', render: (d) => `<strong>${esc(d.name)}</strong><div class="small muted">${esc(d.description || '')}</div>` }, { label: 'Employees', render: (d) => `${Number(d.active_user_count)} active / ${Number(d.user_count)}` }, { label: 'Status', render: (d) => (Number(d.is_active) ? badge('Active', 'ok') : badge('Inactive', 'mut')) }],
      actions: manage ? (d) => [{ label: 'Edit', onClick: () => formModal({ title: 'Edit department', values: { ...d, is_active: Number(d.is_active) === 1 }, fields: [{ name: 'name', label: 'Name', required: true }, { name: 'description', label: 'Description', type: 'textarea' }, { name: 'is_active', label: 'Department is active', type: 'switch' }], onSubmit: async (v, m) => { await api('PUT', '/admin/departments/' + d.id, v); m.close(); toast('Department updated', 'ok'); t.reload(); } }) }] : null,
    });
  };

  pages.roles = async () => {
    const manage = can('role.manage');
    const [perms, depts] = await Promise.all([api('GET', '/admin/permissions').then((r) => r.data), deptOptions()]);
    const t = DataTable(root, {
      endpoint: '/admin/roles', perPage: 50, empty: 'No roles.',
      toolbar: manage ? [{ label: '+ New role', cls: 'pri', onClick: () => formModal({ title: 'New role', fields: [{ name: 'code', label: 'Code', required: true, help: 'lowercase letters, numbers, underscore' }, { name: 'name', label: 'Name', required: true }, { name: 'description', label: 'Description', type: 'textarea' }, { name: 'department_id', label: 'Limit to department', type: 'select', options: depts, placeholder: 'Any department' }], onSubmit: async (v, m) => { await api('POST', '/admin/roles', v); m.close(); toast('Role created', 'ok'); t.reload(); } }) }] : [],
      columns: [{ label: 'Role', render: (r) => `<strong>${esc(r.name)}</strong><div class="small muted">${esc(r.code)}${r.description ? ' · ' + esc(r.description) : ''}</div>` }, { label: 'Department', render: (r) => esc(r.department || 'Any') }, { label: 'Employees', key: 'user_count' }, { label: 'Permissions', render: (r) => (r.permission_ids || []).length }, { label: 'Type', render: (r) => (Number(r.is_system) ? badge('Built-in', 'info') : badge('Custom', 'mut')) }],
      actions: (r) => [
        { label: manage ? 'Permissions' : 'View permissions', onClick: () => {
          const groups = {}; perms.forEach((p) => { (groups[p.module] = groups[p.module] || []).push(p); });
          const has = new Set((r.permission_ids || []).map(Number));
          const form = document.createElement('div');
          form.innerHTML = Object.entries(groups).map(([mod, list]) => `<div class="card" style="margin-bottom:.7rem"><label class="chk"><input type="checkbox" data-all="${esc(mod)}" ${manage ? '' : 'disabled'}> <strong>${esc(mod.replace('_', ' '))}</strong></label><div class="chk-grid">${list.map((p) => `<label class="chk" title="${esc(p.name)}"><input type="checkbox" data-m="${esc(mod)}" value="${p.id}" ${has.has(Number(p.id)) ? 'checked' : ''} ${manage ? '' : 'disabled'}> <span>${esc(p.name)}</span></label>`).join('')}</div></div>`).join('');
          form.querySelectorAll('[data-all]').forEach((a) => { a.onchange = () => form.querySelectorAll(`[data-m="${a.dataset.all}"]`).forEach((c) => { c.checked = a.checked; }); });
          modal({ title: 'Permissions: ' + r.name, size: 'lg', body: form, buttons: manage ? [{ label: 'Cancel' }, { label: 'Save permissions', cls: 'pri', onClick: async (m) => { try { await api('PUT', `/admin/roles/${r.id}/permissions`, { permission_ids: [...form.querySelectorAll('[data-m]:checked')].map((c) => Number(c.value)) }); m.close(); toast('Permissions saved', 'ok'); t.reload(); } catch (e) { toast(e.message, 'bad'); } } }] : [{ label: 'Close', cls: 'pri' }] });
        } },
        ...(manage ? [{ label: 'Edit', onClick: () => formModal({ title: 'Edit role', values: r, fields: [{ name: 'name', label: 'Name', required: true }, { name: 'description', label: 'Description', type: 'textarea' }], onSubmit: async (v, m) => { await api('PUT', '/admin/roles/' + r.id, v); m.close(); toast('Role updated', 'ok'); t.reload(); } }) }] : []),
        ...(manage && !Number(r.is_system) ? [{ label: 'Delete', danger: true, onClick: async () => { if (await confirmDialog(`Delete role "${r.name}"?`, { confirmLabel: 'Delete', danger: true })) { try { await api('DELETE', '/admin/roles/' + r.id); toast('Role deleted', 'ok'); t.reload(); } catch (e) { toast(e.message, 'bad'); } } } }] : []),
      ],
    });
  };

  pages.settings = async () => {
    loading();
    const rows = (await api('GET', '/admin/settings')).data; const manage = can('settings.manage');
    root.innerHTML = `<form class="card" id="sf"><div class="alert bad" hidden></div><div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">${rows.map((s) => `<label class="f" data-f="${esc(s.setting_key)}">${esc(s.description || s.setting_key)}<span class="help"> (${esc(s.setting_key)})</span><input class="in" name="${esc(s.setting_key)}" value="${esc(s.setting_value)}" ${manage ? '' : 'disabled'}><span class="err" hidden></span></label>`).join('')}</div>${manage ? '<button class="btn pri" type="submit">Save settings</button>' : '<p class="muted">You have read-only access.</p>'}</form>`;
    const f = document.getElementById('sf');
    f.onsubmit = async (e) => {
      e.preventDefault(); f.querySelectorAll('.err').forEach((x) => { x.hidden = true; });
      try { await api('PUT', '/admin/settings', { settings: Object.fromEntries(new FormData(f)) }); toast('Settings saved', 'ok'); }
      catch (er) { Object.entries(er.errors || {}).forEach(([k, m]) => { const w = f.querySelector(`[data-f="${CSS.escape(k)}"] .err`); if (w) { w.textContent = m; w.hidden = false; } }); toast(er.message, 'bad'); }
    };
  };

  pages.audit = () => DataTable(root, {
    endpoint: '/admin/audit-logs', perPage: 20, sort: 'created', empty: 'No audit entries match.',
    filters: [{ name: 'q', placeholder: 'Action or record type…', label: 'Search' }, { name: 'from', type: 'date', label: 'From' }, { name: 'to', type: 'date', label: 'To' }],
    columns: [{ label: 'When', sort: 'created', render: (a) => `<span class="nowrap">${fmt(a.created_at)}</span>` }, { label: 'Who', key: 'actor' }, { label: 'Action', sort: 'action', render: (a) => `<strong>${esc(a.action)}</strong>` }, { label: 'Record', sort: 'entity', render: (a) => esc(a.entity_type) + (a.entity_id ? ' #' + esc(a.entity_id) : '') }, { label: 'IP', key: 'ip_address' }],
    actions: (a) => (a.old_values || a.new_values ? [{ label: 'Details', onClick: () => modal({ title: `Audit #${a.id}: ${a.action}`, size: 'lg', body: `<div class="grid" style="grid-template-columns:1fr 1fr"><div><h2>Before</h2>${pre(a.old_values)}</div><div><h2>After</h2>${pre(a.new_values)}</div></div>`, buttons: [{ label: 'Close', cls: 'pri' }] }) }] : []),
  });

  pages.logins = () => DataTable(root, {
    endpoint: '/admin/login-attempts', perPage: 20, sort: 'time', empty: 'No sign-in activity yet.',
    filters: [{ name: 'q', placeholder: 'Username or IP…', label: 'Search' }, { name: 'success', label: 'Result', type: 'select', options: [{ value: '', label: 'All' }, { value: '1', label: 'Successful' }, { value: '0', label: 'Failed' }] }],
    columns: [{ label: 'When', sort: 'time', render: (l) => `<span class="nowrap">${fmt(l.attempted_at)}</span>` }, { label: 'Username', sort: 'username', key: 'username' }, { label: 'IP', sort: 'ip', key: 'ip_address' }, { label: 'Result', render: (l) => (Number(l.success) ? badge('Success', 'ok') : badge('Failed', 'bad')) }, { label: 'Reason', render: (l) => esc(l.reason || '') }, { label: 'Device', render: (l) => `<span class="small muted">${esc((l.user_agent || '').slice(0, 60))}</span>` }],
  });

  pages.sessions = () => { const t = DataTable(root, {
    endpoint: '/admin/sessions', perPage: 20, empty: 'No active sessions.',
    columns: [{ label: 'Employee', render: (s) => `<strong>${esc(s.user)}</strong><div class="small muted">${esc(s.username)}</div>` }, { label: 'IP', key: 'ip_address' }, { label: 'Last activity', render: (s) => fmt(s.last_activity) }, { label: 'Device', render: (s) => `<span class="small muted">${esc((s.user_agent || '').slice(0, 70))}</span>` }, { label: '', render: (s) => (s.is_current ? badge('This session', 'info') : '') }],
    actions: (s) => (s.is_current ? [] : [{ label: 'Revoke', danger: true, onClick: async () => { if (await confirmDialog(`Sign ${s.user} out of this session?`, { confirmLabel: 'Revoke', danger: true })) { try { await api('DELETE', '/admin/sessions/' + s.handle); toast('Session revoked', 'ok'); t.reload(); } catch (e) { toast(e.message, 'bad'); } } } }]),
  }); };

  pages.errors = () => DataTable(root, {
    endpoint: '/admin/error-logs', perPage: 20, empty: 'No errors recorded. 🎉'.replace(' 🎉', ''),
    filters: [{ name: 'q', placeholder: 'Error id, message or path…', label: 'Search' }],
    columns: [{ label: 'When', render: (e) => `<span class="nowrap">${fmt(e.created_at)}</span>` }, { label: 'Error id', render: (e) => `<code>${esc(e.error_id)}</code>` }, { label: 'Request', render: (e) => esc((e.method || '') + ' ' + (e.path || '')) }, { label: 'Message', render: (e) => esc((e.message || '').slice(0, 120)) }],
    actions: (e) => [{ label: 'Details', onClick: () => modal({ title: 'Error ' + e.error_id, body: `<p>${esc(e.message)}</p>${pre(e.context)}`, buttons: [{ label: 'Close', cls: 'pri' }] }) }],
  });

  pages.approvals = () => { const t = DataTable(root, {
    endpoint: '/approvals', empty: 'No approval requests.',
    filters: [{ name: 'status', label: 'Status', type: 'select', options: [{ value: '', label: 'All' }, { value: 'pending', label: 'Pending' }, { value: 'approved', label: 'Approved' }, { value: 'rejected', label: 'Rejected' }] }],
    columns: [
      { label: '#', key: 'id' },
      { label: 'Request', render: (a) => {
        const u = a.request_data;
        if (a.request_type === 'user.create' && u) {
          return `<strong>New employee account</strong><div class="small">${esc(u.first_name)} ${esc(u.last_name)} · ${esc(u.department_name)}${u.job_title ? ' · ' + esc(u.job_title) : ''}</div><div class="small muted">${esc(u.username)} · ${esc(u.email)}${u.phone ? ' · ' + esc(u.phone) : ''}</div>`;
        }
        return `<strong>${esc(a.request_type)}</strong><div class="small muted">${esc(a.entity_type)} #${esc(a.entity_id)}</div>`;
      } },
      { label: 'Reason', key: 'reason' }, { label: 'Requested by', key: 'requested_by' },
      { label: 'Status', render: (a) => badge(a.status, { pending: 'warn', approved: 'ok', rejected: 'bad' }[a.status] || 'mut') },
      { label: 'Decided by', render: (a) => esc(a.decided_by || '—') },
    ],
    actions: (a) => (a.can_decide ? ['approved', 'rejected'].map((d) => ({
      label: d === 'approved' ? 'Approve' : 'Reject',
      danger: d === 'rejected',
      onClick: () => formModal({
        title: (d === 'approved' ? 'Approve' : 'Reject') + ' request #' + a.id,
        fields: [{ name: 'note', label: 'Note', type: 'textarea' }],
        submitLabel: d === 'approved' ? 'Approve' : 'Reject',
        onSubmit: async (v, m) => {
          const endpoint = a.request_type === 'user.create'
            ? `/admin/account-requests/${a.id}/decide`
            : `/approvals/${a.id}/decide`;
          const result = await api('POST', endpoint, { decision: d, note: v.note });
          m.close();
          t.reload();
          if (result.data.temporary_password) {
            showTemp('Account approved: ' + result.data.account.username, result.data.temporary_password);
          } else {
            toast('Decision recorded', 'ok');
          }
        },
      }),
    })) : []),
  }); };

  pages.notifications = async () => {
    const depts = await deptOptions();
    const t = DataTable(root, {
      endpoint: '/admin/notifications', empty: 'No notifications sent yet.',
      toolbar: [{ label: '+ Broadcast message', cls: 'pri', onClick: () => formModal({ title: 'Broadcast notification', fields: [{ name: 'title', label: 'Title', required: true }, { name: 'message', label: 'Message', type: 'textarea', required: true }, { name: 'severity', label: 'Severity', type: 'select', options: [{ value: 'info', label: 'Info' }, { value: 'warning', label: 'Warning' }, { value: 'critical', label: 'Critical' }], required: true }, { name: 'department_id', label: 'Send to', type: 'select', options: depts, placeholder: 'All active employees' }], values: { severity: 'info' }, submitLabel: 'Send', onSubmit: async (v, m) => { const r = await api('POST', '/admin/notifications/broadcast', v); m.close(); toast(`Sent to ${r.data.recipients} employee(s)`, 'ok'); t.reload(); } }) }],
      columns: [{ label: 'When', render: (n) => fmt(n.created_at) }, { label: 'Severity', render: (n) => badge(n.severity, sevKind[n.severity]) }, { label: 'Notification', render: (n) => `<strong>${esc(n.title)}</strong><div class="small muted">${esc(n.message)}</div>` }, { label: 'Event', render: (n) => `<span class="small">${esc(n.type)}${n.entity_type ? ' · ' + esc(n.entity_type) + ' #' + esc(n.entity_id) : ''}</span>` }, { label: 'Read', render: (n) => `${Number(n.read_count)} / ${Number(n.recipients)}` }],
    });
  };


  pages.database = async () => {
    loading();
    const d = (await api('GET', '/admin/database')).data; const t = d.totals;
    const stat = (v, l, cls) => `<div class="card stat ${cls || ''}"><div class="v">${esc(v)}</div><div class="l">${esc(l)}</div></div>`;
    const showTable = async (name) => {
      try {
        const x = (await api('GET', '/admin/database/tables/' + encodeURIComponent(name))).data;
        const fk = Object.fromEntries(x.references.map((r) => [r.column_name, r.references_table]));
        modal({ title: x.name, size: 'lg', body: `<p class="muted">${esc(x.group)} · ${x.row_count} row(s)</p><p>${esc(x.comment)}</p>
          <div class="tw"><table><thead><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Links to</th></tr></thead><tbody>${x.columns.map((c) => `<tr><td><code>${esc(c.name)}</code></td><td>${esc(c.type)}${c.generated ? '<div class="small muted">generated</div>' : ''}</td><td>${c.nullable === 'YES' ? 'yes' : 'no'}</td><td>${esc({ PRI: 'PK', UNI: 'UQ', MUL: 'idx' }[c.key] || '')}</td><td class="small">${esc(c.default ?? '')}</td><td>${fk[c.name] ? '→ ' + esc(fk[c.name]) : ''}</td></tr>`).join('')}</tbody></table></div>
          ${x.referenced_by.length ? `<p class="small" style="margin-top:.8rem"><strong>Referenced by:</strong> ${x.referenced_by.map((r) => `<code>${esc(r.from_table)}.${esc(r.from_column)}</code>`).join(', ')}</p>` : ''}
          ${x.protections.length ? `<p class="small"><strong>Protected by trigger:</strong> ${x.protections.map((r) => esc(r.event.toLowerCase())).join(', ')}: this history cannot be changed or deleted.</p>` : ''}`, buttons: [{ label: 'Close', cls: 'pri' }] });
      } catch (e) { toast(e.message, 'bad'); }
    };
    root.innerHTML = `<div class="grid cards">${stat(t.tables, 'Tables')}${stat(t.foreign_keys, 'Foreign keys')}${stat(t.triggers, 'History-protection triggers')}${stat(t.size_mb + ' MB', 'Database size')}${stat(t.migrations_applied + (t.migrations_pending ? ' (+' + t.migrations_pending + ' pending)' : ''), 'Migrations applied', t.migrations_pending ? 'warn' : '')}</div>
    <div class="card" style="margin-top:1rem"><h2>Schema health: <code>${esc(d.database)}</code></h2>${d.health.map((h) => `<div style="display:flex;gap:.6rem;margin:.35rem 0">${badge(h.ok ? 'OK' : 'Check', h.ok ? 'ok' : 'bad')}<div><strong>${esc(h.check)}</strong><div class="small muted">${esc(h.detail)}</div></div></div>`).join('')}</div>
    <div class="alert info" style="margin-top:1rem"><strong>Maintenance guidance.</strong> Change the schema only by adding a new numbered file in <code>database/migrations</code> and running <code>php database/migrate.php migrate</code>: never edit tables by hand or edit a migration that has already been applied. Take a backup first (Backups page). Ledger and history tables are protected by triggers, so corrections are made by posting new reversing records. Row counts below are estimates.</div>
    ${d.groups.map((g) => `<div class="card" style="margin-top:1rem"><h2>Group ${esc(g.code)}: ${esc(g.name)} <span class="muted small">(${g.tables.length} tables)</span></h2><div class="tw"><table><thead><tr><th>Table</th><th>Purpose</th><th>≈ Rows</th><th>Size</th><th>FKs</th><th></th></tr></thead><tbody>${g.tables.map((x) => `<tr><td><code>${esc(x.name)}</code> ${x.protected ? badge('protected', 'info') : ''}${x.exists ? '' : badge('missing', 'bad')}</td><td class="small">${esc(x.comment)}</td><td>${x.rows_estimate}</td><td class="nowrap">${x.size_kb} KB</td><td>${x.foreign_keys}</td><td><button class="btn sm" data-t="${esc(x.name)}">Columns</button></td></tr>`).join('')}</tbody></table></div></div>`).join('')}
    <div class="card" style="margin-top:1rem"><h2>Migration history</h2><div class="tw"><table><tbody>${d.migrations.map((m) => `<tr><td><code>${esc(m.file)}</code></td><td>${m.applied_at ? badge('applied', 'ok') + ' <span class="small muted">' + fmt(m.applied_at) + '</span>' : badge('pending', 'warn')}</td></tr>`).join('')}</tbody></table></div></div>`;
    root.querySelectorAll('[data-t]').forEach((b) => { b.onclick = () => showTable(b.dataset.t); });
  };

  pages.backups = async () => {
    const draw = async () => {
      let rows = []; try { rows = (await api('GET', '/admin/backups')).data; } catch (e) { return fail(e); }
      root.innerHTML = `<div class="alert info"><strong>Backup and recovery guidance.</strong> Create a backup before upgrades or bulk changes and copy files off this computer regularly. To restore, stop users from signing in, then run <code>mysql -u root ihms &lt; backup-file.sql</code> (XAMPP: <code>C:\\xampp\\mysql\\bin\\mysql.exe</code>). Backups contain personal data: store them securely. Routine maintenance: <code>OPTIMIZE TABLE</code> monthly on large tables, and keep audit logs (they are never deleted by the app).</div>
      <div class="toolbar"><span class="sp"></span><button class="btn pri" id="mk">Create backup now</button></div>
      <div class="tw"><table><thead><tr><th>File</th><th>Size</th><th>Created</th><th></th></tr></thead><tbody>${rows.length ? rows.map((b) => `<tr><td><code>${esc(b.name)}</code></td><td>${(b.size_bytes / 1024).toFixed(1)} KB</td><td>${fmt(b.created_at)}</td><td><a class="btn sm" href="${window.IHMS.BASE}/api/v1/admin/backups/${encodeURIComponent(b.name)}/download">Download</a></td></tr>`).join('') : '<tr><td colspan="4"><div class="state">No backups yet.</div></td></tr>'}</tbody></table></div>`;
      document.getElementById('mk').onclick = async (ev) => { ev.target.disabled = true; ev.target.innerHTML = '<span class="spin"></span>Backing up…'; try { await api('POST', '/admin/backups'); toast('Backup created', 'ok'); } catch (e) { toast(e.message, 'bad'); } draw(); };
    };
    loading(); draw();
  };

  const run = pages[pageKey];
  if (run) Promise.resolve(run()).catch(fail);
})();
