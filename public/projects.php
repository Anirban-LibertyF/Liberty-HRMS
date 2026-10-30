<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];
$isAdm = $user['role'] === 'admin';

$invites = $isAdm ? [] : q_all('SELECT p.id, p.name, u.name AS inviter FROM project_members pm JOIN projects p ON p.id=pm.project_id LEFT JOIN users u ON u.id=pm.invited_by
                                 WHERE pm.user_id=? AND pm.status="pending" ORDER BY p.id DESC', [$uid]);
$base = 'SELECT p.*, c.name AS creator_name, e.name AS editor_name FROM projects p JOIN users c ON c.id=p.created_by LEFT JOIN users e ON e.id=p.updated_by';
// admin filters
$fq = $isAdm ? trim((string)($_GET['q'] ?? '')) : '';
$femp = $isAdm ? (int)($_GET['emp'] ?? 0) : 0;
$ftype = $isAdm && in_array($_GET['type'] ?? '', ['personal', 'group'], true) ? $_GET['type'] : '';
$fsort = $isAdm && in_array($_GET['sort'] ?? '', ['new', 'old', 'employee', 'name', 'prog_hi', 'prog_lo'], true) ? $_GET['sort'] : 'new';
if ($isAdm) {
    $where = []; $args = [];
    if ($femp) { $where[] = '(p.created_by=? OR EXISTS (SELECT 1 FROM project_members m WHERE m.project_id=p.id AND m.user_id=? AND m.status<>"rejected"))'; array_push($args, $femp, $femp); }
    if ($ftype === 'group') $where[] = 'EXISTS (SELECT 1 FROM project_members m WHERE m.project_id=p.id AND m.status<>"rejected")';
    if ($ftype === 'personal') $where[] = 'NOT EXISTS (SELECT 1 FROM project_members m WHERE m.project_id=p.id AND m.status<>"rejected")';
    if ($fq !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $fq) . '%';
        $where[] = '(p.name LIKE ? OR p.short_details LIKE ? OR p.tech_stack LIKE ? OR c.name LIKE ? OR EXISTS (SELECT 1 FROM project_members m JOIN users mu ON mu.id=m.user_id WHERE m.project_id=p.id AND mu.name LIKE ?))';
        array_push($args, $like, $like, $like, $like, $like);
    }
    $orderBy = ['new' => 'COALESCE(p.updated_at, p.created_at) DESC', 'old' => 'COALESCE(p.updated_at, p.created_at) ASC', 'employee' => 'c.name ASC, p.name ASC', 'name' => 'p.name ASC', 'prog_hi' => 'p.progress DESC', 'prog_lo' => 'p.progress ASC'][$fsort];
    $projects = q_all($base . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $orderBy, $args);
    $allEmployees = q_all('SELECT id, name FROM users WHERE role<>"admin" ORDER BY name');
} else {
    $projects = q_all("$base WHERE p.created_by=? OR EXISTS (SELECT 1 FROM project_members m WHERE m.project_id=p.id AND m.user_id=? AND m.status='accepted') ORDER BY COALESCE(p.updated_at, p.created_at) DESC", [$uid, $uid]);
}

$members = []; $fileCount = [];
if ($projects) {
    $ids = array_column($projects, 'id'); $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (q_all("SELECT pm.project_id, pm.status, u.name FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id IN ($in) ORDER BY u.name", $ids) as $m) $members[$m['project_id']][] = $m;
    foreach (q_all("SELECT project_id, COUNT(*) n FROM project_files WHERE project_id IN ($in) GROUP BY project_id", $ids) as $f) $fileCount[$f['project_id']] = (int)$f['n'];
}

render_header('Projects', 'projects', $user);
page_title('Projects', $isAdm ? 'All employee and group projects.' : 'Your projects and group projects you have joined.', '<a class="btn btn-brand" href="project.php">' . icon('plus', 16) . ' New project' . '</a>');
?>
<?php if ($invites): ?>
<section class="card" id="invites" style="margin-bottom:20px">
  <div class="card-head"><h2><?= icon('bell', 20) ?> Project invitations</h2></div>
  <?php foreach ($invites as $iv): ?>
    <div class="feed-item" style="border-bottom:1px solid var(--line-2);align-items:center"><span class="feed-ic blue"><?= icon('briefcase', 16) ?></span>
      <div style="flex:1"><div class="tt"><span><?= e($iv['name']) ?></span></div><p><?= e($iv['inviter'] ?? 'Admin') ?> added you to this project.</p></div>
      <?php foreach (['accept' => ['Accept', 'btn btn-sm btn-brand'], 'reject' => ['Reject', 'btn btn-sm btn-danger']] as $ch => [$label, $cls]): ?>
        <form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="do" value="project_respond"><input type="hidden" name="id" value="<?= $iv['id'] ?>"><input type="hidden" name="choice" value="<?= $ch ?>"><button class="<?= $cls ?>"><?= $label ?></button></form>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($isAdm): ?>
