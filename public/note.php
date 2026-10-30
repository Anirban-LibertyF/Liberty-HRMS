<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];
$isAdm = $user['role'] === 'admin';

$nid = (int)($_GET['id'] ?? 0);
$n = null;
if ($nid) {
    $n = q_one('SELECT n.*, u.name AS owner, ed.name AS editor FROM notes n JOIN users u ON u.id=n.user_id LEFT JOIN users ed ON ed.id=n.updated_by WHERE n.id=?', [$nid]);
    if (!$n || !note_can_access($n, $user)) { http_response_code(404); render_header('Note', 'notes', $user); page_title('Note not found'); echo '<p class="muted">This note does not exist or is not shared with you.</p>'; render_footer(); exit; }
}
$isOwner = !$n || (int)$n['user_id'] === $uid;
$canEdit = !$n || note_can_edit($n, $user);
$adminNote = $isAdm && $isOwner;   // authored by the administrator and shared out to employees
$employees = $adminNote ? q_all('SELECT id, name, emp_code FROM users WHERE role<>"admin" AND status="active" ORDER BY name') : [];
$picked = $adminNote && $n ? array_map('intval', array_column(q_all('SELECT user_id FROM note_shares WHERE note_id=?', [$nid]), 'user_id')) : [];
$files = $n ? q_all('SELECT * FROM note_files WHERE note_id=? ORDER BY id', [$nid]) : [];
$author = $n ? q_val('SELECT name FROM users WHERE id=?', [$n['user_id']]) : '';

render_header($n ? $n['title'] : 'New note', 'notes', $user);
page_title($canEdit ? ($n ? 'Edit note' : 'New note') : 'Shared note', $n && !$isOwner && $canEdit ? 'Owned by ' . e($n['owner']) . ' · shared with the administrator.' : ($n ? 'Created ' . e(fmt_date($n['created_at'])) . ($n['updated_at'] ? ' · last updated ' . e(fmt_date($n['updated_at'], 'M d, h:i A')) . ($n['editor'] && (int)$n['updated_by'] !== (int)$n['user_id'] ? ' by ' . e($n['editor']) : '') : '') : 'Write anything and attach files.'),
    '<a class="btn" href="notes.php">All notes</a>');
?>
<?php if (!$canEdit): ?>
<article class="card" style="max-width:900px">
  <div class="proj-meta"><span class="tag"><?= e(fmt_date($n['note_date'])) ?></span><span class="muted" style="font-size:13px"><?= icon('user', 14) ?> Shared by <?= e($author) ?></span><span class="badge b-slate"><?= icon('lock', 12) ?> Read only</span></div>
  <h2 style="margin:0 0 14px"><?= e($n['title']) ?></h2>
  <div class="rte rte-view"><?= note_body_html((string)$n['body']) ?: '<span class="muted">No text</span>' ?></div>
  <?php if ($files): ?><label class="f" style="margin-top:18px;display:block">Attached files</label><div class="file-list">
    <?php foreach ($files as $f): ?><div class="file-row"><span class="feed-ic blue"><?= icon('clip', 16) ?></span><div class="fn"><?= e($f['file_name']) ?><small><?= e(fmt_date($f['uploaded_at'])) ?></small></div>
      <a class="icon-btn" href="download.php?t=note&id=<?= $f['id'] ?>" target="_blank" rel="noopener" aria-label="View" title="View"><?= icon('eye', 16) ?></a></div><?php endforeach; ?></div><?php endif; ?>
