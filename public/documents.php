<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];

$company = q_all('SELECT * FROM documents WHERE user_id IS NULL ORDER BY uploaded_at DESC');
$offerLetters = q_all('SELECT * FROM documents WHERE user_id=? AND category="Offer Letter" ORDER BY uploaded_at DESC', [$uid]);
$joiningLetters = q_all('SELECT * FROM documents WHERE user_id=? AND category="Joining Letter" ORDER BY uploaded_at DESC', [$uid]);
$mine = q_all('SELECT * FROM documents WHERE user_id=? AND category NOT IN ("Offer Letter","Joining Letter","NDA Letter","NDA Signed") ORDER BY uploaded_at DESC', [$uid]);
$ndaLetter = q_one('SELECT * FROM documents WHERE user_id=? AND category="NDA Letter" ORDER BY id DESC LIMIT 1', [$uid]);
$ndaSigned = q_one('SELECT * FROM documents WHERE user_id=? AND category="NDA Signed" ORDER BY id DESC LIMIT 1', [$uid]);
$joinDaysLeft = joining_letter_days_left($user['joined_on']);
$joinReady = $joinDaysLeft === 0;

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

    <section class="card">
      <div class="card-head"><h2><?= icon('user', 20) ?> Joining Letter</h2></div>
      <?php foreach ($joiningLetters as $d): ?>
        <?php if ($joinReady): ?>
        <div class="feed-item" style="border-bottom:1px solid var(--line-2);align-items:center"><span class="feed-ic blue"><?= icon('folder', 16) ?></span>
          <div style="flex:1"><div class="tt"><a href="download.php?t=doc&id=<?= $d['id'] ?>"><?= e($d['title']) ?></a></div><p><?= e($d['file_name']) ?> · <?= e(fmt_date($d['uploaded_at'])) ?></p></div>
          <a class="btn btn-sm btn-brand" href="download.php?t=doc&id=<?= $d['id'] ?>&dl=1"><?= icon('download', 15) ?> Download</a></div>
        <?php else: ?>
        <div class="feed-item" style="border-bottom:1px solid var(--line-2);align-items:center;opacity:.6"><span class="feed-ic"><?= icon('lock', 16) ?></span>
          <div style="flex:1"><div class="tt"><span><?= e($d['title']) ?></span></div><p><?= e($d['file_name']) ?></p></div>
          <button class="btn btn-sm" disabled><?= icon('download', 15) ?> Download</button></div>
        <div class="alert" style="margin-top:10px"><?= icon('help', 18) ?><span>You have to wait <b><?= $joinDaysLeft ?></b> more day<?= $joinDaysLeft === 1 ? '' : 's' ?>. Your joining letter unlocks 90 days after your date of joining (<?= e(fmt_date($user['joined_on'])) ?>), on <?= e(date('M j, Y', strtotime($user['joined_on'] . ' +90 days'))) ?>.</span></div>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$joiningLetters): ?><p class="empty">No joining letter is available yet.</p><?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2><?= icon('shield', 20) ?> NDA Letter</h2><?php if ($ndaLetter): ?><span class="badge <?= $ndaSigned ? 'b-green' : 'b-amber' ?>"><?= $ndaSigned ? 'Signed copy uploaded' : 'Signature pending' ?></span><?php endif; ?></div>
      <?php if ($ndaLetter): ?>
        <div class="feed-item" style="border-bottom:1px solid var(--line-2);align-items:center"><span class="feed-ic blue"><?= icon('folder', 16) ?></span>
          <div style="flex:1"><div class="tt"><a href="download.php?t=doc&id=<?= $ndaLetter['id'] ?>" target="_blank" rel="noopener"><?= e($ndaLetter['title']) ?></a></div><p><?= e($ndaLetter['file_name']) ?> · <?= e(fmt_date($ndaLetter['uploaded_at'])) ?></p></div>
          <a class="btn btn-sm btn-brand" href="download.php?t=doc&id=<?= $ndaLetter['id'] ?>&dl=1"><?= icon('download', 15) ?> Download</a></div>
        <?php if ($ndaSigned): ?>
        <div class="feed-item" style="border-bottom:1px solid var(--line-2);align-items:center"><span class="feed-ic"><?= icon('check', 16) ?></span>
          <div style="flex:1"><div class="tt"><span>Your signed copy</span></div><p><?= e($ndaSigned['file_name']) ?> · <?= e(fmt_date($ndaSigned['uploaded_at'])) ?></p></div>
          <a class="icon-btn" href="download.php?t=doc&id=<?= $ndaSigned['id'] ?>" target="_blank" rel="noopener" aria-label="View"><?= icon('eye', 16) ?></a>
          <form method="post" action="actions.php" data-confirm="Remove your signed NDA?"><?= csrf_field() ?><input type="hidden" name="do" value="doc_delete"><input type="hidden" name="id" value="<?= $ndaSigned['id'] ?>"><button class="icon-btn" aria-label="Remove"><?= icon('trash', 16) ?></button></form></div>
        <?php endif; ?>
        <form method="post" action="actions.php" enctype="multipart/form-data" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="do" value="nda_upload">
          <label class="f"><?= $ndaSigned ? 'REPLACE SIGNED NDA' : 'UPLOAD SIGNED NDA' ?></label>
          <div class="drop" data-for="ndaFile"><?= icon('upload', 26) ?><b>Drag &amp; drop your signed NDA here, or click to browse</b><span class="muted" style="font-size:12.5px">PDF, PNG or JPG up to 10MB</span><div class="fname"></div></div>
          <input type="file" id="ndaFile" name="file" accept=".pdf,.png,.jpg,.jpeg" hidden>
          <div class="btn-row" style="margin-top:12px"><button class="btn btn-brand"><?= icon('upload', 16) ?> <?= $ndaSigned ? 'Replace' : 'Upload' ?></button></div></form>
      <?php else: ?><p class="empty">No NDA is available yet.</p><?php endif; ?>
    </section>
  </div>

  <section class="card">
    <div class="card-head"><h2><?= icon('user', 20) ?> My Documents</h2></div>
    <?php foreach ($mine as $d): ?>
      <div class="feed-item" style="border-bottom:1px solid var(--line-2);align-items:center"><span class="feed-ic blue"><?= icon('folder', 16) ?></span>
        <div style="flex:1"><div class="tt"><a href="download.php?t=doc&id=<?= $d['id'] ?>"><?= e($d['title']) ?></a></div><p><?= e($d['category']) ?> · <?= e($d['file_name']) ?> · <?= e(fmt_date($d['uploaded_at'])) ?></p></div>
        <a class="icon-btn" href="download.php?t=doc&id=<?= $d['id'] ?>" target="_blank" rel="noopener" aria-label="View"><?= icon('eye', 16) ?></a>
        <a class="btn btn-sm" href="download.php?t=doc&id=<?= $d['id'] ?>&dl=1"><?= icon('download', 15) ?> Download</a>
        <?php if ((int)$d['uploaded_by'] === $uid): ?><form method="post" action="actions.php" data-confirm="Delete this document?"><?= csrf_field() ?><input type="hidden" name="do" value="doc_delete"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="icon-btn" aria-label="Delete"><?= icon('trash', 16) ?></button></form><?php endif; ?></div>
    <?php endforeach; ?>
    <?php if (!$mine): ?><p class="empty">No personal documents uploaded.</p><?php endif; ?>
    <hr class="doc-divider">
    <h3 style="margin:0 0 12px;font-size:15px">Add new document</h3>
    <form method="post" action="actions.php" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="do" value="doc_upload">
      <div class="field-row field"><div><label class="f">Title</label><input class="input" name="title" required maxlength="200" placeholder="e.g. Address proof"></div>
        <div><label class="f">Category</label><select class="input" name="category"><option>Identity</option><option>Education</option><option>Employment</option><option>Medical</option><option>General</option></select></div></div>
      <div class="field"><label class="f">FILE</label>
        <div class="drop" data-for="docFile"><?= icon('upload', 26) ?><b>Drag &amp; drop file here, or click to browse</b><span class="muted" style="font-size:12.5px">PDF, PNG or JPG up to 10MB</span><div class="fname"></div></div>
        <input type="file" id="docFile" name="file" accept=".pdf,.png,.jpg,.jpeg" hidden required></div>
      <button class="btn btn-brand"><?= icon('upload', 16) ?> Upload</button>
    </form>
  </section>
</div>
<?php render_footer(); ?>
