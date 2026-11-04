<h1>Reset password</h1><p class="muted">Enter your work email. If it matches an active account we will send a reset link.</p>
<div id="msg" class="alert" hidden role="alert"></div>
<form data-auth="forgot" novalidate>
  <label class="f">Email<input class="in" type="email" name="email" autocomplete="email" required autofocus></label>
  <button class="btn pri" type="submit" style="width:100%;justify-content:center">Send reset link</button>
</form>
<p class="small" style="text-align:center;margin-top:1rem"><a href="<?= e(url('/login')) ?>">Back to sign in</a></p>
