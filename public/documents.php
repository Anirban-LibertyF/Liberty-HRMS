<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];

$company = q_all('SELECT * FROM documents WHERE user_id IS NULL ORDER BY uploaded_at DESC');
$offerLetters = q_all('SELECT * FROM documents WHERE user_id=? AND category="Offer Letter" ORDER BY uploaded_at DESC', [$uid]);
$mine = q_all('SELECT * FROM documents WHERE user_id=? AND category<>"Offer Letter" ORDER BY uploaded_at DESC', [$uid]);

render_header('Documents', 'documents', $user);
page_title('Documents', 'Company policies and your personal records in one place.');
?>
<div class="grid g-2" style="align-items:start">
  <div class="stack">
    <section class="card">
      <div class="card-head"><h2><?= icon('building', 20) ?> Company Documents</h2></div>
      <a class="feed-item" href="policy.php" style="color:inherit;border-bottom:1px solid var(--line-2)"><span class="feed-ic"><?= icon('shield', 16) ?></span><div><div class="tt"><span>HR Leave &amp; Attendance Policy <?= date('Y') ?></span></div><p>Policy handbook (printable)</p></div></a>
      <?php foreach ($company as $d): ?>
        <div class="feed-item" style="border-bottom:1px solid var(--line-2);align-items:center"><span class="feed-ic blue"><?= icon('folder', 16) ?></span><div style="flex:1"><div class="tt"><a href="download.php?t=doc&id=<?= $d['id'] ?>"><?= e($d['title']) ?></a></div><p><?= e($d['category']) ?> · <?= e(fmt_date($d['uploaded_at'])) ?></p></div>
          <a class="btn btn-sm" href="download.php?t=doc&id=<?= $d['id'] ?>&dl=1"><?= icon('download', 15) ?> Download</a></div>
      <?php endforeach; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2><?= icon('user', 20) ?> Offer Letter</h2></div>
      <?php foreach ($offerLetters as $d): ?>
        <div class="feed-item" style="border-bottom:1px solid var(--line-2);align-items:center"><span class="feed-ic blue"><?= icon('folder', 16) ?></span>
          <div style="flex:1"><div class="tt"><a href="download.php?t=doc&id=<?= $d['id'] ?>"><?= e($d['title']) ?></a></div><p><?= e($d['file_name']) ?> · <?= e(fmt_date($d['uploaded_at'])) ?></p></div>
          <a class="btn btn-sm btn-brand" href="download.php?t=doc&id=<?= $d['id'] ?>&dl=1"><?= icon('download', 15) ?> Download</a></div>
      <?php endforeach; ?>
      <?php if (!$offerLetters): ?><p class="empty">No offer letter is available yet.</p><?php endif; ?>
    </section>
  </div>

  <section class="card">
    <div class="card-head"><h2><?= icon('user', 20) ?> My Documents</h2></div>
    <?php foreach ($mine as $d): ?>
      <div class="feed-item" style="border-bottom:1px solid var(--line-2);align-items:center"><span class="feed-ic blue"><?= icon('folder', 16) ?></span>
        <div style="flex:1"><div class="tt"><a href="download.php?t=doc&id=<?= $d['id'] ?>"><?= e($d['title']) ?></a></div><p><?= e($d['category']) ?> · <?= e($d['file_name']) ?> · <?= e(fmt_date($d['uploaded_at'])) ?></p></div>
        <a class="btn btn-sm" href="download.php?t=doc&id=<?= $d['id'] ?>&dl=1"><?= icon('download', 15) ?> Download</a>
        <?php if ((int)$d['uploaded_by'] === $uid): ?><form method="post" action="actions.php" data-confirm="Delete this document?"><?= csrf_field() ?><input type="hidden" name="do" value="doc_delete"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="icon-btn" aria-label="Delete"><?= icon('trash', 16) ?></button></form><?php endif; ?></div>
    <?php endforeach; ?>
    <?php if (!$mine): ?><p class="empty">No personal documents uploaded.</p><?php endif; ?>
    <form method="post" action="actions.php" enctype="multipart/form-data" style="margin-top:18px">
      <?= csrf_field() ?><input type="hidden" name="do" value="doc_upload">
      <div class="field-row field"><div><label class="f">Title</label><input class="input" name="title" required maxlength="200" placeholder="e.g. Address proof"></div>
        <div><label class="f">Category</label><select class="input" name="category"><option>Identity</option><option>Education</option><option>Employment</option><option>Medical</option><option>General</option></select></div></div>
      <div class="field"><input class="input" type="file" name="file" accept=".pdf,.png,.jpg,.jpeg" required><div class="help">PDF, PNG or JPG up to 10MB.</div></div>
      <button class="btn btn-brand"><?= icon('upload', 16) ?> Upload</button>
    </form>
  </section>
</div>
<?php render_footer(); ?>
