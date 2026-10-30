<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];
$isAdm = $user['role'] === 'admin';

$sel = 'SELECT n.*, u.name AS owner, (SELECT COUNT(*) FROM note_files f WHERE f.note_id=n.id) AS n_files, (SELECT COUNT(*) FROM note_shares s WHERE s.note_id=n.id) AS n_recip FROM notes n JOIN users u ON u.id=n.user_id';
$order = ' ORDER BY n.note_date DESC, n.id DESC';
$mine = q_all("$sel WHERE n.user_id=?$order", [$uid]);                       // employee: personal notes · admin: notes shared out to employees
$others = $isAdm
    ? q_all("$sel WHERE n.admin_access=1 AND n.user_id<>?$order", [$uid])    // admin: employee notes shared with the administrator
    : q_all("$sel WHERE u.role='admin' AND (n.share_all=1 OR EXISTS (SELECT 1 FROM note_shares s WHERE s.note_id=n.id AND s.user_id=?))$order", [$uid]);   // employee: notes the administrator shared

$card = function (array $n, string $kind) use ($isAdm): string {
    ob_start(); ?>
    <a class="card proj-card note-card" href="note.php?id=<?= $n['id'] ?>">
      <div class="proj-top"><h3><?= e($n['title']) ?></h3><span class="tag"><?= e(fmt_date($n['note_date'])) ?></span></div>
      <p class="muted note-snip"><?= e(mb_strimwidth(note_plain((string)$n['body']), 0, 140, '…')) ?: 'No text' ?></p>
      <div class="proj-foot muted">
        <?php if ($kind === 'from-employee'): ?><?= icon('user', 14) ?> <?= e($n['owner']) ?><?php endif; ?>
        <?php if ($kind === 'from-admin'): ?><span class="badge b-slate"><?= icon('lock', 12) ?> Read only</span> <?= e($n['owner']) ?><?php endif; ?>
        <?php if ($kind === 'mine-employee' && $n['admin_access']): ?><span class="badge b-amber"><?= icon('shield', 12) ?> Admin access</span><?php endif; ?>
        <?php if ($kind === 'mine-admin'): ?><span class="badge b-green"><?= icon('user', 12) ?> <?= $n['share_all'] ? 'All employees' : (int)$n['n_recip'] . ' employee' . ((int)$n['n_recip'] === 1 ? '' : 's') ?></span><?php endif; ?>
        <?php if ($n['n_files']): ?><span style="margin-left:auto"><?= icon('clip', 14) ?> <?= (int)$n['n_files'] ?></span><?php endif; ?>
      </div>
    </a>
    <?php return (string)ob_get_clean();
};

render_header('Notes', 'notes', $user);
page_title($isAdm ? 'Notes' : 'Personal Notebook', $isAdm ? 'Share notes with all or selected employees, and see notes employees shared with you.' : 'Your private notes and files. Only you can see them unless you allow admin access.',
    '<a class="btn btn-brand" href="note.php">' . icon('plus', 16) . ' New note</a>');
?>
<?php if ($others && !$isAdm): ?>
<h2 class="sec-title"><?= icon('megaphone', 18) ?> Shared with me <span class="muted" style="font-weight:400;font-size:13px">· from the administrator, read only</span></h2>
<div class="proj-grid" style="margin-bottom:26px"><?php foreach ($others as $n) echo $card($n, 'from-admin'); ?></div>
<h2 class="sec-title"><?= icon('edit', 18) ?> My notes</h2>
<?php endif; ?>

<?php if ($isAdm): ?><h2 class="sec-title"><?= icon('send', 18) ?> Notes I shared</h2><?php endif; ?>
<?php if (!$mine): ?>
  <section class="card"><p class="empty"><?= $isAdm ? 'You have not shared any notes yet. Use “New note” to write one for all or selected employees.' : 'No notes yet. Use “New note” to start writing.' ?></p></section>
<?php else: ?>
<div class="proj-grid"><?php foreach ($mine as $n) echo $card($n, $isAdm ? 'mine-admin' : 'mine-employee'); ?></div>
<?php endif; ?>

<?php if ($isAdm): ?>
<h2 class="sec-title" style="margin-top:28px"><?= icon('user', 18) ?> Shared by employees</h2>
<?php if (!$others): ?><section class="card"><p class="empty">No employee has shared a note with you yet.</p></section>
<?php else: ?><div class="proj-grid"><?php foreach ($others as $n) echo $card($n, 'from-employee'); ?></div><?php endif; ?>
<?php endif; ?>
<?php render_footer(); ?>
