<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$qs = trim((string)($_GET['q'] ?? ''));
$like = '%' . addcslashes($qs, '%_\\') . '%';
$people = $hols = $logs = $anns = [];
if (mb_strlen($qs) >= 2) {
    $people = q_all('SELECT name, emp_code, designation, department, email FROM users WHERE status="active" AND (name LIKE ? OR emp_code LIKE ? OR designation LIKE ? OR department LIKE ?) ORDER BY name LIMIT 15', [$like, $like, $like, $like]);
    $hols = q_all('SELECT * FROM holidays WHERE name LIKE ? ORDER BY holiday_date LIMIT 10', [$like]);
    $logs = q_all('SELECT log_date, tag, content FROM work_logs WHERE user_id=? AND (content LIKE ? OR tag LIKE ?) ORDER BY log_date DESC LIMIT 10', [$user['id'], $like, $like]);
    $anns = q_all('SELECT * FROM announcements WHERE title LIKE ? OR body LIKE ? ORDER BY posted_on DESC LIMIT 10', [$like, $like]);
}
$shifts = (mb_stripos('shift ' . $user['shift_name'], $qs) !== false && $qs !== '') ? [$user] : [];
$none = !$people && !$hols && !$logs && !$anns && !$shifts;

render_header('Search', 'dashboard', $user);
page_title('Search results', $qs === '' ? 'Type at least 2 characters in the search box.' : 'Results for “' . e($qs) . '”');
if ($none && $qs !== ''): ?><div class="card empty">Nothing matched your search.</div><?php endif; ?>
<div class="stack">
<?php if ($people): ?><section class="card"><div class="card-head"><h2>Coworkers</h2></div><div class="table-wrap"><table class="t"><tbody>
  <?php foreach ($people as $p): ?><tr><td><b><?= e($p['name']) ?></b> <span class="code muted"><?= e($p['emp_code']) ?></span></td><td><?= e($p['designation']) ?></td><td><?= e($p['department']) ?></td><td><?= e($p['email']) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<?php if ($shifts): ?><section class="card"><div class="card-head"><h2>Your shift</h2></div><p><?= e($user['shift_name']) ?>: <?= e(substr($user['start_time'], 0, 5)) ?> – <?= e(substr($user['end_time'], 0, 5)) ?>.</p></section><?php endif; ?>
<?php if ($hols): ?><section class="card"><div class="card-head"><h2>Holidays</h2></div><?php foreach ($hols as $h): ?><div class="kv"><span><?= e($h['name']) ?></span><span><?= e(fmt_date($h['holiday_date'], 'D, M j, Y')) ?></span></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($logs): ?><section class="card"><div class="card-head"><h2>Your work logs</h2></div><?php foreach ($logs as $l): ?><div class="kv"><a href="worklog.php?date=<?= e($l['log_date']) ?>"><?= e(fmt_date($l['log_date'])) ?> <?= $l['tag'] ? '· ' . e($l['tag']) : '' ?></a><span class="muted"><?= e(mb_strimwidth($l['content'], 0, 70, '…')) ?></span></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($anns): ?><section class="card"><div class="card-head"><h2>Announcements</h2></div><?php foreach ($anns as $a): ?><article class="ann"><div class="meta"><span><?= e($a['dept']) ?></span><span><?= e(fmt_date($a['posted_on'], 'M d')) ?></span></div><h3><?= e($a['title']) ?></h3><p><?= e($a['body']) ?></p></article><?php endforeach; ?></section><?php endif; ?>
</div>
<?php render_footer(); ?>
