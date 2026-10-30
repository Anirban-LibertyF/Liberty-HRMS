<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = current_user();

$body = function () { ?>
  <div class="grid g-2" style="align-items:start">
    <section class="card"><div class="card-head"><h2><?= icon('help', 20) ?> IT Help Desk</h2></div>
      <div class="kv"><span>Email</span><span>helpdesk@enterprise.corp</span></div>
      <div class="kv"><span>Phone</span><span>+1 (555) 010-4040</span></div>
      <div class="kv"><span>Hours</span><span>Mon–Fri · 08:00 – 20:00</span></div>
      <div class="kv"><span>HR Operations</span><span>hr-ops@enterprise.corp</span></div></section>
    <section class="card" id="directors-corner"><div class="card-head"><h2><?= icon('user', 20) ?> Director's Corner</h2></div>
      <div class="director">
        <?php if (is_file(__DIR__ . '/assets/img/director.jpg')): ?><img src="assets/img/director.jpg" alt="Director" width="84" height="84"><?php else: ?><span class="ph" aria-hidden="true">D</span><?php endif; ?>
        <div><blockquote>“Great work is built one honest day at a time. Show up, own your craft, and lift the people around you.”</blockquote>
          <cite>— Director<small>Liberty Foundation</small></cite></div>
      </div></section>
  </div>
  <section class="card mt"><div class="card-head"><h2>Quick answers</h2></div>
    <p><b>How is attendance calculated?</b><br><span class="muted">Hours are the time between your punch-in and punch-out minus recorded breaks.</span></p><br>
    <p><b>I forgot to punch out.</b><br><span class="muted">Open the Attendance Calendar, pick the day and choose “Request Regularization”.</span></p><br>
    <p><b>How do leave requests work?</b><br><span class="muted">Submit from Leave Application; your reporting manager or an administrator approves it. Leave other than your scheduled weekly off must be requested at least 3 days in advance; emergencies are considered individually. See the HR policy under Documents.</span></p></section>
<?php };

if ($user) { render_header('Support', 'dashboard', $user); page_title('Support', 'Get help with Liberty Office.'); $body(); render_footer(); exit; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><script>try{if(localStorage.getItem('lo-mode')==='dark')document.documentElement.setAttribute('data-mode','dark')}catch(e){}</script><meta name="viewport" content="width=device-width, initial-scale=1"><title>Support · Liberty Office</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body><div class="content"><p><a href="login.php">← Back to sign in</a></p><h1 style="margin:14px 0 20px">Support</h1><?php $body(); ?></div></body></html>
