<h1>Change password</h1>
<?php if (!empty($forced)): ?><div class="alert info">For security, please choose a new password before continuing.</div><?php endif; ?>
<div id="msg" class="alert" hidden role="alert"></div>
<form data-auth="change" novalidate>
  <label class="f">Current password<input class="in" type="password" name="current_password" autocomplete="current-password" required autofocus></label>
  <label class="f">New password<input class="in" type="password" name="new_password" autocomplete="new-password" required></label>
  <label class="f">Confirm new password<input class="in" type="password" name="confirm" autocomplete="new-password" required></label>
  <button class="btn pri" type="submit" style="width:100%;justify-content:center">Change password</button>
</form>
<p class="small" style="text-align:center;margin-top:1rem"><?php if (empty($forced)): ?><a href="<?= e(url('/home')) ?>">Cancel</a><?php else: ?><a href="#" id="forcedOut">Sign out</a><script>document.getElementById('forcedOut').onclick=async(e)=>{e.preventDefault();try{await IHMS.api('POST','/auth/logout')}catch(_){};location.href=IHMS.BASE+'/login'}</script><?php endif; ?></p>
