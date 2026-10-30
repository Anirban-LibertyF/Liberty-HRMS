<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_staff();
$isAdmin = $user['role'] === 'admin';

$tabs = $isAdmin
    ? ['overview' => 'Dashboard', 'employees' => 'Employees', 'attendance' => 'Attendance', 'leaves' => 'Leave Requests', 'logs' => 'Daily Work Logs', 'holidays' => 'Holidays', 'announce' => 'Announcements', 'payroll' => 'Payroll']
    : ['overview' => 'Overview', 'leaves' => 'Approvals', 'logs' => 'Work Logs'];
$tab = $_GET['tab'] ?? 'overview';
if ($tab === 'approvals') $tab = 'leaves';
if (!isset($tabs[$tab])) $tab = 'overview';

// Scope: the administrator sees every employee, a manager sees direct reports only.
$scopeSql = $isAdmin ? 'u.role <> "admin"' : 'u.manager_id = ?';
$scopeP = $isAdmin ? [] : [$user['id']];
$inScope = fn(array $r) => $isAdmin || (int)($r['manager_id'] ?? 0) === (int)$user['id'];

$pendLeave = (int)q_val("SELECT COUNT(*) FROM leave_requests lr JOIN users u ON u.id=lr.user_id WHERE lr.status='pending' AND $scopeSql", $scopeP);
$pendReg = (int)q_val("SELECT COUNT(*) FROM regularizations r JOIN users u ON u.id=r.user_id WHERE r.status='pending' AND $scopeSql", $scopeP);
$submittedLogs = (int)q_val("SELECT COUNT(*) FROM work_logs w JOIN users u ON u.id=w.user_id WHERE w.status='submitted' AND $scopeSql", $scopeP);

$stateMeta = [
    'present_live' => ['Present · Working', 'b-green'], 'break' => ['Present · On Break', 'b-amber'], 'present_done' => ['Present', 'b-green'],
    'leave' => ['On Leave', 'b-amber'], 'holiday' => ['Holiday', 'b-slate'], 'weekend' => ['Weekend', 'b-slate'], 'weekoff' => ['Week Off', 'b-slate'],
    'absent' => ['Absent', 'b-red'], 'absent_so_far' => ['Not in yet', 'b-red'], 'upcoming' => ['Upcoming', 'b-blue'], 'notjoined' => ['Not joined', 'b-slate'],
];
$badge = fn(string $s) => '<span class="badge ' . $stateMeta[$s][1] . '">' . e($stateMeta[$s][0]) . '</span>';

render_header($tabs[$tab] . ' · Admin', $isAdmin ? $tab : 'admin', $user);
page_title($tabs[$tab], $isAdmin ? 'Workforce administration for every employee.' : 'Approvals for your direct reports.');
if (!$isAdmin): ?>
<div class="seg" style="margin-bottom:22px;flex-wrap:wrap"><?php foreach ($tabs as $k => $label): ?><a href="admin.php?tab=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($label) ?><?= $k === 'leaves' && ($pendLeave + $pendReg) ? " <span class='badge b-amber'>" . ($pendLeave + $pendReg) . '</span>' : '' ?><?= $k === 'logs' && $submittedLogs ? " <span class='badge b-amber'>$submittedLogs</span>" : '' ?></a><?php endforeach; ?></div>
<?php endif; ?>

