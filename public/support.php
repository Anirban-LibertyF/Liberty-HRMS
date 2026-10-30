<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = current_user();

$S = support_settings();
$faqs = q_all('SELECT * FROM support_faqs ORDER BY sort_order, id');
$isAdm = $user && $user['role'] === 'admin';

$body = function () use ($S, $faqs, $isAdm) { ?>
  <div class="grid g-2" style="align-items:start">
    <section class="card"><div class="card-head"><h2><?= icon('help', 20) ?> IT Help Desk</h2></div>
      <div class="kv"><span>Email</span><span><?= e($S['help_email']) ?></span></div>
      <div class="kv"><span>Phone</span><span><?= e($S['help_phone']) ?></span></div>
      <div class="kv"><span>Hours</span><span><?= e($S['help_hours']) ?></span></div>
      <div class="kv"><span>HR Operations</span><span><?= e($S['hr_email']) ?></span></div></section>
    <section class="card" id="directors-corner"><div class="card-head"><h2><?= icon('user', 20) ?> Director's Corner</h2></div>
      <div class="director">
        <?php if ($S['dir_photo'] !== ''): ?><img src="support_photo.php?v=<?= substr(md5($S['dir_photo']), 0, 8) ?>" alt="<?= e($S['dir_name']) ?>" width="84" height="84"><?php elseif (is_file(__DIR__ . '/assets/img/director.jpg')): ?><img src="assets/img/director.jpg" alt="Director" width="84" height="84"><?php else: ?><span class="ph" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($S['dir_name'], 0, 1))) ?></span><?php endif; ?>
        <div><blockquote>“<?= e($S['dir_quote']) ?>”</blockquote>
          <cite>— <?= e($S['dir_name']) ?><small><?= e($S['dir_org']) ?></small></cite></div>
      </div></section>
  </div>
  <section class="card mt"><div class="card-head"><h2>Quick answers</h2></div>
    <?php foreach ($faqs as $i => $f): ?><?= $i ? '<br>' : '' ?><p><b><?= e($f['question']) ?></b><br><span class="muted"><?= nl2br(e($f['answer'])) ?></span></p><?php endforeach; ?>
    <?php if (!$faqs): ?><p class="empty">No answers added yet.</p><?php endif; ?></section>

  <?php if ($isAdm): ?>
  <section class="card mt" id="support-edit"><div class="card-head"><h2><?= icon('edit', 20) ?> Edit support page <span class="badge b-amber">Admin</span></h2></div>
    <form method="post" action="actions.php" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="do" value="admin_support_save"><input type="hidden" name="back" value="support.php">
      <h3 style="margin:0 0 10px">IT Help Desk</h3>
      <div class="field-row field"><div><label class="f" for="sEmail">Email</label><input class="input" id="sEmail" name="help_email" maxlength="200" value="<?= e($S['help_email']) ?>"></div>
        <div><label class="f" for="sPhone">Phone</label><input class="input" id="sPhone" name="help_phone" maxlength="60" value="<?= e($S['help_phone']) ?>"></div></div>
      <div class="field-row field"><div><label class="f" for="sHours">Hours</label><input class="input" id="sHours" name="help_hours" maxlength="120" value="<?= e($S['help_hours']) ?>"></div>
        <div><label class="f" for="sHr">HR Operations email</label><input class="input" id="sHr" name="hr_email" maxlength="200" value="<?= e($S['hr_email']) ?>"></div></div>
      <h3 style="margin:18px 0 10px">Director's Corner</h3>
      <div class="field"><label class="f" for="sQuote">Quote</label><textarea class="input" id="sQuote" name="dir_quote" rows="3" maxlength="600"><?= e($S['dir_quote']) ?></textarea></div>
      <div class="field-row field"><div><label class="f" for="sName">Name</label><input class="input" id="sName" name="dir_name" maxlength="100" value="<?= e($S['dir_name']) ?>"></div>
        <div><label class="f" for="sOrg">Title / organisation</label><input class="input" id="sOrg" name="dir_org" maxlength="100" value="<?= e($S['dir_org']) ?>"></div></div>
      <div class="field"><label class="f" for="sPhoto">Photo (JPG or PNG, up to 5MB)</label><input class="input" type="file" id="sPhoto" name="dir_photo" accept=".jpg,.jpeg,.png">
        <?php if ($S['dir_photo'] !== ''): ?><label class="inline-note" style="margin-top:8px"><input type="checkbox" name="remove_photo" value="1"> Remove current photo</label><?php endif; ?></div>
      <button class="btn btn-brand"><?= icon('save', 16) ?> Save changes</button>
    </form>
  </section>

  <section class="card mt" id="faq-edit"><div class="card-head"><h2><?= icon('edit', 20) ?> Edit quick answers <span class="badge b-amber">Admin</span></h2></div>
    <?php foreach ($faqs as $f): ?>
      <form method="post" action="actions.php" style="border-bottom:1px solid var(--line-2);padding-bottom:14px;margin-bottom:14px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $f['id'] ?>"><input type="hidden" name="back" value="support.php">
        <div class="field-row field"><div><label class="f">Question</label><input class="input" name="question" required maxlength="300" value="<?= e($f['question']) ?>"></div>
          <div style="max-width:110px"><label class="f">Order</label><input class="input" type="number" name="sort_order" value="<?= (int)$f['sort_order'] ?>"></div></div>
        <div class="field"><label class="f">Answer</label><textarea class="input" name="answer" required rows="3" maxlength="3000"><?= e($f['answer']) ?></textarea></div>
        <div class="btn-row"><button class="btn btn-brand btn-sm" name="do" value="admin_faq_save"><?= icon('save', 14) ?> Save</button>
          <button class="btn btn-danger btn-sm" name="do" value="admin_faq_delete" formnovalidate onclick="return confirm('Delete this answer?')"><?= icon('trash', 14) ?> Delete</button></div></form>
    <?php endforeach; ?>
    <form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="do" value="admin_faq_save"><input type="hidden" name="id" value="0"><input type="hidden" name="back" value="support.php">
      <h3 style="margin:0 0 10px">Add a new answer</h3>
      <div class="field"><label class="f">Question</label><input class="input" name="question" required maxlength="300" placeholder="e.g. How do I reset my password?"></div>
      <div class="field"><label class="f">Answer</label><textarea class="input" name="answer" required rows="3" maxlength="3000"></textarea></div>
      <button class="btn btn-brand"><?= icon('plus', 16) ?> Add answer</button>
    </form>
  </section>
  <?php endif; ?>
<?php };

if ($user) { render_header('Support', 'dashboard', $user); page_title('Support', 'Get help with Liberty Office.'); $body(); render_footer(); exit; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><script>try{if(localStorage.getItem('lo-mode')==='dark')document.documentElement.setAttribute('data-mode','dark')}catch(e){}</script><meta name="viewport" content="width=device-width, initial-scale=1"><title>Support · Liberty Office</title><link rel="stylesheet" href="assets/css/app.css"></head>
<body><div class="content"><p><a href="login.php">← Back to sign in</a></p><h1 style="margin:14px 0 20px">Support</h1><?php $body(); ?></div></body></html>