<section class="card">
  <form method="get" class="filter-bar">
    <input class="input" type="search" name="q" value="<?= e($fq) ?>" placeholder="Search project, tech, employee…" aria-label="Search">
    <select class="input" name="emp" onchange="this.form.submit()" aria-label="Employee"><option value="0">All employees</option><?php foreach ($allEmployees as $x): ?><option value="<?= $x['id'] ?>" <?= $femp === (int)$x['id'] ? 'selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select>
    <select class="input" name="type" onchange="this.form.submit()" aria-label="Type"><option value="">Personal + group</option><option value="personal" <?= $ftype === 'personal' ? 'selected' : '' ?>>Personal only</option><option value="group" <?= $ftype === 'group' ? 'selected' : '' ?>>Group only</option></select>
    <select class="input" name="sort" onchange="this.form.submit()" aria-label="Sort"><?php foreach (['new' => 'Newest first', 'old' => 'Oldest first', 'employee' => 'Employee A–Z', 'name' => 'Project name A–Z', 'prog_hi' => 'Progress high → low', 'prog_lo' => 'Progress low → high'] as $k => $l): ?><option value="<?= $k ?>" <?= $fsort === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-brand"><?= icon('search', 16) ?> Search</button>
    <?php if ($fq !== '' || $femp || $ftype || $fsort !== 'new'): ?><a class="btn" href="projects.php"><?= icon('x', 14) ?> Reset</a><?php endif; ?>
  </form>
  <p class="muted" style="margin:12px 0;font-size:13px"><?= count($projects) ?> project<?= count($projects) === 1 ? '' : 's' ?></p>
  <div class="hscroll"><table class="t" style="min-width:820px">
    <thead><tr><th>Project</th><th>Added by</th><th>Type</th><th>Members</th><th style="width:150px">Progress</th><th>Updated</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($projects as $p):
        $acc = array_filter($members[$p['id']] ?? [], fn($m) => $m['status'] === 'accepted');
        $pend = array_filter($members[$p['id']] ?? [], fn($m) => $m['status'] === 'pending');
        $isGroup = (bool)array_filter($members[$p['id']] ?? [], fn($m) => $m['status'] !== 'rejected'); ?>
      <tr><td><a href="project.php?id=<?= $p['id'] ?>"><b><?= e($p['name']) ?></b></a><?php if ($p['short_details'] !== ''): ?><br><small class="muted"><?= e(mb_strimwidth($p['short_details'], 0, 70, '…')) ?></small><?php endif; ?></td>
        <td><?= e($p['creator_name']) ?></td>
        <td><span class="badge <?= $isGroup ? 'b-blue' : 'b-slate' ?>"><?= $isGroup ? 'Group' : 'Personal' ?></span></td>
        <td style="font-size:12.5px"><?= $acc ? e(implode(', ', array_column($acc, 'name'))) : '<span class="muted">—</span>' ?><?= $pend ? '<br><small class="muted">' . count($pend) . ' pending</small>' : '' ?></td>
        <td><div class="proj-prog"><div class="meter"><i style="width:<?= (int)$p['progress'] ?>%"></i></div><b><?= (int)$p['progress'] ?>%</b></div></td>
        <td style="white-space:nowrap;font-size:12.5px"><?= e(fmt_date($p['updated_at'] ?? $p['created_at'], 'M d, Y')) ?><?= $p['editor_name'] ? '<br><small class="muted">by ' . e($p['editor_name']) . '</small>' : '' ?></td>
        <td style="white-space:nowrap;text-align:right"><a class="icon-btn" href="project.php?id=<?= $p['id'] ?>" aria-label="Open / edit" title="Open / edit"><?= icon('edit', 16) ?></a>
          <form method="post" action="actions.php" data-confirm="Delete this project and all its files?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="project_delete"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="icon-btn" aria-label="Delete" title="Delete"><?= icon('trash', 16) ?></button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php if (!$projects): ?><p class="empty">No projects match.</p><?php endif; ?>
</section>
<?php elseif (!$projects): ?>
  <section class="card"><p class="empty">No projects yet. Use “New project” to add one.</p></section>
<?php else: ?>
<div class="proj-grid">
  <?php foreach ($projects as $p):
      $acc = array_filter($members[$p['id']] ?? [], fn($m) => $m['status'] === 'accepted');
      $pend = array_filter($members[$p['id']] ?? [], fn($m) => $m['status'] === 'pending');
      $edited = $p['updated_by'] !== null; ?>
    <a class="card proj-card" href="project.php?id=<?= $p['id'] ?>">
      <div class="proj-top"><h3><?= e($p['name']) ?></h3>
        <span class="tag <?= $edited ? 'tag-edit' : '' ?>" title="Added by <?= e($p['creator_name']) ?> · <?= e(fmt_date($p['created_at'])) ?>"><?= $edited ? 'Edited by ' . e($p['editor_name']) : 'Added by ' . e($p['creator_name']) ?></span></div>
      <?php if ($p['short_details'] !== ''): ?><p class="muted"><?= e($p['short_details']) ?></p><?php endif; ?>
      <?php if ($p['tech_stack'] !== ''): ?><div class="tech"><?php foreach (array_slice(array_filter(array_map('trim', explode(',', $p['tech_stack']))), 0, 6) as $t): ?><span class="chip"><?= e($t) ?></span><?php endforeach; ?></div><?php endif; ?>
      <div class="proj-prog"><div class="meter"><i style="width:<?= (int)$p['progress'] ?>%"></i></div><b><?= (int)$p['progress'] ?>%</b></div>
      <div class="proj-foot muted"><?= icon('user', 14) ?> <?= $acc ? e(implode(', ', array_column($acc, 'name'))) : 'Personal' ?><?= $isAdm && $pend ? ' · ' . count($pend) . ' pending' : '' ?>
        <?php if (!empty($fileCount[$p['id']])): ?><span style="margin-left:auto"><?= icon('clip', 14) ?> <?= $fileCount[$p['id']] ?></span><?php endif; ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php render_footer(); ?>
