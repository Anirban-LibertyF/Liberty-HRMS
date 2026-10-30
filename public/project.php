<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];
$isAdm = $user['role'] === 'admin';

$pid = (int)($_GET['id'] ?? 0);
$p = null;
if ($pid) {
    $p = project_load($pid);
    if (!$p || !project_can_access($p, $user)) { http_response_code(404); render_header('Project', 'projects', $user); page_title('Project not found'); echo '<p class="muted">This project does not exist or you do not have access to it.</p>'; render_footer(); exit; }
}
$files = $p ? q_all('SELECT pf.*, u.name AS by_name FROM project_files pf LEFT JOIN users u ON u.id=pf.uploaded_by WHERE pf.project_id=? ORDER BY pf.id', [$pid]) : [];
$members = $p ? q_all('SELECT pm.status, u.id, u.name, u.emp_code FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=? ORDER BY u.name', [$pid]) : [];
$memberStatus = array_column($members, 'status', 'id');
$employees = $isAdm ? q_all('SELECT id, name, emp_code FROM users WHERE role<>"admin" AND status="active" ORDER BY name') : [];
$v = fn(string $k) => e($p[$k] ?? '');
$progress = (int)($p['progress'] ?? 0);
$edited = $p && $p['updated_by'] !== null;
$acc = array_filter($members, fn($m) => $m['status'] === 'accepted');

render_header($p ? $p['name'] : 'New project', 'projects', $user);
page_title($p ? $p['name'] : 'New project', $p ? 'Update progress, files and notes. Members of this project can see and edit it.' : 'Fill in the details below.',
    '<a class="btn" href="projects.php">All projects</a>');
