<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$qs = trim((string)($_GET['q'] ?? ''));
$like = '%' . addcslashes($qs, '%_\\') . '%';
$isAdm = $user['role'] === 'admin';
$uid = (int)$user['id'];
$adm = $isAdm ? 1 : 0;
$people = $hols = $logs = $anns = $projects = $expenses = $notes = $docs = [];
if (mb_strlen($qs) >= 2) {
    $people = q_all('SELECT id, name, emp_code, designation, department, email FROM users WHERE ' . ($isAdm ? '1=1' : 'status="active"') . ' AND (name LIKE ? OR emp_code LIKE ? OR designation LIKE ? OR department LIKE ? OR email LIKE ?) ORDER BY name LIMIT 15', [$like, $like, $like, $like, $like]);
    // only what this user is allowed to open
    $projects = q_all('SELECT p.id, p.name, p.short_details, p.tech_stack FROM projects p JOIN users c ON c.id=p.created_by
        WHERE (p.name LIKE ? OR p.short_details LIKE ? OR p.tech_stack LIKE ? OR c.name LIKE ?)
          AND (? OR p.created_by=? OR EXISTS (SELECT 1 FROM project_members m WHERE m.project_id=p.id AND m.user_id=? AND m.status="accepted")) ORDER BY p.name LIMIT 10',
        [$like, $like, $like, $like, $adm, $uid, $uid]);
    $expenses = q_all('SELECT e.id, e.title, e.expense_date, e.total, u.name AS by_name FROM expenses e JOIN users u ON u.id=e.created_by
        WHERE (e.title LIKE ? OR u.name LIKE ? OR EXISTS (SELECT 1 FROM expense_items i WHERE i.expense_id=e.id AND i.item_name LIKE ?)) AND (? OR e.created_by=?)
        ORDER BY e.expense_date DESC LIMIT 10', [$like, $like, $like, $adm, $uid]);
    foreach (q_all('SELECT * FROM notes WHERE title LIKE ? ORDER BY note_date DESC LIMIT 60', [$like]) as $n) {
        if (count($notes) < 10 && note_can_access($n, $user)) $notes[] = $n;
    }
    $joinOpen = $isAdm || joining_letter_days_left($user['joined_on']) === 0 ? 1 : 0;
    $docs = q_all('SELECT d.id, d.title, d.category, d.file_name, u.name AS owner FROM documents d LEFT JOIN users u ON u.id=d.user_id
        WHERE (d.title LIKE ? OR d.file_name LIKE ? OR d.category LIKE ?) AND (? OR d.user_id IS NULL OR d.user_id=?)
          AND (? OR d.category<>"Joining Letter") ORDER BY d.uploaded_at DESC LIMIT 10', [$like, $like, $like, $adm, $uid, $joinOpen]);
    $hols = q_all('SELECT * FROM holidays WHERE name LIKE ? ORDER BY holiday_date LIMIT 10', [$like]);
    $logs = q_all('SELECT log_date, tag, content FROM work_logs WHERE user_id=? AND (content LIKE ? OR tag LIKE ?) ORDER BY log_date DESC LIMIT 10', [$user['id'], $like, $like]);
    $anns = q_all('SELECT * FROM announcements WHERE title LIKE ? OR body LIKE ? ORDER BY posted_on DESC LIMIT 10', [$like, $like]);
}
$shifts = (mb_stripos('shift ' . $user['shift_name'], $qs) !== false && $qs !== '') ? [$user] : [];
$none = !$people && !$hols && !$logs && !$anns && !$shifts && !$projects && !$expenses && !$notes && !$docs;

render_header('Search', 'dashboard', $user);
page_title('Search results', $qs === '' ? 'Type at least 2 characters in the search box.' : 'Results for “' . e($qs) . '”');
if ($none && $qs !== ''): ?><div class="card empty">Nothing matched your search.</div><?php endif; ?>
<div class="stack">
<?php if ($people): ?><section class="card"><div class="card-head"><h2>Coworkers</h2></div><div class="table-wrap"><table class="t"><tbody>
  <?php foreach ($people as $p): ?><tr><td><?php if ($isAdm && $p['emp_code'] !== 'ADMIN'): ?><a href="admin_employee.php?id=<?= (int)$p['id'] ?>"><b><?= e($p['name']) ?></b></a><?php else: ?><b><?= e($p['name']) ?></b><?php endif; ?> <span class="code muted"><?= e($p['emp_code']) ?></span></td><td><?= e($p['designation']) ?></td><td><?= e($p['department']) ?></td><td><?= e($p['email']) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<?php if ($projects): ?><section class="card"><div class="card-head"><h2>Projects</h2></div><?php foreach ($projects as $p): ?><div class="kv"><a href="project.php?id=<?= (int)$p['id'] ?>"><b><?= e($p['name']) ?></b></a><span class="muted"><?= e(mb_strimwidth($p['short_details'] ?: $p['tech_stack'], 0, 70, '…')) ?></span></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($expenses): ?><section class="card"><div class="card-head"><h2>Expenses</h2></div><?php foreach ($expenses as $x): ?><div class="kv"><a href="expense.php?id=<?= (int)$x['id'] ?>"><b><?= e($x['title']) ?></b></a><span class="muted"><?= e(fmt_date($x['expense_date'])) ?> · <?= e($x['by_name']) ?> · <?= e(number_format((float)$x['total'], 2)) ?></span></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($notes): ?><section class="card"><div class="card-head"><h2>Notes</h2></div><?php foreach ($notes as $n): ?><div class="kv"><a href="note.php?id=<?= (int)$n['id'] ?>"><b><?= e($n['title']) ?></b></a><span class="muted"><?= e(fmt_date($n['note_date'])) ?></span></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($docs): ?><section class="card"><div class="card-head"><h2>Documents</h2></div><?php foreach ($docs as $d): ?><div class="kv"><a href="download.php?t=doc&amp;id=<?= (int)$d['id'] ?>"><b><?= e($d['title']) ?></b></a><span class="muted"><?= e($d['category']) ?><?= $d['owner'] ? ' · ' . e($d['owner']) : '' ?></span></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($shifts): ?><section class="card"><div class="card-head"><h2>Your shift</h2></div><p><?= e($user['shift_name']) ?>: <?= e(substr($user['start_time'], 0, 5)) ?> – <?= e(substr($user['end_time'], 0, 5)) ?>.</p></section><?php endif; ?>
<?php if ($hols): ?><section class="card"><div class="card-head"><h2>Holidays</h2></div><?php foreach ($hols as $h): ?><div class="kv"><span><?= e($h['name']) ?></span><span><?= e(fmt_date($h['holiday_date'], 'D, M j, Y')) ?></span></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($logs): ?><section class="card"><div class="card-head"><h2>Your work logs</h2></div><?php foreach ($logs as $l): ?><div class="kv"><a href="worklog.php?date=<?= e($l['log_date']) ?>"><?= e(fmt_date($l['log_date'])) ?> <?= $l['tag'] ? '· ' . e($l['tag']) : '' ?></a><span class="muted"><?= e(mb_strimwidth($l['content'], 0, 70, '…')) ?></span></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($anns): ?><section class="card"><div class="card-head"><h2>Announcements</h2></div><?php foreach ($anns as $a): ?><article class="ann"><div class="meta"><span><?= e($a['dept']) ?></span><span><?= e(fmt_date($a['posted_on'], 'M d')) ?></span></div><h3><?= e($a['title']) ?></h3><p><?= e($a['body']) ?></p></article><?php endforeach; ?></section><?php endif; ?>
</div>
<?php render_footer(); ?>
