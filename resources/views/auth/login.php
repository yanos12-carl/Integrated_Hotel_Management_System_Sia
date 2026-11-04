<h1>Sign in</h1><p class="muted">Use your employee account to reach your department.</p>
<div id="msg" class="alert" hidden role="alert"></div>
<form data-auth="login" novalidate>
  <label class="f">Username or email<input class="in" name="username" autocomplete="username" required autofocus></label>
  <label class="f">Password<input class="in" type="password" name="password" autocomplete="current-password" required></label>
  <button class="btn pri" type="submit" style="width:100%;justify-content:center">Sign in</button>
</form>
<p class="small" style="text-align:center;margin-top:1rem"><a href="<?= e(url('/forgot-password')) ?>">Forgot your password?</a></p>