<?php if ($tab === 'overview'):
    $roster = array_values(array_filter(roster_for_date(today()), $inScope));
    $cnt = array_count_values(array_column($roster, 'state'));
    $present = ($cnt['present_live'] ?? 0) + ($cnt['break'] ?? 0) + ($cnt['present_done'] ?? 0);
    $absent = ($cnt['absent_so_far'] ?? 0) + ($cnt['absent'] ?? 0);
    $onLeave = $cnt['leave'] ?? 0;
    $weekOff = $cnt['weekoff'] ?? 0;
    $offDay = is_weekend(today()) || holidays_between(today(), today());
    $people = fn(callable $f) => array_values(array_map(fn($r) => ['name' => $r['name'], 'code' => $r['emp_code'], 'sub' => $r['leave_name'] ?? $r['designation'] ?? ''], array_filter($roster, $f)));
    $byState = fn(array $states) => $people(fn($r) => in_array($r['state'], $states, true));
    $pendPeople = function (string $table) use ($scopeSql, $scopeP) {
        $rows = q_all("SELECT DISTINCT u.name, u.emp_code AS code, '' AS sub FROM $table t JOIN users u ON u.id=t.user_id WHERE " . ($table === 'work_logs' ? "t.status='submitted'" : "t.status='pending'") . " AND $scopeSql ORDER BY u.name", $scopeP);
        return $rows;
    };
    $statCard = fn(string $title, int $n, array $list, ?string $link = null) => '<button type="button" class="card stat stat-btn" data-stat="' . e(json_encode(['title' => $title, 'list' => $list, 'link' => $link])) . '"><div class="eyebrow">' . e($title) . '</div><div class="big">' . $n . '</div></button>';
    $pendCard = fn(string $title, int $n, array $list, string $link) => '<button type="button" class="card stat-btn" data-stat="' . e(json_encode(['title' => $title, 'list' => $list, 'link' => $link])) . '"><div class="eyebrow">' . e($title) . '</div><div style="font-size:30px;font-weight:600;margin-top:6px">' . $n . '</div></button>'; ?>
  <div class="grid g-4 g-5">
    <?= $statCard('Total employees', count($roster), $people(fn() => true)) ?>
    <?= $statCard('Present today', $present, $byState(['present_live', 'break', 'present_done'])) ?>
    <?= $offDay ? $statCard('Off day today', count($roster), $people(fn() => true)) : $statCard('Absent / not in yet today', $absent, $byState(['absent_so_far', 'absent'])) ?>
    <?= $statCard('On approved leave today', $onLeave, $byState(['leave'])) ?>
    <?= $statCard('On week off today', $weekOff, $byState(['weekoff'])) ?>
  </div>
  <div class="grid g-3 mt">
    <?= $pendCard('Pending leave requests', $pendLeave, $pendPeople('leave_requests'), 'admin.php?tab=leaves') ?>
    <?= $pendCard('Pending regularizations', $pendReg, $pendPeople('regularizations'), 'admin.php?tab=leaves') ?>
    <?= $pendCard('Submitted work logs', $submittedLogs, $pendPeople('work_logs'), 'admin.php?tab=logs&status=submitted') ?>
  </div>
  <dialog id="statDlg">
    <div class="modal-h"><h2 id="statTitle"></h2><button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
    <div class="modal-b"><ul id="statList" class="stat-list"></ul><a id="statLink" class="btn btn-sm" style="display:none;margin-top:14px">Open page →</a></div>
  </dialog>
  <script>
  (function () {
    var d = document.getElementById('statDlg'), ul = document.getElementById('statList'), lk = document.getElementById('statLink');
    document.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-stat]'); if (!b) return;
      var x = JSON.parse(b.getAttribute('data-stat'));
      document.getElementById('statTitle').textContent = x.title + ' (' + x.list.length + ')';
      ul.innerHTML = '';
      if (!x.list.length) { var li = document.createElement('li'); li.className = 'muted'; li.textContent = 'No employees.'; ul.appendChild(li); }
      x.list.forEach(function (p) {
        var li = document.createElement('li'), n = document.createElement('b'), c = document.createElement('span'), s = document.createElement('small');
        n.textContent = p.name; c.className = 'code muted'; c.textContent = ' ' + p.code; s.className = 'muted'; s.textContent = p.sub ? ' · ' + p.sub : '';
        li.appendChild(n); li.appendChild(c); li.appendChild(s); ul.appendChild(li);
      });
      if (x.link) { lk.href = x.link; lk.style.display = ''; } else { lk.style.display = 'none'; }
      d.showModal();
    });
  })();
  </script>
  <section class="card mt"><div class="card-head"><h2>Today's Attendance — <?= e(date('D, M j')) ?></h2><?php if ($isAdmin): ?><a href="admin.php?tab=attendance">Full attendance →</a><?php endif; ?></div>
    <div class="table-wrap"><table class="t"><thead><tr><th>Employee</th><th>Status</th><th>In</th><th>Out</th><th>Working time</th></tr></thead><tbody>
    <?php foreach ($roster as $r): ?>
      <tr><td><b><?= e($r['name']) ?></b> <span class="code muted"><?= e($r['emp_code']) ?></span><br><small class="muted"><?= e($r['designation']) ?></small></td><td><?= $badge($r['state']) ?></td>
        <td><?= e(fmt_time($r['first_in'])) ?></td><td><?= e(fmt_time($r['last_out'])) ?></td><td><?= $r['first_in'] ? fmt_hm((int)$r['worked_live'], false) : '—' ?></td></tr>
    <?php endforeach; if (!$roster): ?><tr><td colspan="5" class="empty">No employees yet.</td></tr><?php endif; ?></tbody></table></div></section>