</article>
<?php render_footer(); exit; endif; ?>
<form class="card" method="post" action="actions.php" enctype="multipart/form-data" style="max-width:900px">
  <?= csrf_field() ?><input type="hidden" name="do" value="note_save"><input type="hidden" name="id" value="<?= $nid ?>"><input type="hidden" name="back" value="<?= $n ? 'note.php?id=' . $nid : 'note.php' ?>">
  <div class="field-row field"><div><label class="f" for="nTitle">Note heading <span class="req">*</span></label><input class="input" id="nTitle" name="title" required maxlength="200" value="<?= e($n['title'] ?? '') ?>" placeholder="e.g. Meeting notes"></div>
    <div><label class="f" for="nDate">Date <span class="req">*</span></label><input class="input" type="date" id="nDate" name="note_date" required value="<?= e($n['note_date'] ?? today()) ?>"></div></div>

  <div class="field"><label class="f">Note</label>
    <div class="rte-bar" id="rteBar">
      <select data-cmd="fontName" title="Font" aria-label="Font"><option value="">Font</option><?php foreach (NOTE_FONTS as $ft): ?><option value="<?= e($ft) ?>" style="font-family:'<?= e($ft) ?>'"><?= e($ft) ?></option><?php endforeach; ?></select>
      <select data-cmd="fontSize" title="Text size" aria-label="Text size"><option value="">Size</option><?php foreach ([12, 14, 16, 18, 20, 24, 28, 32, 40] as $sz): ?><option value="<?= $sz ?>"><?= $sz ?></option><?php endforeach; ?></select>
      <button type="button" data-cmd="bold" title="Bold (Ctrl+B)"><b>B</b></button>
      <button type="button" data-cmd="italic" title="Italic (Ctrl+I)"><i>I</i></button>
      <button type="button" data-cmd="underline" title="Underline (Ctrl+U)"><u>U</u></button>
      <span class="rte-sep"></span>
      <span class="rte-grp"><label class="rte-color" title="Text colour"><span style="font-weight:700">A</span><input type="color" value="#b91c1c" data-cmd="foreColor"></label>
      <button type="button" class="rte-x" data-clear="color" title="Default text colour (remove colour)" aria-label="Default text colour"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg></button></span>
      <span class="rte-grp"><label class="rte-color" title="Highlight colour"><span class="hl">H</span><input type="color" value="#fff176" data-cmd="hiliteColor"></label>
      <?php foreach (['#fff176', '#a7f3d0', '#fbcfe8', '#bfdbfe'] as $hc): ?><button type="button" class="rte-sw" data-hl="<?= $hc ?>" style="background:<?= $hc ?>" title="Highlight" aria-label="Highlight"></button><?php endforeach; ?>
      <button type="button" class="rte-x" data-clear="bg" title="No highlight (remove highlight)" aria-label="No highlight"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg></button></span>
      <span class="rte-sep"></span>
      <button type="button" data-cmd="removeFormat" title="Clear formatting">Clear</button>
    </div>
    <div class="input rte" id="rte" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="Start writing…"><?= $n ? note_body_html((string)$n['body']) : '' ?></div>
    <input type="hidden" name="body" id="rteInput"></div>

  <div class="field"><label class="f">Attached files</label>
    <?php if ($files): ?><div class="file-list">
      <?php foreach ($files as $f): ?>
        <div class="file-row"><span class="feed-ic blue"><?= icon('clip', 16) ?></span>
          <div class="fn"><?= e($f['file_name']) ?><small><?= e(fmt_date($f['uploaded_at'])) ?></small></div>
          <a class="icon-btn" href="download.php?t=note&id=<?= $f['id'] ?>" target="_blank" rel="noopener" aria-label="View" title="View"><?= icon('eye', 16) ?></a>
          <input type="hidden" name="remove_files[]" value="<?= $f['id'] ?>" disabled>
          <button type="button" class="icon-btn" data-remove-file aria-label="Remove" title="Remove"><?= icon('trash', 16) ?></button></div>
      <?php endforeach; ?></div><?php endif; ?>
    <div class="drop" data-multi="noteFiles" data-list="newFiles"><?= icon('upload', 26) ?><b>Drag &amp; drop files here, or click to browse</b><span class="muted" style="font-size:12.5px">Images, PDF, Word, Excel, PowerPoint, CSV, TXT · up to 10MB each · multiple allowed</span></div>
    <input type="file" id="noteFiles" name="files[]" multiple hidden accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt">
    <div class="file-list" id="newFiles"></div></div>

  <?php if ($isOwner && !$adminNote): ?>
  <label class="toggle-row toggle-note"><div class="tn-ic"><?= icon('shield', 18) ?></div>
    <div class="tn-text"><b>Allow admin access to this note</b><small>When on, the administrator can view, edit and delete it.</small></div>
    <input type="checkbox" class="switch" name="admin_access" value="1" <?= !empty($n['admin_access']) ? 'checked' : '' ?>></label>
  <?php elseif ($adminNote): $all = !$n || (int)$n['share_all'] === 1; ?>
  <div class="field"><label class="f">Share this note with</label>
    <div class="aud-opts"><label class="chk"><input type="radio" name="audience" value="all" <?= $all ? 'checked' : '' ?>> All employees</label>
      <label class="chk"><input type="radio" name="audience" value="some" <?= $all ? '' : 'checked' ?>> Specific employees</label></div>
    <div class="member-pick" id="audList" <?= $all ? 'hidden' : '' ?> style="margin-top:10px"><input class="input" type="search" data-filter-list="#audList" placeholder="Search employees…" aria-label="Search employees" style="grid-column:1/-1">
      <?php foreach ($employees as $emp): ?><label class="member-opt"><input type="checkbox" name="recipients[]" value="<?= $emp['id'] ?>" <?= in_array((int)$emp['id'], $picked, true) ? 'checked' : '' ?>> <span><?= e($emp['name']) ?> <small class="muted"><?= e($emp['emp_code']) ?></small></span></label><?php endforeach; ?></div>
    <div class="help">Employees get a notification and can read the note and open its files, but cannot edit it.</div></div>
  <?php endif; ?>

  <div class="btn-row"><button class="btn btn-brand"><?= icon('save', 16) ?> <?= $n ? 'Update note' : 'Save note' ?></button>
    <?php if ($n): ?><button class="btn btn-danger" type="submit" form="delNote"><?= icon('trash', 16) ?> Delete</button><?php endif; ?></div>
</form>
<?php if ($n): ?><form id="delNote" method="post" action="actions.php" data-confirm="Delete this note and its files?"><?= csrf_field() ?><input type="hidden" name="do" value="note_delete"><input type="hidden" name="id" value="<?= $nid ?>"></form><?php endif; ?>
<?php render_footer(); ?>
