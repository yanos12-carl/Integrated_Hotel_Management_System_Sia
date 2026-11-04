<h1>Choose a new password</h1><p class="muted">At least 10 characters with letters and numbers.</p>
<div id="msg" class="alert" hidden role="alert"></div>
<form data-auth="reset" novalidate>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <label class="f">New password<input class="in" type="password" name="new_password" autocomplete="new-password" required autofocus></label>
  <label class="f">Confirm password<input class="in" type="password" name="confirm" autocomplete="new-password" required></label>
  <button class="btn pri" type="submit" style="width:100%;justify-content:center">Update password</button>
</form>