<?php elseif ($tab === 'attendance' && $isAdmin):
    $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : today();
    $q = trim((string)($_GET['q'] ?? ''));
    $roster = roster_for_date($d, $q);
    $cnt = array_count_values(array_column($roster, 'state'));
    $present = ($cnt['present_live'] ?? 0) + ($cnt['break'] ?? 0) + ($cnt['present_done'] ?? 0);
    $absent = ($cnt['absent_so_far'] ?? 0) + ($cnt['absent'] ?? 0); ?>
  <form class="head-actions" method="get" style="margin-bottom:18px"><input type="hidden" name="tab" value="attendance">
    <a class="btn btn-sm" href="admin.php?tab=attendance&date=<?= date('Y-m-d', strtotime("$d -1 day")) ?>">‹</a>
    <input class="input" type="date" name="date" value="<?= e($d) ?>" style="width:170px" onchange="this.form.submit()" aria-label="Date">
    <a class="btn btn-sm" href="admin.php?tab=attendance&date=<?= date('Y-m-d', strtotime("$d +1 day")) ?>">›</a>
    <input class="input" name="q" value="<?= e($q) ?>" placeholder="Search name, ID, department" style="width:240px"><button class="btn btn-sm">Search</button>
    <span class="badge b-green">Present: <?= $present ?></span><span class="badge b-red">Absent: <?= $absent ?></span><span class="badge b-amber">On leave: <?= $cnt['leave'] ?? 0 ?></span></form>
  <section class="card"><div class="card-head"><h2><?= e(date('l, F j, Y', strtotime($d))) ?></h2></div>
    <div class="table-wrap"><table class="t"><thead><tr><th>Employee</th><th>Department</th><th>Status</th><th>Check in</th><th>Check out</th><th>Time worked</th><th>Break</th><th></th></tr></thead><tbody>
    <?php foreach ($roster as $r): ?>
      <tr><td><a href="admin_employee.php?id=<?= $r['id'] ?>"><b><?= e($r['name']) ?></b></a> <span class="code muted"><?= e($r['emp_code']) ?></span></td><td><?= e($r['department']) ?></td>
        <td><?= $badge($r['state']) ?><?= $r['leave_name'] ? '<br><small class="muted">' . e($r['leave_name']) . '</small>' : '' ?></td>
        <td><?= e(fmt_time($r['first_in'])) ?></td><td><?= e(fmt_time($r['last_out'])) ?></td><td><b><?= $r['first_in'] ? fmt_hm((int)$r['worked_live'], false) : '—' ?></b></td><td><?= $r['first_in'] ? (int)$r['break_min'] . ' min' : '—' ?></td>
        <td><?= $d <= today() ? att_edit_button($r, $d, $r['first_in'] ? ['first_in' => $r['first_in'], 'last_out' => $r['last_out'], 'break_min' => $r['break_min']] : null) : '' ?></td></tr>
    <?php endforeach; if (!$roster): ?><tr><td colspan="8" class="empty">No employees match.</td></tr><?php endif; ?></tbody></table></div></section>
  <?php attendance_editor_dialog('admin.php?tab=attendance&date=' . $d); ?>

