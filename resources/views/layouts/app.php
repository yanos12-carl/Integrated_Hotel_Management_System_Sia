<?php
$menu = require BASE_PATH . '/config/menu.php';
$perms = $user['permissions'];
$initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
$activePath = $activePath ?? '';

$groups = [];
$pinned = [];
foreach ($menu as $name => $cfg) {
    if ($name === 'Security' && ($user['department']['code'] ?? null) !== 'SECURITY') { continue; }
    if (($user['department']['code'] ?? null) === 'SECURITY' && !in_array($name, ['General', 'Security'], true)) { continue; }
    $items = array_values(array_filter($cfg['items'] ?? [], fn($i) => empty($i['perm']) || in_array($i['perm'], $perms, true)));
    if (!$items) { continue; }
    $groups[] = [
        'name'  => $name,
        'icon'  => $cfg['icon'] ?? 'list',
        'short' => $cfg['short'] ?? $name,
        'note'  => $cfg['note'] ?? '',
        'items' => $items,
    ];
    foreach ($items as $it) {
        if (in_array($it['href'], (array) ($cfg['primary'] ?? []), true)) { $pinned[] = $it; }
    }
}
$menuJson = json_encode(
    ['groups' => $groups, 'pinned' => $pinned, 'active' => $activePath],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
);
$boot = json_encode(['user' => ['id' => $user['id'], 'name' => $user['first_name'] . ' ' . $user['last_name'], 'permissions' => $perms, 'department' => $user['department']['code']]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?= e($csrf) ?>"><meta name="app-base" content="<?= e(base_path()) ?>">
<title><?= e($title) ?> · <?= e($appName) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css') . '?v=' . filemtime(BASE_PATH . '/public/assets/css/app.css')) ?>">
</head>
<body data-page="<?= e($pageKey ?? '') ?>">
<div class="shell">
  <aside class="sidebar" aria-label="Main navigation">
    <div class="brand"><span class="logo">H</span><span class="t"><?= e($appName) ?></span></div>
    <nav class="nav" id="nav">
<?php if ($pinned): ?>
      <div class="rail-sec">Quick</div>
<?php foreach ($pinned as $it): ?>
      <a class="rail-btn<?= $activePath === $it['href'] ? ' active' : '' ?>" href="<?= e(url($it['href'])) ?>" title="<?= e($it['label']) ?>"><?= icon($it['icon']) ?><span class="t"><?= e($it['label']) ?></span></a>
<?php endforeach; ?>
      <div class="rail-sec">Sections</div>
<?php endif; ?>
<?php foreach ($groups as $gi => $g):
    $hasActive = false;
    foreach ($g['items'] as $it) { if ($activePath === $it['href']) { $hasActive = true; break; } }
?>
      <button class="rail-btn grp<?= $hasActive ? ' has-active' : '' ?>" type="button" data-group="<?= (int) $gi ?>" aria-expanded="false" aria-haspopup="true" title="<?= e($g['name']) ?>">
        <?= icon($g['icon']) ?><span class="t"><?= e($g['short']) ?></span>
      </button>
<?php endforeach; ?>
    </nav>
  </aside>
  <div class="flyout" id="flyout" hidden></div>
  <div class="main">
    <header class="topbar">
      <button class="iconbtn only-mobile" id="sbToggle" aria-label="Toggle navigation"><?= icon('menu') ?></button>
      <span class="dept"><?= e($user['department']['name']) ?></span>
      <span class="sp"></span>
      <div class="dd notification-dd">
        <button class="iconbtn" id="bellBtn" aria-label="Notifications"><?= icon('bell') ?><span class="count" id="notifCount" hidden>0</span></button>
        <div class="dd-panel notification-panel"><div class="notification-head"><strong>Notifications</strong><button class="btn sm" id="readAll">Mark all read</button></div><div class="notif-list" id="notifList"></div></div>
      </div>
      <div class="dd">
        <button class="btn account-btn" aria-label="Account menu">
          <?php if (!empty($user['avatar_url'])): ?>
            <img class="header-avatar" src="<?= e($user['avatar_url']) ?>" alt="<?= e($user['first_name']) ?> profile picture" />
          <?php else: ?>
            <span class="header-avatar initials"><?= e($initials) ?></span>
          <?php endif; ?>
          <span class="small"><?= e($user['first_name']) ?></span>
        </button>
        <div class="dd-panel" style="min-width:220px">
          <div class="item" style="cursor:default"><strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong><div class="small muted"><?= e($user['email']) ?></div></div>
          <a class="item" href="<?= e(url('/profile')) ?>">My profile</a>
          <a class="item" href="<?= e(url('/change-password')) ?>">Change password</a>
          <button class="item" id="logoutBtn">Sign out</button>
        </div>
      </div>
    </header>
    <main class="content">
      <div class="page-head">
        <div>
          <h1><?= e($title) ?></h1>
          <?php if (!empty($subtitle)): ?><p class="page-sub"><?= e($subtitle) ?></p><?php endif; ?>
        </div>
        <span class="sp"></span>
        <?= $head ?? '' ?>
      </div>
      <?= $content ?>
    </main>
  </div>
</div>
<script type="application/json" id="boot"><?= $boot ?></script>
<script type="application/json" id="menu"><?= $menuJson ?></script>
<script src="<?= e(url('assets/js/app.js') . '?v=' . filemtime(BASE_PATH . '/public/assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/charts.js') . '?v=' . filemtime(BASE_PATH . '/public/assets/js/charts.js')) ?>"></script>
<?php if (!empty($script)): ?><script src="<?= e(url('assets/js/' . $script) . '?v=' . filemtime(BASE_PATH . '/public/assets/js/' . $script)) ?>"></script><?php endif; ?>
</body></html>
