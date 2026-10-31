<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_admin();

$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($year < 2020 || $year > (int)date('Y') + 1) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('n');
$ym = sprintf('%04d-%02d', $year, $month);
$first = "$ym-01";
$daysInMonth = (int)date('t', strtotime($first));
$locations = array_column(q_all('SELECT DISTINCT work_location FROM users WHERE role<>"admin" AND work_location<>"" ORDER BY work_location'), 'work_location');
$loc = (string)($_GET['loc'] ?? '');
if ($loc !== '' && !in_array($loc, $locations, true)) $loc = '';

// employees who had joined by the end of the chosen month
$sql = 'SELECT * FROM users WHERE role<>"admin" AND status="active" AND joined_on <= ?';
$args = [date('Y-m-t', strtotime($first))];
if ($loc !== '') { $sql .= ' AND work_location=?'; $args[] = $loc; }
$rows = [];
foreach (q_all($sql . ' ORDER BY emp_code', $args) as $emp) {
    $sum = month_summary(month_overview($emp, $ym));
    $wo = weekoff_balance($emp, $ym);
    $counted = $sum['present'] + $sum['absent'] + $sum['weekoff'] + $sum['holiday'] + $sum['weekend'] + (int)ceil($sum['leave']);
    $rows[] = $emp + ['present' => $sum['present'], 'absent' => $sum['absent'], 'weekoff' => $sum['weekoff'], 'holiday' => $sum['holiday'] + $sum['weekend'],
        'leave' => (int)ceil($sum['leave']), 'other' => max(0, $daysInMonth - $counted), 'carry_fwd' => $wo['left']];
}
$tot = fn(string $k): int => array_sum(array_column($rows, $k));
$monthName = date('F', strtotime($first));

render_header('Monthly Attendance · Admin', 'attendance', $user);
?>
<div class="no-print">
  <div style="margin-bottom:14px"><a href="admin.php?tab=attendance">← Attendance</a></div>
  <form class="head-actions" method="get" style="margin-bottom:18px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <select class="input" name="month" style="width:150px" aria-label="Month"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>"<?= $m === $month ? ' selected' : '' ?>><?= e(date('F', mktime(0, 0, 0, $m, 1))) ?></option><?php endfor; ?></select>
    <select class="input" name="year" style="width:110px" aria-label="Year"><?php for ($y = (int)date('Y') + 1; $y >= 2020; $y--): ?><option<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option><?php endfor; ?></select>
    <select class="input" name="loc" style="width:220px" aria-label="Office location"><option value="">All office locations</option><?php foreach ($locations as $l): ?><option<?= $l === $loc ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <button class="btn btn-brand">Show</button>
    <button type="button" class="btn btn-dark" onclick="window.print()"><?= icon('print', 16) ?> Print / Save PDF</button>
  </form>
</div>

<section class="card att-sheet">
  <div class="att-sheet-head">
    <img src="assets/img/logo.png" alt="Liberty Foundation - since 2008">
    <h1>Attendance Sheet</h1>
    <div class="att-sheet-meta"><b><?= e($monthName . ' ' . $year) ?></b><span><?= e($loc !== '' ? $loc : 'All locations') ?></span><span><?= $daysInMonth ?> days in month</span></div>
  </div>
  <div class="table-wrap"><table class="t att-table">
    <thead><tr><th>#</th><th>Employee Name</th><th>Code</th><th>Department</th><th>Present</th><th>Absent</th><th>Week Off</th><th>Holiday</th><th>Leave</th><th>Upcoming / N.A.</th><th>Total Days</th><th>Week Off Carry Fwd</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td><td><b><?= e($r['name']) ?></b></td><td class="code"><?= e($r['emp_code']) ?></td><td><?= e($r['department']) ?></td>
        <td><?= $r['present'] ?></td><td><?= $r['absent'] ?></td><td><?= $r['weekoff'] ?></td><td><?= $r['holiday'] ?></td><td><?= $r['leave'] ?></td><td><?= $r['other'] ?></td><td><b><?= $daysInMonth ?></b></td><td><?= $r['carry_fwd'] ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="12" class="empty">No employees for this month and location.</td></tr><?php else: ?>
    <?php endif; ?>
    </tbody></table></div>
  <?php $nextMonth = date('F Y', strtotime($first . ' +1 month')); $perMonth = (int)cfg('weekoff_per_month'); $carried = array_filter($rows, fn($r) => $r['carry_fwd'] > 0); ?>
  <?php if ($carried): ?>
  <div class="att-carry"><h3>Week Off Carry Forward to <?= e($nextMonth) ?></h3>
    <table class="t att-table"><thead><tr><th>#</th><th>Employee Name</th><th>Code</th><th>Unused in <?= e($monthName) ?> (carried)</th><th>Total Week Offs in <?= e($nextMonth) ?></th></tr></thead><tbody>
    <?php foreach (array_values($carried) as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td><td><b><?= e($r['name']) ?></b></td><td class="code"><?= e($r['emp_code']) ?></td><td><?= $r['carry_fwd'] ?></td><td><b><?= $perMonth + $r['carry_fwd'] ?></b> <span class="muted">(<?= $perMonth ?> + <?= $r['carry_fwd'] ?>)</span></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
  <p class="att-note">Week Off Carry Fwd = unused week offs added to next month's balance. Generated <?= e(date('d M Y, h:i A')) ?> by Liberty Office.</p>
</section>
<?php render_footer(); ?>
