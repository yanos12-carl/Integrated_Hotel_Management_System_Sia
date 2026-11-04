/* Security department pages backed by the shared IHMS API and database. */
(function () {
  'use strict';
  const { api, esc, fmt, badge, toast, modal, confirmDialog, formModal, DataTable, can } = window.IHMS;
  const root = document.getElementById('page');
  const page = document.body.dataset.page.replace('security-', '');
  const statusTone = { reported: 'warn', assigned: 'info', investigating: 'info', escalated: 'bad', resolved: 'ok', closed: 'mut' };
  const severityTone = { low: 'mut', medium: 'warn', high: 'bad', critical: 'bad' };
  const options = (values) => values.map((value) => ({ value, label: value.replaceAll('_', ' ').replace(/\b\w/g, (s) => s.toUpperCase()) }));
  const localDateTime = () => {
    const d = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
    return d.toISOString().slice(0, 16);
  };
  const dateOnly = () => localDateTime().slice(0, 10);
  const showError = (e) => { root.innerHTML = `<div class="alert bad">${esc(e.message)}</div>`; };
  const stat = (label, value, tone) => `<div class="card stat ${tone || 't-khaki'}"><div class="v">${esc(value)}</div><div class="l">${esc(label)}</div></div>`;

  async function securityUsers() {
    const rows = (await api('GET', '/security/users')).data;
    return rows.map((u) => ({ value: u.id, label: `${u.first_name} ${u.last_name} (${u.username})` }));
  }

  function incidentsTable(users) {
    let table;
    table = DataTable(root, {
      endpoint: '/security/incidents',
      empty: 'No incidents have been reported.',
      filters: [
        { name: 'q', label: 'Search', placeholder: 'Incident, title or location…' },
        { name: 'status', label: 'Status', type: 'select', options: [{ value: '', label: 'All statuses' }, ...options(['reported', 'assigned', 'investigating', 'escalated', 'resolved', 'closed'])] },
      ],
      toolbar: [{
        label: '+ Report incident', cls: 'pri', onClick: () => formModal({
          title: 'Report a security incident',
          fields: [
            { name: 'category', label: 'Category', type: 'select', required: true, options: options(['theft', 'missing_property', 'disturbance', 'accident', 'damage', 'emergency', 'safety', 'trespass', 'other']) },
            { name: 'severity', label: 'Severity', type: 'select', required: true, options: options(['low', 'medium', 'high', 'critical']) },
            { name: 'title', label: 'Title', required: true },
            { name: 'description', label: 'Description', type: 'textarea', required: true },
            { name: 'location_description', label: 'Location' },
            { name: 'occurred_at', label: 'When it occurred', type: 'datetime-local', required: true },
          ],
          values: { severity: 'low', occurred_at: localDateTime() },
          submitLabel: 'Report incident',
          onSubmit: async (v, m) => { await api('POST', '/security/incidents', v); m.close(); toast('Incident reported', 'ok'); table.reload(); },
        }),
      }],
      columns: [
        { label: 'Incident', render: (i) => `<strong>${esc(i.incident_no)}</strong><div class="small muted">${esc(i.title)}</div>` },
        { label: 'Category', render: (i) => esc(i.category.replaceAll('_', ' ')) },
        { label: 'Severity', render: (i) => badge(i.severity, severityTone[i.severity]) },
        { label: 'Status', render: (i) => badge(i.status.replaceAll('_', ' '), statusTone[i.status]) },
        { label: 'Occurred', render: (i) => `<span class="nowrap">${fmt(i.occurred_at)}</span>` },
        { label: 'Assigned', render: (i) => esc(i.assigned_to || '—') },
      ],
      actions: (i) => [{ label: 'Details', onClick: () => incidentDetails(i, users, table) }],
    });
    return table;
  }

  async function incidentDetails(incident, users, table) {
    try {
      const i = (await api('GET', `/security/incidents/${incident.id}`)).data;
      const notes = i.updates.length
        ? i.updates.map((u) => `<div class="item"><strong>${esc(u.update_type.replaceAll('_', ' '))}</strong> · ${esc(u.created_by)} · ${fmt(u.created_at)}<p>${esc(u.note)}</p></div>`).join('')
        : '<p class="muted">No updates recorded.</p>';
      const evidence = i.evidence.length
        ? i.evidence.map((e) => `<div class="item"><strong>${esc(e.camera_code)}</strong> · ${fmt(e.footage_from)}–${fmt(e.footage_to)}<div class="small muted">Internal reference: ${esc(e.storage_ref || 'not provided')}</div></div>`).join('')
        : '<p class="muted">No CCTV references linked.</p>';
      const details = modal({
        title: `${i.incident_no}: ${i.title}`,
        size: 'lg',
        body: `<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
          <div><p><strong>Status</strong><br>${badge(i.status, statusTone[i.status])}</p><p><strong>Severity</strong><br>${badge(i.severity, severityTone[i.severity])}</p><p><strong>Category</strong><br>${esc(i.category)}</p></div>
          <div><p><strong>Location</strong><br>${esc(i.location_description || '—')}</p><p><strong>Occurred</strong><br>${fmt(i.occurred_at)}</p><p><strong>Reported by</strong><br>${esc(i.reporter)}</p></div>
        </div><p>${esc(i.description)}</p><h3>Investigation updates</h3>${notes}<h3>CCTV references</h3>${evidence}`,
        buttons: [
          { label: 'Add update', onClick: () => formModal({ title: 'Add investigation update', fields: [{ name: 'update_type', label: 'Update type', type: 'select', required: true, options: options(['note', 'investigation', 'evidence']) }, { name: 'note', label: 'Update', type: 'textarea', required: true }], values: { update_type: 'note' }, submitLabel: 'Add update', onSubmit: async (v, m) => { await api('POST', `/security/incidents/${i.id}/updates`, v); m.close(); details.close(); toast('Update recorded', 'ok'); incidentDetails(i, users, table); } }) },
          { label: 'Assign', onClick: () => formModal({ title: 'Assign incident', fields: [{ name: 'assigned_to', label: 'Security team member', type: 'select', required: true, options: users, placeholder: 'Select a person' }], submitLabel: 'Assign', onSubmit: async (v, m) => { await api('POST', `/security/incidents/${i.id}/assign`, v); m.close(); details.close(); toast('Incident assigned', 'ok'); table.reload(); } }) },
          { label: 'Change status', onClick: () => formModal({ title: 'Update incident status', fields: [{ name: 'status', label: 'Status', type: 'select', required: true, options: options(['reported', 'assigned', 'investigating', 'escalated', 'resolved', 'closed']) }, { name: 'note', label: 'Update note', type: 'textarea' }], values: { status: i.status }, submitLabel: 'Save status', onSubmit: async (v, m) => { await api('POST', `/security/incidents/${i.id}/status`, v); m.close(); details.close(); toast('Status updated', 'ok'); table.reload(); } }) },
          { label: 'Close', cls: 'pri' },
        ],
      });
    } catch (e) { toast(e.message, 'bad'); }
  }

  const pages = {};
  pages.dashboard = async () => {
    root.innerHTML = '<div class="state"><span class="spin"></span>Loading…</div>';
    try {
      const s = (await api('GET', '/security/summary')).data;
      const links = [
        ['Incidents','/security/incidents','security.incident.view'],
        ['Visitors','/security/visitors','visitor.manage'],
        ['Patrols','/security/patrols','patrol.manage'],
        ['Shifts','/security/shifts','patrol.manage'],
        ['CCTV','/security/evidence','security.evidence.view'],
        ['Inspections','/security/inspections','patrol.manage'],
      ].filter((item) => can(item[2]));
      root.innerHTML = `<div class="grid security-overview">
        ${stat('Open incidents', s.incidents_open, 't-rose')}
        ${stat('High / critical incidents', s.incidents_critical, s.incidents_critical ? 'bad' : 't-sage')}
        ${stat('Visitors currently inside', s.visitors_inside, 't-sky')}
        ${stat('Active shifts today', s.shifts_today, 't-khaki')}
        ${stat('Inspections needing attention', s.inspections_needing_action, s.inspections_needing_action ? 'warn' : 't-sage')}
      </div><section class="security-shortcuts" aria-label="Security sections">
        <span class="small muted">Today: ${esc(s.patrols_today)} patrol checkpoints recorded</span>
        <div class="security-shortcut-list">
          ${links.map(([label,href]) => `<a class="security-shortcut" href="${esc(window.IHMS.BASE + href)}">${esc(label)}<span aria-hidden="true">→</span></a>`).join('')}
        </div>
      </section>`;
    } catch (e) { showError(e); }
  };

  pages.incidents = async () => {
    try { incidentsTable(await securityUsers()); } catch (e) { showError(e); }
  };

  pages.visitors = () => {
    let table;
    table = DataTable(root, {
      endpoint: '/security/visitors', empty: 'No visitor records.',
      filters: [
        { name: 'q', label: 'Search', placeholder: 'Visitor name, badge or purpose…' },
        { name: 'status', label: 'Presence', type: 'select', options: [{ value: '', label: 'Everyone' }, { value: 'in', label: 'Checked in' }, { value: 'out', label: 'Checked out' }] },
      ],
      toolbar: [{ label: '+ Check in visitor', cls: 'pri', onClick: () => formModal({
        title: 'Check in visitor',
        fields: [
          { name: 'visitor_name', label: 'Visitor name', required: true },
          { name: 'id_type', label: 'ID type' }, { name: 'id_number', label: 'ID number' },
          { name: 'purpose', label: 'Purpose of visit' }, { name: 'room_id', label: 'Room ID', type: 'number' },
          { name: 'badge_no', label: 'Visitor badge number' },
        ],
        submitLabel: 'Check in',
        onSubmit: async (v, m) => { if (!v.room_id) delete v.room_id; await api('POST', '/security/visitors', v); m.close(); toast('Visitor checked in', 'ok'); table.reload(); },
      }) }],
      columns: [
        { label: 'Visitor', render: (v) => `<strong>${esc(v.visitor_name)}</strong><div class="small muted">${esc(v.id_type || '')}${v.id_number ? ' · ' + esc(v.id_number) : ''}</div>` },
        { label: 'Purpose', key: 'purpose' }, { label: 'Room', key: 'room_id' }, { label: 'Badge', key: 'badge_no' },
        { label: 'Checked in', render: (v) => fmt(v.checked_in_at) },
        { label: 'Status', render: (v) => badge(v.status === 'in' ? 'Inside' : 'Checked out', v.status === 'in' ? 'warn' : 'mut') },
      ],
      actions: (v) => v.status === 'in' ? [{ label: 'Check out', onClick: async () => {
        if (await confirmDialog(`Check ${v.visitor_name} out?`, { confirmLabel: 'Check out' })) {
          try { await api('POST', `/security/visitors/${v.id}/checkout`); toast('Visitor checked out', 'ok'); table.reload(); } catch (e) { toast(e.message, 'bad'); }
        }
      } }] : [],
    });
  };

  pages.shifts = async () => {
    let users, table;
    try { users = await securityUsers(); } catch (e) { return showError(e); }
    table = DataTable(root, {
      endpoint: '/security/shifts', empty: 'No guard shifts scheduled.',
      filters: [{ name: 'date', label: 'Shift date', type: 'date' }],
      toolbar: [
        { label: '+ Schedule shift', cls: 'pri', onClick: () => formModal({
          title: 'Schedule guard shift',
          fields: [
            { name: 'guard_user_id', label: 'Guard', type: 'select', required: true, placeholder: 'Select a guard', options: users },
            { name: 'shift_date', label: 'Date', type: 'date', required: true },
            { name: 'shift_type', label: 'Shift', type: 'select', required: true, options: options(['morning', 'afternoon', 'night']) },
            { name: 'starts_at', label: 'Starts at', type: 'datetime-local', required: true },
            { name: 'ends_at', label: 'Ends at', type: 'datetime-local', required: true },
            { name: 'post', label: 'Assigned post' },
          ],
          values: { shift_date: dateOnly(), shift_type: 'morning' },
          submitLabel: 'Schedule shift',
          onSubmit: async (v, m) => { await api('POST', '/security/shifts', v); m.close(); toast('Shift scheduled', 'ok'); table.reload(); },
        }) },
        { label: 'Handovers', onClick: () => handovers(users) },
      ],
      columns: [
        { label: 'Guard', render: (s) => `<strong>${esc(s.first_name)} ${esc(s.last_name)}</strong><div class="small muted">${esc(s.guard_username)}</div>` },
        { label: 'Date / shift', render: (s) => `${esc(s.shift_date)} · ${esc(s.shift_type)}` },
        { label: 'Hours', render: (s) => `${fmt(s.starts_at)} – ${fmt(s.ends_at)}` },
        { label: 'Post', render: (s) => esc(s.post || '—') },
        { label: 'Status', render: (s) => badge(s.status, s.status === 'on_duty' ? 'ok' : s.status === 'absent' ? 'bad' : 'mut') },
      ],
      actions: (s) => s.status === 'scheduled' ? ['on_duty', 'absent'].map((status) => ({ label: status === 'on_duty' ? 'Start' : 'Mark absent', onClick: async () => {
        try { await api('POST', `/security/shifts/${s.id}/status`, { status }); toast('Shift updated', 'ok'); table.reload(); } catch (e) { toast(e.message, 'bad'); }
      } })) : s.status === 'on_duty' ? [{ label: 'Complete', onClick: async () => {
        try { await api('POST', `/security/shifts/${s.id}/status`, { status: 'completed' }); toast('Shift completed', 'ok'); table.reload(); } catch (e) { toast(e.message, 'bad'); }
      } }] : [],
    });
  };

  async function handovers(users) {
    root.innerHTML = '<div class="state"><span class="spin"></span>Loading…</div>';
    let shifts;
    try { shifts = (await api('GET', '/security/shifts', { per_page: 100 })).data; } catch (e) { return showError(e); }
    const myShifts = shifts.filter((s) => Number(s.guard_user_id) === Number(window.IHMS.boot.user.id));
    let table;
    table = DataTable(root, {
      endpoint: '/security/handovers', empty: 'No shift handovers.',
      toolbar: [{ label: '+ Record handover', cls: 'pri', onClick: () => formModal({
        title: 'Record shift handover',
        fields: [
          { name: 'from_shift_id', label: 'Your shift', type: 'select', required: true, placeholder: 'Select your shift', options: myShifts.map((s) => ({ value: s.id, label: `${s.shift_date} · ${s.shift_type} · ${s.post || 'No post'}` })) },
          { name: 'to_shift_id', label: 'Receiving shift (optional)', type: 'select', placeholder: 'Not specified', options: shifts.map((s) => ({ value: s.id, label: `${s.shift_date} · ${s.first_name} ${s.last_name} · ${s.shift_type}` })) },
          { name: 'summary', label: 'Handover summary', type: 'textarea', required: true },
          { name: 'open_issues', label: 'Open issues', type: 'textarea' },
          { name: 'equipment_status', label: 'Equipment status' },
        ],
        submitLabel: 'Record handover',
        onSubmit: async (v, m) => { if (!v.to_shift_id) delete v.to_shift_id; await api('POST', '/security/handovers', v); m.close(); toast('Handover recorded', 'ok'); table.reload(); },
      }) }],
      columns: [
        { label: 'From / to', render: (h) => `${esc(h.from_username)} → ${esc(h.to_username || 'Unassigned')}` },
        { label: 'Summary', render: (h) => `<strong>${esc(h.summary)}</strong><div class="small muted">${esc(h.open_issues || '')}</div>` },
        { label: 'Recorded', render: (h) => fmt(h.handed_over_at) },
        { label: 'Acknowledged', render: (h) => h.acknowledged_at ? fmt(h.acknowledged_at) : badge('Pending', 'warn') },
      ],
      actions: (h) => Number(h.to_user_id) === Number(window.IHMS.boot.user.id) && !h.acknowledged_at ? [{ label: 'Acknowledge', onClick: async () => {
        try { await api('POST', `/security/handovers/${h.id}/acknowledge`); toast('Handover acknowledged', 'ok'); table.reload(); } catch (e) { toast(e.message, 'bad'); }
      } }] : [],
    });
  }

  pages.patrols = async () => {
    root.innerHTML = '<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))"><div id="checkpoints"></div><div id="schedules"></div><div id="records"></div></div>';
    const checkpointsEl = document.getElementById('checkpoints');
    let checkpoints, schedules, shifts, recordTable, checkpointTable, scheduleTable;
    try {
      [checkpoints, schedules, shifts] = await Promise.all([
        api('GET', '/security/checkpoints').then((r) => r.data),
        api('GET', '/security/patrol-schedules').then((r) => r.data),
        api('GET', '/security/shifts', { per_page: 100 }).then((r) => r.data),
      ]);
    } catch (e) { return showError(e); }
    checkpointTable = DataTable(checkpointsEl, {
      endpoint: '/security/checkpoints', empty: 'No checkpoints configured.',
      toolbar: [{ label: '+ Checkpoint', cls: 'pri', onClick: () => formModal({ title: 'Add patrol checkpoint', fields: [{ name: 'code', label: 'Checkpoint code', required: true }, { name: 'name', label: 'Name', required: true }, { name: 'location_description', label: 'Location description' }, { name: 'sequence_no', label: 'Route sequence', type: 'number' }], values: { sequence_no: 0 }, submitLabel: 'Add checkpoint', onSubmit: async (v, m) => { await api('POST', '/security/checkpoints', v); m.close(); toast('Checkpoint added', 'ok'); checkpointTable.reload(); } }) }],
      columns: [{ label: 'Checkpoint', render: (c) => `<strong>${esc(c.code)}</strong><div class="small muted">${esc(c.name)}</div>` }, { label: 'Last checked', render: (c) => fmt(c.last_checked_at) }],
    });
    scheduleTable = DataTable(document.getElementById('schedules'), {
      endpoint: '/security/patrol-schedules', empty: 'No patrol schedules configured.',
      toolbar: [{ label: '+ Schedule', cls: 'pri', onClick: () => formModal({ title: 'Add patrol schedule', fields: [{ name: 'name', label: 'Schedule name', required: true }, { name: 'route_description', label: 'Route description' }, { name: 'start_time', label: 'Start time', type: 'time', required: true }, { name: 'interval_minutes', label: 'Repeat interval (minutes)', type: 'number' }, { name: 'days_of_week', label: 'Days (1=Mon to 7=Sun)', help: 'Comma-separated, e.g. 1,2,3,4,5,6,7' }], values: { start_time: '08:00', days_of_week: '1,2,3,4,5,6,7' }, submitLabel: 'Add schedule', onSubmit: async (v, m) => { await api('POST', '/security/patrol-schedules', v); m.close(); toast('Patrol schedule added', 'ok'); scheduleTable.reload(); } }) }],
      columns: [{ label: 'Schedule', render: (s) => `<strong>${esc(s.name)}</strong><div class="small muted">${esc(s.route_description || '')}</div>` }, { label: 'Start / interval', render: (s) => `${esc(s.start_time)} / ${esc(s.interval_minutes || '—')} min` }],
    });
    recordTable = DataTable(document.getElementById('records'), {
      endpoint: '/security/patrol-records', empty: 'No patrol checkpoints recorded.',
      toolbar: [{ label: '+ Record checkpoint', cls: 'pri', onClick: () => formModal({
        title: 'Record patrol checkpoint',
        fields: [
          { name: 'checkpoint_id', label: 'Checkpoint', type: 'select', required: true, placeholder: 'Select checkpoint', options: checkpoints.map((c) => ({ value: c.id, label: `${c.code} · ${c.name}` })) },
          { name: 'patrol_schedule_id', label: 'Schedule (optional)', type: 'select', placeholder: 'Not linked', options: schedules.map((s) => ({ value: s.id, label: s.name })) },
          { name: 'guard_shift_id', label: 'Your shift (optional)', type: 'select', placeholder: 'Not linked', options: shifts.filter((s) => Number(s.guard_user_id) === Number(window.IHMS.boot.user.id)).map((s) => ({ value: s.id, label: `${s.shift_date} · ${s.shift_type}` })) },
          { name: 'status', label: 'Result', type: 'select', required: true, options: options(['ok', 'issue_found', 'missed']) },
          { name: 'notes', label: 'Notes' },
        ],
        values: { status: 'ok' }, submitLabel: 'Record patrol',
        onSubmit: async (v, m) => { if (!v.patrol_schedule_id) delete v.patrol_schedule_id; if (!v.guard_shift_id) delete v.guard_shift_id; await api('POST', '/security/patrol-records', v); m.close(); toast('Patrol checkpoint recorded', 'ok'); recordTable.reload(); },
      }) }],
      columns: [{ label: 'Checkpoint', render: (p) => `<strong>${esc(p.checkpoint_code)}</strong><div class="small muted">${esc(p.checkpoint_name)}</div>` }, { label: 'Guard', key: 'guard_username' }, { label: 'Result', render: (p) => badge(p.status.replaceAll('_', ' '), p.status === 'ok' ? 'ok' : 'warn') }, { label: 'Checked', render: (p) => fmt(p.checked_at) }],
    });
  };

  pages.evidence = () => {
    root.innerHTML = `<section class="card camera-setup-card">
      <div class="camera-setup-heading"><div><h2>Camera setup</h2><p class="small muted">Find cameras available to this browser, preview one, then save its name and location.</p></div>
        <button class="btn" type="button" id="cameraScan">Find cameras</button>
      </div>
      <div class="camera-setup-grid">
        <div class="camera-preview-column">
          <label class="camera-field">Available device
            <select class="in" id="cameraDevice" disabled><option value="">Select Find cameras first</option></select>
          </label>
          <video class="camera-preview" id="cameraPreview" autoplay muted playsinline hidden></video>
          <div class="camera-preview-actions"><button class="btn" type="button" id="cameraPreviewStart" disabled>Start preview</button><button class="btn" type="button" id="cameraPreviewStop" disabled>Stop preview</button></div>
        </div>
        <div>
          <label class="camera-field">Camera name<input class="in" id="cameraName" maxlength="100" placeholder="e.g. Main entrance"></label>
          <label class="camera-field">Location<input class="in" id="cameraLocation" maxlength="150" placeholder="e.g. Lobby"></label>
          ${can('security.camera.manage') ? '<button class="btn pri" type="button" id="cameraSave" disabled>Save camera setup</button>' : '<p class="small muted">You can preview available cameras; camera setup requires camera-management permission.</p>'}
        </div>
      </div>
      <p class="small muted" id="cameraMessage" role="status">Camera access is requested only when you choose Find cameras or Start preview. This page does not record video.</p>
      <section class="camera-live-viewer">
        <div class="camera-live-heading"><div><h3>Camera wall</h3><p class="small muted">Saved cameras appear together in a compact live grid. Scan to connect available feeds.</p></div>
          <div class="camera-preview-actions"><button class="btn" type="button" id="cameraStopAll" disabled>Stop all feeds</button></div>
        </div>
        <div class="camera-live-grid" id="cameraLiveGrid"><p class="small muted">Loading saved cameras…</p></div>
      </section>
    </section><div id="cctv-reference-list"></div>`;

    const deviceSelect = document.getElementById('cameraDevice');
    const preview = document.getElementById('cameraPreview');
    const message = document.getElementById('cameraMessage');
    const nameInput = document.getElementById('cameraName');
    const locationInput = document.getElementById('cameraLocation');
    const previewStart = document.getElementById('cameraPreviewStart');
    const previewStop = document.getElementById('cameraPreviewStop');
    const saveButton = document.getElementById('cameraSave');
    let devices = [];
    let registered = [];
    let stream = null;
    let previewUsesCameraStream = false;
    let selectedCameraId = null;
    const cameraStreams = new Map();
    const cameraViewPending = new Set();
    const cameraViewErrors = new Map();
    let cameraViewGeneration = 0;
    let hasScannedCameras = false;
    const deviceKeys = new Map();

    const setCameraMessage = (text, error = false) => {
      message.textContent = text;
      message.classList.toggle('camera-error', error);
    };
    const stopPreview = () => {
      if (stream && !previewUsesCameraStream) {
        stream.getTracks().forEach((track) => track.stop());
      }
      stream = null;
      previewUsesCameraStream = false;
      preview.srcObject = null;
      preview.hidden = true;
      previewStop.disabled = true;
      previewStart.disabled = !deviceSelect.value;
    };
    const selectedDevice = () => devices.find((device) => device.deviceId === deviceSelect.value);
    const deviceKey = async (deviceId) => {
      if (!window.crypto || !window.crypto.subtle) throw new Error('Secure browser camera identification is unavailable. Open IHMS on localhost or HTTPS.');
      const digest = await window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(deviceId));
      return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
    };
    const availableRegisteredCameras = () => registered.filter((camera) => (
      camera.browser_device_key && devices.some((device) => deviceKeys.get(device.deviceId) === camera.browser_device_key)
    ));
    const renderCameraViewer = () => {
      const grid = document.getElementById('cameraLiveGrid');
      const stopAllButton = document.getElementById('cameraStopAll');
      stopAllButton.disabled = cameraStreams.size === 0 && cameraViewPending.size === 0;

      if (!registered.length) {
        grid.innerHTML = '<p class="small muted">No cameras are registered yet.</p>';
        return;
      }

      const availableIds = new Set(availableRegisteredCameras().map((camera) => camera.id));
      grid.innerHTML = registered.map((camera) => {
        const id = String(camera.id);
        const deviceAvailable = availableIds.has(camera.id);
        const isLive = cameraStreams.has(id);
        const error = cameraViewErrors.get(id);
        const status = cameraViewPending.has(id)
          ? 'Connecting'
          : isLive
            ? 'Live'
            : error
              ? 'Unavailable'
              : !camera.browser_device_key
                ? 'External'
                : !hasScannedCameras
                  ? 'Scan required'
                  : deviceAvailable
                    ? 'Ready'
                    : 'Offline';
        const screen = isLive
          ? `<video class="camera-live-video" data-camera-view="${esc(id)}" autoplay muted playsinline></video>`
          : `<div class="camera-no-signal"><span>${cameraViewPending.has(id) ? 'CONNECTING' : error ? esc(error) : status === 'External' ? 'EXTERNAL CAMERA' : 'NO SIGNAL'}</span></div>`;
        const setupButton = `<button class="camera-tile-action" type="button" data-camera-setup="${esc(id)}"${deviceAvailable ? '' : ' disabled'}>${deviceAvailable ? 'View / setup' : 'Unavailable'}</button>`;
        const removeButton = can('security.camera.manage')
          ? `<button class="camera-tile-action danger" type="button" data-camera-remove="${esc(id)}">Remove</button>`
          : '';
        return `<article class="camera-live-tile"><button class="camera-live-screen" type="button" data-camera-full-view="${esc(id)}"${deviceAvailable ? '' : ' disabled'} aria-label="${esc(`Open full view for ${camera.name}`)}">${screen}<span class="camera-live-status ${isLive ? 'is-live' : ''}"><i></i>${esc(status)}</span><span class="camera-live-name">${esc(camera.name)}</span></button><div class="camera-live-meta"><span>${esc(camera.location || 'Location not set')}</span></div><div class="camera-tile-actions">${setupButton}${removeButton}</div></article>`;
      }).join('');

      grid.querySelectorAll('video[data-camera-view]').forEach((video) => {
        video.srcObject = cameraStreams.get(video.dataset.cameraView) || null;
      });
      grid.querySelectorAll('[data-camera-full-view]').forEach((button) => {
        button.addEventListener('click', async () => {
          const camera = registered.find((item) => String(item.id) === button.dataset.cameraFullView);
          if (!camera) return;
          let cameraStream = cameraStreams.get(String(camera.id));
          if (!cameraStream) {
            const device = devices.find((item) => deviceKeys.get(item.deviceId) === camera.browser_device_key);
            if (!device) {
              setCameraMessage('This camera is not available to the browser. Reconnect it and scan again.', true);
              return;
            }
            try {
              stopPreview();
              cameraViewPending.add(String(camera.id));
              renderCameraViewer();
              cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { deviceId: { exact: device.deviceId } },
                audio: false,
              });
              cameraStreams.set(String(camera.id), cameraStream);
              cameraViewPending.delete(String(camera.id));
              renderCameraViewer();
            } catch (error) {
              cameraViewPending.delete(String(camera.id));
              cameraViewErrors.set(String(camera.id), error.message);
              renderCameraViewer();
              setCameraMessage(`Could not open ${camera.name}: ${error.message}`, true);
              return;
            }
          }
          openCameraFullView(camera, cameraStream);
        });
      });
      grid.querySelectorAll('[data-camera-setup]').forEach((button) => {
        button.addEventListener('click', () => {
          const camera = registered.find((item) => String(item.id) === button.dataset.cameraSetup);
          if (!camera) return;
          const device = devices.find((item) => deviceKeys.get(item.deviceId) === camera.browser_device_key);
          if (!device) {
            setCameraMessage('This camera is not available to the browser. Reconnect it and scan again.', true);
            return;
          }

          deviceSelect.value = device.deviceId;
          deviceSelect.dispatchEvent(new Event('change'));
          nameInput.value = camera.name;
          locationInput.value = camera.location || '';
          selectedCameraId = String(camera.id);

          const existingStream = cameraStreams.get(String(camera.id));
          if (existingStream) {
            stream = existingStream;
            previewUsesCameraStream = true;
            preview.srcObject = existingStream;
            preview.hidden = false;
            previewStart.disabled = true;
            previewStop.disabled = false;
            setCameraMessage(`${camera.name} is selected. Update its name or location, then save the camera setup.`);
          } else {
            previewStart.click();
          }
          document.querySelector('.camera-setup-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });
      grid.querySelectorAll('[data-camera-remove]').forEach((button) => {
        button.addEventListener('click', async () => {
          const camera = registered.find((item) => String(item.id) === button.dataset.cameraRemove);
          if (!camera) return;
          if (!await confirmDialog(
            `Remove "${camera.name}" from the camera wall? Existing CCTV references will be kept.`,
            { confirmLabel: 'Remove camera', danger: true }
          )) return;
          try {
            await api('DELETE', `/security/cameras/${camera.id}`);
            cameraViewGeneration += 1;
            cameraViewPending.clear();
            if (selectedCameraId === String(camera.id)) {
              stopPreview();
              selectedCameraId = null;
              nameInput.value = '';
              locationInput.value = '';
            }
            const cameraStream = cameraStreams.get(String(camera.id));
            if (cameraStream) cameraStream.getTracks().forEach((track) => track.stop());
            cameraStreams.delete(String(camera.id));
            registered = registered.filter((item) => String(item.id) !== String(camera.id));
            renderCameraViewer();
            toast('Camera removed', 'ok');
          } catch (error) {
            renderCameraViewer();
            setCameraMessage(error.message, true);
          }
        });
      });
    };
    const openCameraFullView = (camera, cameraStream) => {
      const fullView = modal({
        title: camera.name,
        size: 'camera-fullscreen-modal',
        body: `<div class="camera-full-view"><video autoplay muted playsinline></video><p>${esc(camera.location || 'Location not set')} · Live preview only; not recorded.</p></div>`,
        buttons: [
          {
            label: 'Full screen',
            cls: 'pri',
            onClick: async (dialog) => {
              const target = dialog.el.querySelector('.modal');
              if (!document.fullscreenElement && target.requestFullscreen) {
                try {
                  await target.requestFullscreen();
                } catch (error) {
                  setCameraMessage(`Could not enter full screen: ${error.message}`, true);
                }
              }
            },
          },
          { label: 'Close' },
        ],
      });
      fullView.body.querySelector('video').srcObject = cameraStream;
      fullView.el.addEventListener('click', async (event) => {
        if (event.target.closest('[data-x]') || event.target === fullView.el) {
          if (document.fullscreenElement) await document.exitFullscreen();
        }
      }, { once: true });
    };
    const stopCameraViews = () => {
      cameraViewGeneration += 1;
      cameraStreams.forEach((cameraStream) => cameraStream.getTracks().forEach((track) => track.stop()));
      cameraStreams.clear();
      cameraViewPending.clear();
      cameraViewErrors.clear();
      renderCameraViewer();
    };
    const startCameraViews = async () => {
      const available = availableRegisteredCameras();
      if (!available.length) {
        renderCameraViewer();
        return 0;
      }

      const generation = cameraViewGeneration;
      available.forEach((camera) => cameraViewPending.add(String(camera.id)));
      renderCameraViewer();
      await Promise.all(available.map(async (camera) => {
        const id = String(camera.id);
        const device = devices.find((candidate) => deviceKeys.get(candidate.deviceId) === camera.browser_device_key);
        try {
          if (!device) throw new Error('Camera is no longer available. Scan again.');
          const cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { deviceId: { exact: device.deviceId } },
            audio: false,
          });
          if (generation !== cameraViewGeneration) {
            cameraStream.getTracks().forEach((track) => track.stop());
            return;
          }
          cameraStreams.set(id, cameraStream);
        } catch (error) {
          if (generation === cameraViewGeneration) cameraViewErrors.set(id, error.message);
        } finally {
          if (generation === cameraViewGeneration) {
            cameraViewPending.delete(id);
            renderCameraViewer();
          }
        }
      }));
      return cameraStreams.size;
    };

    api('GET', '/security/cameras').then((response) => {
      registered = response.data;
      if (hasScannedCameras) {
        stopCameraViews();
        startCameraViews().then((active) => {
          if (active) setCameraMessage(`${active} saved camera feed${active === 1 ? '' : 's'} live. Feeds stay local and are not recorded.`);
        });
      } else {
        renderCameraViewer();
      }
    }).catch((error) => {
      document.getElementById('cameraLiveGrid').textContent = error.message;
    });

    document.getElementById('cameraScan').addEventListener('click', async () => {
      stopPreview();
      stopCameraViews();
      const media = navigator.mediaDevices;
      if (!media || !media.getUserMedia || !media.enumerateDevices) {
        setCameraMessage('This browser does not support camera discovery. Use a current browser over localhost or HTTPS.', true);
        return;
      }
      try {
        const permissionStream = await media.getUserMedia({ video: true, audio: false });
        permissionStream.getTracks().forEach((track) => track.stop());
        devices = (await media.enumerateDevices()).filter((device) => device.kind === 'videoinput' && device.deviceId);
        deviceKeys.clear();
        for (const device of devices) deviceKeys.set(device.deviceId, await deviceKey(device.deviceId));

        deviceSelect.replaceChildren(new Option(devices.length ? 'Select a camera' : 'No cameras found', ''));
        devices.forEach((device, index) => {
          const label = device.label || `Camera ${index + 1}`;
          deviceSelect.add(new Option(label, device.deviceId));
        });
        deviceSelect.disabled = devices.length === 0;
        previewStart.disabled = true;
        if (saveButton) saveButton.disabled = true;
        hasScannedCameras = true;
        renderCameraViewer();
        if (devices.length) {
          const active = await startCameraViews();
          setCameraMessage(active
            ? `${active} saved camera feed${active === 1 ? '' : 's'} live. Feeds stay local and are not recorded.`
            : `${devices.length} camera device${devices.length === 1 ? '' : 's'} available. No saved camera feeds are available to this browser.`);
        } else {
          setCameraMessage('No camera devices are available to this browser.');
        }
      } catch (error) {
        setCameraMessage(error.name === 'NotAllowedError'
          ? 'Camera permission was denied. Allow camera access in your browser settings, then try again.'
          : `Could not discover cameras: ${error.message}`, true);
      }
    });

    deviceSelect.addEventListener('change', () => {
      stopPreview();
      const device = selectedDevice();
      previewStart.disabled = !device;
      if (saveButton) saveButton.disabled = !device;
      const camera = registered.find((item) => item.browser_device_key === (device ? deviceKeys.get(device.deviceId) : ''));
      nameInput.value = camera?.name || device?.label || '';
      locationInput.value = camera?.location || '';
      selectedCameraId = camera ? String(camera.id) : null;
      setCameraMessage(device ? 'Camera selected. Start a preview to check its view.' : 'Select a camera to continue.');
    });

    previewStart.addEventListener('click', async () => {
      const device = selectedDevice();
      if (!device) return;
      stopPreview();
      stopCameraViews();
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: { deviceId: { exact: device.deviceId } },
          audio: false,
        });
        preview.srcObject = stream;
        preview.hidden = false;
        previewStop.disabled = false;
        setCameraMessage('Live preview is active locally. Video is not recorded or sent to the server.');
      } catch (error) {
        setCameraMessage(`Could not start camera preview: ${error.message}`, true);
      }
    });

    previewStop.addEventListener('click', () => {
      stopPreview();
      setCameraMessage('Camera preview stopped.');
    });

    document.getElementById('cameraStopAll').addEventListener('click', () => {
      stopPreview();
      stopCameraViews();
      setCameraMessage('All camera feeds have been stopped.');
    });

    if (saveButton) {
      saveButton.addEventListener('click', async () => {
        const device = selectedDevice();
        const name = nameInput.value.trim();
        if (!device || !name) {
          setCameraMessage('Select a camera and enter a camera name before saving.', true);
          return;
        }
        try {
          const result = await api('POST', '/security/cameras', {
            name,
            location: locationInput.value.trim(),
            browser_device_key: deviceKeys.get(device.deviceId),
          });
          registered = [
            ...registered.filter((camera) => camera.id !== result.data.id),
            result.data,
          ].sort((a, b) => a.name.localeCompare(b.name));
          selectedCameraId = String(result.data.id);
          renderCameraViewer();
          if (hasScannedCameras) {
            stopPreview();
            stopCameraViews();
            const active = await startCameraViews();
            setCameraMessage(active
              ? `Camera setup saved. ${active} saved camera feed${active === 1 ? '' : 's'} live locally.`
              : 'Camera setup saved. Scan again to connect the camera feed.');
          } else {
            setCameraMessage('Camera setup saved. Scan cameras to add its live feed to the camera wall.');
          }
          toast('Camera setup saved', 'ok');
        } catch (error) {
          setCameraMessage(error.message, true);
        }
      });
    }

    window.addEventListener('pagehide', () => {
      stopPreview();
      stopCameraViews();
    }, { once: true });

    let table;
    table = DataTable(document.getElementById('cctv-reference-list'), {
      endpoint: '/security/cctv-references', empty: 'No CCTV references recorded.',
      toolbar: [{ label: '+ Add reference', cls: 'pri', onClick: () => formModal({
        title: 'Record restricted CCTV reference',
        fields: [
          { name: 'incident_id', label: 'Incident ID (optional)', type: 'number' },
          { name: 'camera_code', label: 'Camera code', required: true },
          { name: 'camera_location', label: 'Camera location' },
          { name: 'footage_from', label: 'Footage from', type: 'datetime-local', required: true },
          { name: 'footage_to', label: 'Footage to', type: 'datetime-local', required: true },
          { name: 'storage_ref', label: 'Private storage reference', help: 'Internal key only; do not enter a public URL.' },
          { name: 'checksum_sha256', label: 'SHA-256 checksum' },
          { name: 'retention_until', label: 'Retention date', type: 'date' },
        ],
        values: { footage_from: localDateTime(), footage_to: localDateTime() },
        submitLabel: 'Save reference',
        onSubmit: async (v, m) => { if (!v.incident_id) delete v.incident_id; if (!v.retention_until) delete v.retention_until; await api('POST', '/security/cctv-references', v); m.close(); toast('CCTV reference recorded', 'ok'); table.reload(); },
      }) }],
      columns: [
        { label: 'Incident', render: (c) => esc(c.incident_no || (c.incident_id ? `#${c.incident_id}` : 'Unlinked')) },
        { label: 'Camera', render: (c) => `<strong>${esc(c.camera_code)}</strong><div class="small muted">${esc(c.camera_location || '')}</div>` },
        { label: 'Footage window', render: (c) => `${fmt(c.footage_from)} – ${fmt(c.footage_to)}` },
        { label: 'Retention', render: (c) => esc(c.retention_until || '—') },
        { label: 'Restricted', render: (c) => badge(Number(c.is_restricted) ? 'Yes' : 'No', Number(c.is_restricted) ? 'warn' : 'mut') },
      ],
    });
  };

  pages.inspections = () => {
    let table;
    table = DataTable(root, {
      endpoint: '/security/inspections', empty: 'No safety inspections recorded.',
      toolbar: [{ label: '+ Record inspection', cls: 'pri', onClick: () => formModal({
        title: 'Record safety inspection',
        fields: [
          { name: 'inspection_type', label: 'Inspection type', type: 'select', required: true, options: options(['fire', 'emergency_exit', 'extinguisher', 'alarm', 'drill', 'other']) },
          { name: 'area', label: 'Area or equipment', required: true },
          { name: 'result', label: 'Result', type: 'select', required: true, options: options(['pass', 'fail', 'action_required']) },
          { name: 'notes', label: 'Notes', type: 'textarea' },
          { name: 'next_due_on', label: 'Next due date', type: 'date' },
        ],
        submitLabel: 'Save inspection',
        onSubmit: async (v, m) => { if (!v.next_due_on) delete v.next_due_on; await api('POST', '/security/inspections', v); m.close(); toast('Safety inspection recorded', 'ok'); table.reload(); },
      }) }],
      columns: [
        { label: 'Type / area', render: (i) => `<strong>${esc(i.inspection_type.replaceAll('_', ' '))}</strong><div class="small muted">${esc(i.area)}</div>` },
        { label: 'Result', render: (i) => badge(i.result.replaceAll('_', ' '), i.result === 'pass' ? 'ok' : 'warn') },
        { label: 'Inspector', key: 'inspector' }, { label: 'Inspected', render: (i) => fmt(i.inspected_at) },
        { label: 'Next due', render: (i) => esc(i.next_due_on || '—') }, { label: 'Notes', key: 'notes' },
      ],
    });
  };

  (pages[page] || showError)(new Error('Security page not found.'));
})();
