<?php
$homeLinks = [];
$homePermissions = $user['permissions'] ?? [];
$homeMenu = require BASE_PATH . '/config/menu.php';
foreach ($homeMenu as $section) {
    foreach ($section['items'] ?? [] as $item) {
        if (in_array($item['href'], ['/home', '/profile', '/change-password'], true)) { continue; }
        if (!empty($item['perm']) && !in_array($item['perm'], $homePermissions, true)) { continue; }
        $homeLinks[] = $item;
        if (count($homeLinks) === 4) { break 2; }
    }
}
$homeLinkTones = ['rose', 'khaki', 'sage', 'sky'];
?>
<section class="hero home-hero" aria-labelledby="homeWelcome">
  <div class="in">
    <div class="home-hero-copy">
      <span class="home-eyebrow">YOUR IHMS WORKSPACE</span>
      <h2 id="homeWelcome"><span id="homeGreeting">Welcome back</span>, <?= e($user['first_name']) ?>.</h2>
      <p><?= e($user['department']['name']) ?> · <?= $user['roles'] ? e(implode(', ', array_column($user['roles'], 'name'))) : 'No role assigned' ?></p>
      <time class="home-date" id="homeDate"></time>
      <?php if (!$user['roles']): ?><div class="home-role-notice">Your account has no assigned role yet. Ask your administrator to assign your department role.</div><?php endif; ?>
    </div>
    <div class="home-hero-art" aria-hidden="true"><span>H</span><small>One hotel.<br>One pulse.</small></div>
  </div>
</section>

<section class="home-quick" aria-labelledby="homeQuickTitle">
  <div class="home-section-heading">
    <div><span class="home-eyebrow">GET MOVING</span><h2 id="homeQuickTitle">Quick access</h2></div>
    <div class="home-quick-actions">
      <p>Request employee accounts for your department; Admin approval is required before they're created.</p>
      <button class="btn pri" type="button" id="requestStaffAccount">Request employee account</button>
    </div>
  </div>
  <div class="home-quick-grid">
    <?php if ($homeLinks): foreach ($homeLinks as $i => $item): ?>
      <a class="home-quick-link" href="<?= e(url($item['href'])) ?>">
        <span class="home-quick-icon chip-<?= e($homeLinkTones[$i % count($homeLinkTones)]) ?>"><?= icon($item['icon'] ?? 'list') ?></span>
        <span class="home-quick-label"><?= e($item['label']) ?></span>
        <span class="home-quick-arrow" aria-hidden="true">→</span>
      </a>
    <?php endforeach; else: ?>
      <a class="home-quick-link" href="<?= e(url('/profile')) ?>">
        <span class="home-quick-icon chip-rose"><?= icon('users') ?></span><span class="home-quick-label">My profile</span><span class="home-quick-arrow" aria-hidden="true">→</span>
      </a>
      <a class="home-quick-link" href="<?= e(url('/change-password')) ?>">
        <span class="home-quick-icon chip-khaki"><?= icon('key') ?></span><span class="home-quick-label">Change password</span><span class="home-quick-arrow" aria-hidden="true">→</span>
      </a>
    <?php endif; ?>
  </div>
</section>

<section class="card home-inbox" aria-labelledby="homeInboxTitle">
  <div class="home-inbox-head">
    <div>
      <span class="home-eyebrow">STAY IN THE LOOP</span>
      <h2 id="homeInboxTitle">Your action center</h2>
      <p class="muted">Unread notifications that may need your attention.</p>
    </div>
    <span class="badge b-info" id="homeUnreadCount" aria-live="polite">Loading</span>
  </div>
  <div id="myNotifs" aria-live="polite"><div class="state"><span class="spin"></span>Loading your unread notifications…</div></div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const { api, esc, fmt, badge, toast, formModal } = IHMS;
  const box = document.getElementById('myNotifs');
  const count = document.getElementById('homeUnreadCount');
  const kinds = { info: 'info', warning: 'warn', critical: 'bad' };
  const hour = new Date().getHours();
  document.getElementById('homeGreeting').textContent = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
  document.getElementById('homeDate').textContent = new Intl.DateTimeFormat('en-PH', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  }).format(new Date());

  document.getElementById('requestStaffAccount').onclick = () => formModal({
    title: 'Request an employee account',
    fields: [
      { name: 'username', label: 'Username', required: true, help: 'The account will only be created after an administrator approves this request.' },
      { name: 'first_name', label: 'First name', required: true },
      { name: 'last_name', label: 'Last name', required: true },
      { name: 'email', label: 'Email', type: 'email', required: true },
      { name: 'phone', label: 'Phone' },
    ],
    submitLabel: 'Send for approval',
    onSubmit: async (values, modal) => {
      await api('POST', '/account-requests', values);
      modal.close();
      toast('Request sent. The account will be created only after Admin approval.', 'ok');
    },
  });

  async function loadUnread() {
    box.innerHTML = '<div class="state"><span class="spin"></span>Loading your unread notifications…</div>';
    try {
      const r = await api('GET', '/notifications', { per_page: 10, unread: 1 });
      count.textContent = `${r.meta.total} unread`;
      box.innerHTML = r.data.length ? `<div class="home-notifications">${r.data.map((n) => `
        <article class="home-notification">
          <div class="home-notification-main">
            <div class="home-notification-title">${badge(n.severity, kinds[n.severity])}<strong>${esc(n.title)}</strong></div>
            <p>${esc(n.message)}</p>
            <time class="small muted">${fmt(n.created_at)}</time>
          </div>
          <button class="btn sm home-mark-read" type="button" data-id="${esc(n.id)}">Mark read</button>
        </article>`).join('')}</div>` : '<div class="home-empty"><strong>You’re all caught up.</strong><span>No unread notifications right now.</span></div>';

      box.querySelectorAll('.home-mark-read').forEach((button) => {
        button.onclick = async () => {
          button.disabled = true;
          try {
            await api('POST', `/notifications/${button.dataset.id}/read`);
            await loadUnread();
          } catch (e) {
            button.disabled = false;
            toast(e.message, 'bad');
          }
        };
      });
    } catch (e) {
      count.textContent = 'Unavailable';
      box.innerHTML = `<div class="alert bad">${esc(e.message)}</div>`;
    }
  }

  loadUnread();
});
</script>
