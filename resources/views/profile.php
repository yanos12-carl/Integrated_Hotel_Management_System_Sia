<?php
$fullName = $user['first_name'] . ' ' . $user['last_name'];
$roles = array_column($user['roles'], 'name');
$avatar = !empty($user['avatar_url']) ? e($user['avatar_url']) : '';
?>
<div class="card" style="max-width:860px">
  <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.2rem">
    <?php if ($avatar !== ''): ?>
      <img class="profile-avatar" src="<?= $avatar ?>" alt="<?= e($fullName) ?> profile picture" />
    <?php else: ?>
      <span class="profile-avatar"><?= e(strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1))) ?></span>
    <?php endif; ?>
    <div>
      <h2 style="margin:0"><?= e($fullName) ?></h2>
      <div class="muted"><?= e($user['department']['name']) ?></div>
    </div>
  </div>

  <div style="margin-bottom:1.2rem">
    <label class="f" for="avatarInput">Profile picture</label>
    <input id="avatarInput" class="in" type="file" accept="image/png,image/jpeg,image/gif,image/webp" style="padding:.4rem" />
    <div class="small muted" style="margin-top:.35rem">PNG, JPG, GIF, or WebP up to about 500 KB.</div>
    <div id="avatarStatus" class="alert" hidden></div>
  </div>

  <div class="grid cards">
    <div><div class="small muted">Username</div><strong><?= e($user['username']) ?></strong></div>
    <div><div class="small muted">Employee number</div><strong><?= e($user['employee_no']) ?></strong></div>
    <div><div class="small muted">Email</div><strong><?= e($user['email']) ?></strong></div>
    <div><div class="small muted">Phone</div><strong><?= $user['phone'] ? e($user['phone']) : '<span class="muted">Not provided</span>' ?></strong></div>
    <div><div class="small muted">Department</div><strong><?= e($user['department']['name']) ?></strong></div>
    <div><div class="small muted">Roles</div><strong><?= $roles ? e(implode(', ', $roles)) : '<span class="muted">None assigned</span>' ?></strong></div>
    <div><div class="small muted">Account status</div><strong><?= e(ucfirst($user['status'])) ?></strong></div>
    <div><div class="small muted">Member since</div><strong><?= e(substr((string) $user['created_at'], 0, 10)) ?></strong></div>
    <div><div class="small muted">Last sign-in</div><strong><?= $user['last_login_at'] ? e((string) $user['last_login_at']) : '<span class="muted">Not available</span>' ?></strong></div>
  </div>
  <div style="margin-top:1.4rem">
    <a class="btn pri" href="<?= e(url('/change-password')) ?>">Change password</a>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('avatarInput');
  const status = document.getElementById('avatarStatus');
  if (!input) return;
  input.addEventListener('change', async () => {
    const file = input.files && input.files[0];
    if (!file) return;
    status.hidden = true;
    status.className = 'alert';
    status.textContent = '';
    const reader = new FileReader();
    reader.onload = async () => {
      try {
        const resp = await IHMS.api('POST', '/auth/avatar', { image: String(reader.result) });
        status.className = 'alert ok';
        status.textContent = resp.message || 'Profile picture updated.';
        status.hidden = false;
        const img = document.querySelector('.profile-avatar');
        if (img && img.tagName === 'IMG') {
          img.src = resp.data.avatar_url + '?t=' + Date.now();
        }
      } catch (e) {
        status.className = 'alert bad';
        status.textContent = e.message || 'Unable to upload image.';
        status.hidden = false;
      }
    };
    reader.readAsDataURL(file);
  });
});
</script>