<?php elseif ($tab === 'employees' && $isAdmin):
    $q = trim((string)($_GET['q'] ?? ''));
    $st = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '';
    $sql = 'SELECT u.*, m.name AS mgr FROM users u LEFT JOIN users m ON m.id=u.manager_id WHERE u.role <> "admin"'; $p = [];
    if ($q !== '') { $sql .= ' AND (u.name LIKE ? OR u.emp_code LIKE ? OR u.email LIKE ? OR u.department LIKE ? OR u.designation LIKE ?)'; $l = '%' . addcslashes($q, '%_\\') . '%'; $p = [$l, $l, $l, $l, $l]; }
    if ($st) { $sql .= ' AND u.status=?'; $p[] = $st; }
    $locs = array_column(q_all('SELECT DISTINCT work_location FROM users WHERE role <> "admin" AND work_location <> "" ORDER BY work_location'), 'work_location');
    $loc = in_array($_GET['location'] ?? '', $locs, true) ? $_GET['location'] : '';
    if ($loc !== '') { $sql .= ' AND u.work_location=?'; $p[] = $loc; }
    $emps = q_all($sql . ' ORDER BY u.emp_code', $p);
    $todayState = array_column(roster_for_date(today()), 'state', 'id'); ?>
  <div class="head-actions" style="margin-bottom:18px;justify-content:space-between;width:100%">
    <form method="get" class="head-actions"><input type="hidden" name="tab" value="employees"><input class="input" name="q" value="<?= e($q) ?>" placeholder="Search name, ID, email, department" style="width:300px">
      <select class="input" name="status" style="width:140px" onchange="this.form.submit()"><option value="">All status</option><option value="active" <?= $st === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $st === 'inactive' ? 'selected' : '' ?>>Inactive</option></select>
      <select class="input" name="location" style="width:180px" onchange="this.form.submit()" aria-label="Work location"><option value="">All work locations</option><?php foreach ($locs as $l2): ?><option value="<?= e($l2) ?>" <?= $loc === $l2 ? 'selected' : '' ?>><?= e($l2) ?></option><?php endforeach; ?></select><button class="btn btn-sm">Search</button></form>
    <a class="btn btn-brand" href="admin_employee.php?new=1">+ Add employee</a></div>
  <section class="card"><div class="table-wrap"><table class="t"><thead><tr><th>ID</th><th>Employee</th><th>Department</th><th>Designation</th><th>Joined</th><th>Today</th><th>Account</th><th></th></tr></thead><tbody>
    <?php foreach ($emps as $e2): $ts2 = $e2['status'] === 'active' ? ($todayState[$e2['id']] ?? null) : null; ?>
      <tr><td class="mono" style="white-space:nowrap"><b><?= e($e2['emp_code']) ?></b></td>
        <td><div style="display:flex;gap:12px;align-items:center"><?= avatar_html($e2, 44) ?><div><a href="admin_employee.php?id=<?= $e2['id'] ?>"><b><?= e($e2['name']) ?></b></a><br><small class="muted"><?= e($e2['email']) ?></small><br><small class="muted"><?= e($e2['phone'] ?: '—') ?></small></div></div></td>
        <td><?= e($e2['department']) ?><br><small class="muted"><?= e($e2['team']) ?></small></td><td><?= e($e2['designation'] ?: '—') ?></td><td style="white-space:nowrap"><?= e(fmt_date($e2['joined_on'])) ?></td>
        <td><?= $ts2 ? $badge($ts2) : '—' ?></td><td><span class="badge <?= $e2['status'] === 'active' ? 'b-green' : 'b-slate' ?>"><?= e(ucfirst($e2['status'])) ?></span></td>
        <td style="white-space:nowrap"><a class="icon-btn" href="admin_employee.php?id=<?= $e2['id'] ?>" title="View" aria-label="View <?= e($e2['name']) ?>"><?= icon('eye', 18) ?></a> <a class="icon-btn" href="admin_employee.php?id=<?= $e2['id'] ?>" title="Edit" aria-label="Edit <?= e($e2['name']) ?>"><?= icon('edit', 18) ?></a></td></tr>
    <?php endforeach; if (!$emps): ?><tr><td colspan="8" class="empty">No employees found.</td></tr><?php endif; ?></tbody></table></div></section>

