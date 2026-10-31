<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';

if (current_user()) redirect('index.php');

$error = '';
$ident = '';
$asAdmin = !empty($_POST['as_admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ident = trim((string)($_POST['identifier'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    $u = $ident !== '' ? q_one('SELECT * FROM users WHERE emp_code = ? OR email = ?', [strtoupper($ident), strtolower($ident)]) : null;
    // The admin toggle must match the account type, so each side only accepts its own accounts.
    if ($u && (($u['role'] === 'admin') !== $asAdmin)) $u = null;
    if ($u && $u['locked_until'] && strtotime($u['locked_until']) > time()) {
        $error = 'Account temporarily locked after repeated failed attempts. Try again in a few minutes or contact IT.';
    } elseif ($u && $u['status'] === 'active' && password_verify($pass, $u['password_hash'])) {
        q('UPDATE users SET failed_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?', [$u['id']]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['remember'] = $remember;
        $_SESSION['expires'] = time() + ($remember ? cfg('session_hours_remember') * 3600 : cfg('session_idle_minutes') * 60);
        if ($remember) setcookie(session_name(), session_id(), ['expires' => $_SESSION['expires'], 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        redirect($u['role'] === 'admin' ? 'admin.php' : safe_next($_POST['next'] ?? $_GET['next'] ?? null));
    } else {
        if ($u) {
            $fails = (int)$u['failed_attempts'] + 1;
            q('UPDATE users SET failed_attempts=?, locked_until=? WHERE id=?', [$fails, $fails >= 5 ? date('Y-m-d H:i:s', time() + 300) : null, $u['id']]);
        }
        $error = ($u && $u['status'] !== 'active') ? 'This account is deactivated. Contact HR Operations.' : 'Invalid credentials. Check your Employee ID / email and password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<script>try{if(localStorage.getItem('lo-mode')==='dark')document.documentElement.setAttribute('data-mode','dark')}catch(e){}</script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · Liberty Office</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
</head>
<body class="login-body theme-<?= $asAdmin ? 'admin' : 'emp' ?>">
<header class="login-top">
  <div class="l"><img src="assets/img/logo.png" alt="Liberty Foundation - since 2008" class="login-logo-top"><b style="font-size:18px;font-weight:600">Liberty Office</b><small>Identity &amp; Access Management (IAM)</small></div>
  <div style="display:flex;gap:16px;align-items:center">
    <button type="button" class="icon-btn mode-btn" id="modeBtn" aria-label="Switch dark / light mode" title="Dark / light mode"><span class="i-moon"><?= icon('moon', 20) ?></span><span class="i-sun"><?= icon('sun', 20) ?></span></button>
    <span class="pill pill-status is-on mono"><i></i> SSO Cluster: Online</span>
    <a href="support.php" class="muted" style="display:flex;gap:6px;align-items:center"><?= icon('help', 16) ?> IT Help Desk</a>
  </div>
</header>

<div class="login-card">
  <div class="grad"></div>
  <div class="login-in">
    <div class="login-logo">
      <img src="assets/img/logo.png" alt="Liberty Foundation - since 2008" class="login-logo-card">
      <h1>Liberty Office</h1>
      <p class="muted" style="font-size:13px">Enterprise Workforce &amp; Attendance Suite</p>
    </div>

    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= icon('x', 20) ?><span><?= e($error) ?></span></div><?php endif; ?>

    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($_GET['next'] ?? '') ?>">
      <label class="theme-switch" for="asAdmin"><span><b>Login as Administrator</b><small>Switch on for the admin console</small></span>
        <input type="checkbox" class="switch" id="asAdmin" name="as_admin" value="1" <?= $asAdmin ? 'checked' : '' ?>></label>
      <div class="field">
        <label class="f" for="identifier" id="identLabel">Employee ID / Corporate Email</label>
        <div class="ico-input"><?= icon('user', 18) ?><input class="input" id="identifier" name="identifier" required autofocus placeholder="EMP-8042 or user@enterprise.corp" value="<?= e($ident) ?>" autocomplete="username"></div>
      </div>
      <div class="field">
        <div style="display:flex;justify-content:space-between"><label class="f" for="password">Password</label><a href="support.php#reset" style="font-size:12px;font-weight:500">Forgot Password?</a></div>
        <div class="pw-wrap ico-input"><?= icon('shield', 18) ?><input class="input" type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••••••">
          <button type="button" class="icon-btn eye" data-eye="#password" aria-label="Show password"><?= icon('eye', 18) ?></button></div>
      </div>
      <label class="chk"><input type="checkbox" name="remember" value="1"> Remember this workstation (12h session)</label>
      <button class="btn btn-dark btn-block btn-lg btn-signin">Sign In to Workspace <?= icon('arrow', 18) ?></button>
    </form>

  </div>
</div>
<p class="muted" style="text-align:center;font-size:13px;max-width:420px;margin:0 auto 30px">Need an account or authorization changes? <a href="support.php">Contact HR Operations</a> or your System Administrator.</p>
<footer class="login-footer">&copy; <?= date('Y') ?> Liberty Foundation &middot; Liberty Office &mdash; Enterprise Workforce &amp; Attendance Suite</footer>
<div class="toast-wrap" id="toasts"></div>
<script src="assets/js/app.js"></script>
</body>
</html>