?>
<form class="card" method="post" action="actions.php" enctype="multipart/form-data" style="max-width:860px">
  <?= csrf_field() ?><input type="hidden" name="do" value="project_save"><input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="back" value="<?= $p ? 'project.php?id=' . $pid : 'project.php' ?>">
  <?php if ($p): ?>
  <div class="proj-meta">
    <span class="tag <?= $edited ? 'tag-edit' : '' ?>"><?= $edited ? 'Edited by ' . e($p['editor_name']) . ' · ' . e(fmt_date($p['updated_at'], 'M d, h:i A')) : 'Added by ' . e($p['creator_name']) . ' · ' . e(fmt_date($p['created_at'], 'M d, Y')) ?></span>
    <?php if ($edited): ?><span class="muted" style="font-size:12.5px">Added by <?= e($p['creator_name']) ?> on <?= e(fmt_date($p['created_at'])) ?></span><?php endif; ?>
    <?php if ($acc): ?><span class="muted" style="font-size:12.5px"><?= icon('user', 14) ?> Members: <?= e(implode(', ', array_column($acc, 'name'))) ?></span><?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="field"><label class="f" for="pName">Project name <span class="req">*</span></label><input class="input" id="pName" name="name" required maxlength="160" value="<?= $v('name') ?>" placeholder="e.g. Liberty Mobile App"></div>
  <div class="field"><label class="f" for="pShort">Short details</label><input class="input" id="pShort" name="short_details" maxlength="300" value="<?= $v('short_details') ?>" placeholder="One line about the project"></div>
  <div class="field"><label class="f" for="pTech">Tech stack</label><input class="input" id="pTech" name="tech_stack" maxlength="300" value="<?= $v('tech_stack') ?>" placeholder="Comma separated, e.g. PHP, MySQL, React"></div>
  <div class="field-row field">
    <div><label class="f" for="pGit">GitHub link</label><div class="copy-row"><input class="input" type="url" id="pGit" name="github_url" maxlength="500" value="<?= $v('github_url') ?>" placeholder="https://github.com/org/repo"><button type="button" class="btn" data-copy="#pGit" title="Copy link"><?= icon('copy', 16) ?> Copy</button></div></div>
    <div><label class="f" for="pFigma">Figma link</label><div class="copy-row"><input class="input" type="url" id="pFigma" name="figma_url" maxlength="500" value="<?= $v('figma_url') ?>" placeholder="https://www.figma.com/file/…"><button type="button" class="btn" data-copy="#pFigma" title="Copy link"><?= icon('copy', 16) ?> Copy</button></div></div>
  </div>
  <div class="field"><label class="f" for="pDesc">Short description</label><textarea class="input" id="pDesc" name="description" rows="4" maxlength="5000" placeholder="What is this project about?"><?= $v('description') ?></textarea></div>

  <div class="field" data-progress><label class="f" for="pProg">Progress completed</label>
    <div class="prog-edit"><input type="range" min="0" max="100" step="1" value="<?= $progress ?>" aria-label="Progress slider">
      <span class="prog-num"><input class="input" type="number" id="pProg" name="progress" min="0" max="100" value="<?= $progress ?>"><b>%</b></span></div></div>

  <div class="field"><label class="f">Attached files</label>
    <?php if ($files): ?><div class="file-list">
      <?php foreach ($files as $f): ?>
        <div class="file-row"><span class="feed-ic blue"><?= icon('clip', 16) ?></span>
          <div class="fn"><?= e($f['file_name']) ?><small><?= e(fmt_date($f['uploaded_at'])) ?><?= $f['by_name'] ? ' · ' . e($f['by_name']) : '' ?></small></div>
          <a class="icon-btn" href="download.php?t=project&id=<?= $f['id'] ?>" target="_blank" rel="noopener" aria-label="View" title="View"><?= icon('eye', 16) ?></a>
          <input type="hidden" name="remove_files[]" value="<?= $f['id'] ?>" disabled>
          <button type="button" class="icon-btn" data-remove-file aria-label="Remove" title="Remove"><?= icon('trash', 16) ?></button></div>
      <?php endforeach; ?></div><?php endif; ?>
    <div class="drop" data-multi="projFiles" data-list="newFiles"><?= icon('upload', 26) ?><b>Drag &amp; drop files here, or click to browse</b><span class="muted" style="font-size:12.5px">Images, PDF, Word, Excel, PowerPoint, CSV, TXT · up to 10MB each · multiple allowed</span></div>
    <input type="file" id="projFiles" name="files[]" multiple hidden accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt">
    <div class="file-list" id="newFiles"></div></div>

  <div class="field"><label class="f" for="pNotes">Special notes</label><textarea class="input" id="pNotes" name="notes" rows="4" maxlength="5000" placeholder="Any extra details about the project…"><?= $v('notes') ?></textarea></div>

  <div class="field"><div style="display:flex;justify-content:space-between;align-items:center"><label class="f" for="pSecrets"><?= icon('lock', 14) ?> Project secret keys / env</label>
      <button type="button" class="btn btn-sm" data-mask="#pSecrets"><?= icon('eye', 14) ?> Show / hide</button></div>
    <textarea class="input masked" id="pSecrets" name="secrets" rows="5" maxlength="8000" autocomplete="off" spellcheck="false" placeholder="API_KEY=…&#10;DB_PASSWORD=…"><?= e(secret_decrypt($p['secrets'] ?? null)) ?></textarea>
    <div class="help">Stored encrypted. Visible to everyone who is part of this project and to the administrator.</div></div>

  <?php if ($isAdm): $groupMode = count(array_filter($members, fn($m) => $m['status'] !== 'rejected')) > 1; ?>
  <div class="field"><label class="f">Project type</label>
    <div class="aud-opts"><label class="chk"><input type="radio" name="project_type" value="single" <?= $groupMode ? '' : 'checked' ?>> Single project <small class="muted">(one employee)</small></label>
      <label class="chk"><input type="radio" name="project_type" value="group" <?= $groupMode ? 'checked' : '' ?>> Group project <small class="muted">(multiple employees)</small></label></div></div>
  <div class="field"><label class="f"><span id="memLabel"><?= $groupMode ? 'Group members' : 'Assign to employee' ?></span></label><input type="hidden" name="members_present" value="1">
    <div class="pick-tools">
      <input class="input" type="search" data-filter-list="#memberPick" placeholder="Search employees by name or ID…" aria-label="Search employees">
      <span class="badge b-mint" id="memCount">0 selected</span>
      <button type="button" class="btn btn-sm" id="memAll" <?= $groupMode ? '' : 'hidden' ?>>Select shown</button>
      <button type="button" class="btn btn-sm" id="memNone">Clear</button></div>
    <div class="member-pick pick-scroll" id="memberPick">
      <?php foreach ($employees as $emp): $st = $memberStatus[$emp['id']] ?? null; ?>
        <label class="member-opt"><input type="checkbox" name="members[]" value="<?= $emp['id'] ?>" <?= $st ? 'checked' : '' ?>> <span><?= e($emp['name']) ?> <small class="muted"><?= e($emp['emp_code']) ?></small></span>
          <?php if ($st): ?><span class="badge <?= ['accepted' => 'b-green', 'pending' => 'b-amber', 'rejected' => 'b-red'][$st] ?>"><?= e(ucfirst($st)) ?></span><?php endif; ?></label>
      <?php endforeach; ?></div>
    <div class="empty" id="memEmpty" hidden style="padding:14px">No employee matches your search.</div>
    <div class="help">The employee gets a notification and joins only after accepting. Unticking removes someone. A single project can have one employee; leave it empty to keep it unassigned.</div></div>
  <?php endif; ?>

  <div class="btn-row"><button class="btn btn-brand"><?= icon('save', 16) ?> <?= $p ? 'Save changes' : 'Create project' ?></button>
    <?php if ($p && project_can_delete($p, $user)): ?><button class="btn btn-danger" type="submit" form="delProject"><?= icon('trash', 16) ?> Delete project</button><?php endif; ?></div>
</form>
<?php if ($p && project_can_delete($p, $user)): ?>
<form id="delProject" method="post" action="actions.php" data-confirm="Delete this project and all its files?"><?= csrf_field() ?><input type="hidden" name="do" value="project_delete"><input type="hidden" name="id" value="<?= $pid ?>"></form>
<?php endif; ?>
<?php render_footer(); ?>
