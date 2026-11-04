<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?= e($csrf) ?>"><meta name="app-base" content="<?= e(base_path()) ?>">
<title><?= e($title) ?> · <?= e($appName) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css') . '?v=' . filemtime(BASE_PATH . '/public/assets/css/app.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-shell">
  <aside class="auth-art" aria-hidden="true">
    <div class="aa-glow"></div>
    <div class="aa-brand"><span class="logo">H</span><span class="t"><?= e($appName) ?></span></div>
    <div class="aa-copy">
      <h2>One hotel.<br>One pulse.</h2>
      <p>Eight integrated departments, one central database and a single source of truth for every stay, charge and shift.</p>
      <ul class="aa-points">
        <li><b>Front Office</b><span>Reservations, stays, folios</span></li>
        <li><b>Finance</b><span>Invoices, payments, ledgers</span></li>
        <li><b>Housekeeping</b><span>Turnovers, inspections, linen</span></li>
        <li><b>Kitchen &amp; F&amp;B</b><span>Orders, preparation, stock</span></li>
      </ul>
    </div>
    <div class="aa-foot">Secured access · every action is audited</div>
  </aside>
  <main class="auth-form">
    <div class="card">
      <div class="brand"><span class="logo">H</span><span class="t"><?= e($appName) ?></span></div>
      <?= $content ?>
    </div>
  </main>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/auth.js')) ?>"></script>
</body></html>