<?php elseif ($tab === 'leaves'):
    $ls = q_all("SELECT lr.*, u.name, u.emp_code, lt.name AS type_name FROM leave_requests lr JOIN users u ON u.id=lr.user_id JOIN leave_types lt ON lt.id=lr.leave_type_id
                 WHERE lr.status='pending' AND $scopeSql ORDER BY lr.applied_at", $scopeP);
    $rs = q_all("SELECT r.*, u.name, u.emp_code FROM regularizations r JOIN users u ON u.id=r.user_id WHERE r.status='pending' AND $scopeSql ORDER BY r.created_at", $scopeP);
    $hs = in_array($_GET['hstatus'] ?? '', ['approved', 'rejected', 'cancelled'], true) ? $_GET['hstatus'] : '';
    $hist = q_all("SELECT lr.*, u.name, u.emp_code, lt.name AS type_name, rv.name AS reviewer FROM leave_requests lr JOIN users u ON u.id=lr.user_id JOIN leave_types lt ON lt.id=lr.leave_type_id LEFT JOIN users rv ON rv.id=lr.reviewed_by
                   WHERE lr.status IN ('approved','rejected','cancelled') " . ($hs ? 'AND lr.status=' . db()->quote($hs) : '') . " AND $scopeSql ORDER BY lr.applied_at DESC LIMIT 60", $scopeP);
    $cls = ['approved' => 'b-green', 'rejected' => 'b-red', 'cancelled' => 'b-slate']; ?>
  <section class="card"><div class="card-head"><h2>Pending leave applications (<?= count($ls) ?>)</h2></div><div class="table-wrap"><table class="t">
    <thead><tr><th>Employee</th><th>Type</th><th>Period</th><th>Days</th><th>Reason</th><th>Decision</th></tr></thead><tbody>
    <?php foreach ($ls as $l): ?>
      <tr><td><b><?= e($l['name']) ?></b><br><small class="muted"><?= e($l['emp_code']) ?></small></td><td><?= e($l['type_name']) ?></td><td><?= e(leave_period($l)) ?></td><td><?= number_format((float)$l['days'], 1) ?></td>
        <td style="max-width:240px"><?= e($l['reason']) ?><?= $l['doc_path'] ? ' <a href="download.php?t=leave&id=' . $l['id'] . '">' . icon('clip', 14) . '</a>' : '' ?></td>
        <td><form method="post" action="actions.php" style="display:flex;gap:6px;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="do" value="admin_leave_review"><input type="hidden" name="id" value="<?= $l['id'] ?>"><input type="hidden" name="back" value="admin.php?tab=leaves">
          <input class="input" name="note" placeholder="Note (optional)" maxlength="300" style="width:150px"><button name="decision" value="approved" class="btn btn-sm btn-brand">Approve</button><button name="decision" value="rejected" class="btn btn-sm btn-danger">Reject</button></form></td></tr>
    <?php endforeach; if (!$ls): ?><tr><td colspan="6" class="empty">No pending leave requests.</td></tr><?php endif; ?></tbody></table></div></section>
  <section class="card mt"><div class="card-head"><h2>Attendance regularization requests (<?= count($rs) ?>)</h2></div><div class="table-wrap"><table class="t">
    <thead><tr><th>Employee</th><th>Date</th><th>Requested</th><th>Reason</th><th>Decision</th></tr></thead><tbody>
    <?php foreach ($rs as $r): ?>
      <tr><td><b><?= e($r['name']) ?></b><br><small class="muted"><?= e($r['emp_code']) ?></small></td><td><?= e(fmt_date($r['work_date'])) ?></td><td><?= e(substr($r['req_in'], 0, 5)) ?> – <?= e(substr($r['req_out'], 0, 5)) ?></td><td style="max-width:260px"><?= e($r['reason']) ?></td>
        <td><form method="post" action="actions.php" style="display:flex;gap:6px;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="do" value="admin_reg_review"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="back" value="admin.php?tab=leaves">
          <input class="input" name="note" placeholder="Note (optional)" maxlength="300" style="width:150px"><button name="decision" value="approved" class="btn btn-sm btn-brand">Approve</button><button name="decision" value="rejected" class="btn btn-sm btn-danger">Reject</button></form></td></tr>
    <?php endforeach; if (!$rs): ?><tr><td colspan="5" class="empty">No pending regularizations.</td></tr><?php endif; ?></tbody></table></div></section>
  <section class="card mt"><div class="card-head"><h2>Reviewed leave history</h2>
    <form method="get"><input type="hidden" name="tab" value="leaves"><select class="input" name="hstatus" onchange="this.form.submit()"><option value="">All reviewed</option><?php foreach (['approved', 'rejected', 'cancelled'] as $o): ?><option value="<?= $o ?>" <?= $hs === $o ? 'selected' : '' ?>><?= ucfirst($o) ?></option><?php endforeach; ?></select></form></div>
    <div class="table-wrap"><table class="t"><thead><tr><th>Employee</th><th>Type</th><th>Period</th><th>Days</th><th>Status</th><th>Reviewed by</th></tr></thead><tbody>
    <?php foreach ($hist as $l): ?><tr><td><b><?= e($l['name']) ?></b> <span class="code muted"><?= e($l['emp_code']) ?></span></td><td><?= e($l['type_name']) ?></td><td><?= e(leave_period($l)) ?></td><td><?= number_format((float)$l['days'], 1) ?></td>
      <td><span class="badge <?= $cls[$l['status']] ?>"><?= e(ucfirst($l['status'])) ?></span></td><td><?= e($l['reviewer'] ?? '—') ?><?= $l['review_note'] ? '<br><small class="muted">' . e($l['review_note']) . '</small>' : '' ?></td></tr>
    <?php endforeach; if (!$hist): ?><tr><td colspan="6" class="empty">Nothing here yet.</td></tr><?php endif; ?></tbody></table></div></section>

<?php elseif ($tab === 'logs'):
    $fe = (int)($_GET['emp'] ?? 0);
    $fs = in_array($_GET['status'] ?? '', ['submitted', 'approved', 'rejected'], true) ? $_GET['status'] : '';
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-14 days'));
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : today();
    $sql = "SELECT w.*, u.name, u.emp_code, u.designation FROM work_logs w JOIN users u ON u.id=w.user_id WHERE $scopeSql AND w.status <> 'draft' AND w.log_date BETWEEN ? AND ?"; $p = array_merge($scopeP, [$from, $to]);
    if ($fe) { $sql .= ' AND w.user_id=?'; $p[] = $fe; }
    if ($fs) { $sql .= ' AND w.status=?'; $p[] = $fs; }
    $ws = q_all($sql . ' ORDER BY w.log_date DESC, u.emp_code LIMIT 100', $p);
    $people = q_all("SELECT u.id, u.name, u.emp_code FROM users u WHERE $scopeSql ORDER BY u.emp_code", $scopeP);
    $cls = ['submitted' => 'b-amber', 'approved' => 'b-green', 'rejected' => 'b-red']; ?>
  <form method="get" class="head-actions" style="margin-bottom:18px"><input type="hidden" name="tab" value="logs">
    <select class="input" name="emp" style="width:210px"><option value="0">All employees</option><?php foreach ($people as $pp): ?><option value="<?= $pp['id'] ?>" <?= $fe === (int)$pp['id'] ? 'selected' : '' ?>><?= e($pp['emp_code'] . ' · ' . $pp['name']) ?></option><?php endforeach; ?></select>
    <select class="input" name="status" style="width:150px"><option value="">All statuses</option><?php foreach (array_keys($cls) as $o): ?><option value="<?= $o ?>" <?= $fs === $o ? 'selected' : '' ?>><?= ucfirst($o) ?></option><?php endforeach; ?></select>
    <input class="input" type="date" name="from" value="<?= e($from) ?>" style="width:160px" aria-label="From"><input class="input" type="date" name="to" value="<?= e($to) ?>" style="width:160px" aria-label="To"><button class="btn btn-brand btn-sm">Filter</button></form>
  <section class="card"><div class="card-head"><h2>Work logs (<?= count($ws) ?>)</h2></div>
    <?php foreach ($ws as $w): ?>
      <div class="ann"><div class="meta"><span><?= e($w['name']) ?> · <?= e($w['emp_code']) ?></span><span><?= e(fmt_date($w['log_date'], 'D, M j Y')) ?> · <span class="badge <?= $cls[$w['status']] ?>"><?= e(ucfirst($w['status'])) ?></span></span></div>
        <h3><?= e($w['tag'] ?: 'Daily log') ?></h3><p style="white-space:pre-line"><?= e($w['content']) ?></p>
        <?php if ($w['attach_path']): ?><p><a href="download.php?t=worklog&id=<?= $w['id'] ?>"><?= icon('clip', 14) ?> <?= e($w['attach_name']) ?></a></p><?php endif; ?>
      </div>
    <?php endforeach; if (!$ws): ?><p class="empty">No work logs match these filters.</p><?php endif; ?></section>

<?php elseif ($tab === 'holidays' && $isAdmin):
    $yr = (int)($_GET['year'] ?? date('Y'));
    $hl = q_all('SELECT * FROM holidays WHERE YEAR(holiday_date)=? ORDER BY holiday_date', [$yr]); ?>
  <div class="head-actions" style="margin-bottom:18px;justify-content:flex-end;width:100%"><button type="button" class="btn btn-brand" data-open="#holAddDlg">+ Add holiday</button></div>
  <dialog id="holAddDlg">
    <form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="do" value="admin_holiday_add"><input type="hidden" name="back" value="admin.php?tab=holidays&year=<?= $yr ?>">
      <div class="modal-h"><h2>Add holiday</h2><button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
      <div class="modal-b">
        <div class="field"><label class="f">Date</label><input class="input" type="date" name="holiday_date" required></div>
        <div class="field"><label class="f">Name</label><input class="input" name="name" required maxlength="120"></div>
        <div class="field"><label class="f">Note</label><input class="input" name="note" value="Corporate Paid" maxlength="150"></div>
        <button class="btn btn-brand btn-block">Add holiday</button>
      </div>
    </form>
  </dialog>
  <div>
    <section class="card"><div class="card-head"><h2>Company holidays <?= $yr ?></h2><div class="seg"><a href="admin.php?tab=holidays&year=<?= $yr - 1 ?>">‹ <?= $yr - 1 ?></a><a href="admin.php?tab=holidays&year=<?= $yr + 1 ?>"><?= $yr + 1 ?> ›</a></div></div>
      <p class="muted" style="margin-bottom:12px">Changes appear immediately on every employee's calendar, dashboard and leave-day calculation.</p>
      <?php foreach ($hl as $h): ?>
        <form method="post" action="actions.php" class="hol-edit"><?= csrf_field() ?><input type="hidden" name="do" value="admin_holiday_edit"><input type="hidden" name="id" value="<?= $h['id'] ?>"><input type="hidden" name="back" value="admin.php?tab=holidays&year=<?= $yr ?>">
          <input class="input" type="date" name="holiday_date" value="<?= e($h['holiday_date']) ?>" required><input class="input hol-day" value="<?= e(date('l', strtotime($h['holiday_date']))) ?>" readonly tabindex="-1" placeholder="Weekday" aria-label="Weekday"><input class="input hol-name" name="name" value="<?= e($h['name']) ?>" required maxlength="120"><input type="hidden" name="note" value="<?= e($h['note']) ?>">
          <button type="button" class="icon-btn" data-hol-note title="Note" aria-label="View note"><?= icon('log', 18) ?></button><button class="btn btn-sm btn-save" title="Save" aria-label="Save holiday"><?= icon('save', 16) ?></button><button class="btn btn-sm btn-del" name="do" value="admin_holiday_delete" formnovalidate title="Delete" aria-label="Delete holiday" onclick="return confirm('Remove this holiday?')"><?= icon('trash', 16) ?></button></form>
      <?php endforeach; if (!$hl): ?><p class="empty">No holidays for <?= $yr ?>.</p><?php endif; ?>
      <dialog id="holNoteDlg">
        <div class="modal-h"><h2 id="holNoteTitle">Note</h2><button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
        <div class="modal-b"><input class="input" id="holNoteInput" maxlength="150" placeholder="No note"><div class="help" style="margin-top:8px">Edit here, then press Save on the holiday row to keep the change.</div>
          <button type="button" class="btn btn-brand" id="holNoteOk" style="margin-top:14px">Done</button></div>
      </dialog>
      <script>
      (function () {
        var d = document.getElementById('holNoteDlg'), inp = document.getElementById('holNoteInput'), cur = null;
        document.addEventListener('click', function (ev) {
          var b = ev.target.closest('[data-hol-note]'); if (!b) return;
          cur = b.closest('form').querySelector('input[name=note]');
          document.getElementById('holNoteTitle').textContent = 'Note · ' + b.closest('form').querySelector('input[name=name]').value;
          inp.value = cur.value; d.showModal();
        });
        document.addEventListener('change', function (ev) {
          var i = ev.target; if (!i.matches || !i.matches('.hol-edit input[type=date]')) return;
          var s = i.closest('form').querySelector('.hol-day'), dt = new Date(i.value + 'T00:00:00');
          if (s) s.value = isNaN(dt) ? '' : dt.toLocaleDateString('en-US', { weekday: 'long' });
        });
        document.getElementById('holNoteOk').addEventListener('click', function () { if (cur) cur.value = inp.value; d.close(); });
      })();
      </script></section>
  </div>

<?php elseif ($tab === 'announce' && $isAdmin):
    $as = q_all('SELECT * FROM announcements ORDER BY posted_on DESC, id DESC LIMIT 20'); ?>
  <div class="grid g-3-2" style="align-items:start">
    <section class="card"><div class="card-head"><h2>Announcements</h2></div>
      <?php foreach ($as as $a): ?><article class="ann"><div class="meta"><span><?= e($a['dept']) ?> · <?= e(fmt_date($a['posted_on'], 'M d')) ?></span>
        <form method="post" action="actions.php" data-confirm="Delete this announcement?"><?= csrf_field() ?><input type="hidden" name="do" value="admin_announce_delete"><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="back" value="admin.php?tab=announce"><button class="link-btn" style="color:var(--red)">Delete</button></form></div><h3><?= e($a['title']) ?></h3><p><?= e($a['body']) ?></p></article><?php endforeach; ?></section>
    <section class="card"><div class="card-head"><h2>Post announcement</h2></div><form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="do" value="admin_announce_add"><input type="hidden" name="back" value="admin.php?tab=announce">
      <div class="field"><label class="f">Department</label><input class="input" name="dept" required value="HR Operations" maxlength="60"></div><div class="field"><label class="f">Title</label><input class="input" name="title" required maxlength="200"></div>
      <div class="field"><label class="f">Message</label><textarea class="input" name="body" required maxlength="1000"></textarea></div><button class="btn btn-brand">Publish</button></form></section>
  </div>

<?php elseif ($tab === 'payroll' && $isAdmin): ?>
  <section class="card" style="max-width:560px"><div class="card-head"><h2>Generate payslips</h2></div>
    <p class="muted" style="margin-bottom:16px">Builds payslips for all active employees from annual salary, attendance (loss of pay for unexcused absences and unpaid leave) and overtime. Re-running a month recalculates it.</p>
    <form method="post" action="actions.php" data-confirm="Generate payslips for this month?"><?= csrf_field() ?><input type="hidden" name="do" value="admin_payroll_run"><input type="hidden" name="back" value="admin.php?tab=payroll">
      <div class="field"><label class="f">Month</label><input class="input" type="month" name="month" required value="<?= date('Y-m', strtotime('first day of last month')) ?>" max="<?= date('Y-m') ?>"></div>
      <button class="btn btn-brand">Run payroll</button></form></section>
<?php endif; ?>
<?php render_footer(); ?>
