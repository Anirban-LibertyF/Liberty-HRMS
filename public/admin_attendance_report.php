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
$sql = 'SELECT u.*, s.name AS shift_name, s.start_time, s.end_time, s.required_min, s.break_allow_min, s.grace_min FROM users u JOIN shifts s ON s.id=u.shift_id WHERE u.role<>"admin" AND u.status="active" AND u.joined_on <= ?';
$args = [date('Y-m-t', strtotime($first))];
if ($loc !== '') { $sql .= ' AND u.work_location=?'; $args[] = $loc; }
$paid = [];
foreach (q_all('SELECT user_id, paid_at FROM payout_paid WHERE pay_month=?', [$ym]) as $pp) $paid[(int)$pp['user_id']] = $pp['paid_at'];
$rows = [];
foreach (q_all($sql . ' ORDER BY u.emp_code', $args) as $emp) {
    $sum = month_summary(month_overview($emp, $ym));
    $wo = weekoff_balance($emp, $ym);
    $counted = $sum['present'] + $sum['absent'] + $sum['weekoff'] + $sum['holiday'] + $sum['weekend'] + (int)ceil($sum['leave']);
    $pc = payroll_calc($emp, $ym);
    $rows[] = $emp + ['ot_min' => $pc['ot_min'], 'ot_payouts' => $pc['ot_payouts'], 'ot_pay' => $pc['ot_pay'], 'day_pay' => $pc['daily'], 'att_pay' => $pc['att_pay'], 'net' => $pc['net'], 'paid' => $paid[(int)$emp['id']] ?? null, 'present' => $sum['present'], 'absent' => $sum['absent'], 'weekoff' => $sum['weekoff'], 'holiday' => $sum['holiday'] + $sum['weekend'],
        'leave' => (int)ceil($sum['leave']), 'carry_fwd' => $wo['left']];
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
  <div class="att-split">
  <table class="t att-table att-names no-print" aria-hidden="true">
    <thead><tr><th class="n-no">#</th><th>Employee Name</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?><tr><td class="n-no"><?= $i + 1 ?></td><td><b><?= e($r['name']) ?></b></td></tr><?php endforeach; ?>
    <?php if ($rows): ?><tr class="att-total"><td class="n-no"></td><td>Total payout</td></tr><?php endif; ?>
    </tbody></table>
  <div class="table-wrap"><table class="t att-table att-main">
    <thead><tr><th>#</th><th>Employee Name</th><th>Code</th><th>Department</th><th>Present</th><th>Absent</th><th>Week Off</th><th>Holiday</th><th>Leave</th><th>Overtime (OT)</th><th>Week Off Carry Fwd</th><th class="no-print">Attendance Pay</th><th class="no-print">Extra (OT) Pay</th><th class="no-print">Total Payout</th><th class="no-print">Paid</th><th class="no-print">Paid On</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr><td data-label="#"><?= $i + 1 ?></td><td data-label="Employee"><b><?= e($r['name']) ?></b></td><td data-label="Code" class="code"><?= e($r['emp_code']) ?></td><td data-label="Department"><?= e($r['department']) ?></td>
        <td data-label="Present"><?= $r['present'] ?></td><td data-label="Absent"><?= $r['absent'] ?></td><td data-label="Week Off"><?= $r['weekoff'] ?></td><td data-label="Holiday"><?= $r['holiday'] ?></td><td data-label="Leave"><?= $r['leave'] ?></td><td data-label="Overtime (OT)"><b><?= $r['ot_min'] ? e(fmt_hm((int)$r['ot_min'], false)) : '—' ?></b></td><td data-label="Week Off Carry Fwd"><?= $r['carry_fwd'] ?></td>
        <td data-label="Attendance Pay" class="no-print"><?= e(money($r['att_pay'])) ?></td>
        <td data-label="Extra (OT) Pay" class="no-print"><?php if ($r['ot_payouts']): ?><?= $r['ot_payouts'] ?> &times; <?= e(money($r['day_pay'])) ?><br><b><?= e(money($r['ot_pay'])) ?></b><?php else: ?>—<?php endif; ?></td>
        <td data-label="Total Payout" class="no-print"><b><?= e(money($r['net'])) ?></b></td>
        <td data-label="Paid" class="no-print"><form method="post" action="actions.php" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="admin_payout_paid"><input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="month" value="<?= e($ym) ?>"><input type="hidden" name="back" value="<?= e('admin_attendance_report.php?month=' . $month . '&year=' . $year . '&loc=' . urlencode($loc)) ?>">
          <?php if ($r['paid']): ?><button class="btn btn-sm btn-paid" title="Paid on <?= e(fmt_date($r['paid'])) ?> - click to undo" data-confirm="Mark <?= e($r['name']) ?> as unpaid?"><?= icon('check', 14) ?> Paid</button>
          <?php else: ?><input type="hidden" name="paid" value="1"><button class="btn btn-sm" data-confirm="Mark <?= e($r['name']) ?> as paid (<?= e(money($r['net'])) ?>)?">Mark Paid</button><?php endif; ?></form></td>
        <td class="no-print" data-label="Paid On"><?php if ($r['paid']): ?>
          <form method="post" action="actions.php" class="inline paid-date"><?= csrf_field() ?><input type="hidden" name="do" value="admin_payout_date"><input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="month" value="<?= e($ym) ?>"><input type="hidden" name="back" value="<?= e('admin_attendance_report.php?month=' . $month . '&year=' . $year . '&loc=' . urlencode($loc)) ?>">
            <input class="input" type="date" name="paid_date" value="<?= e(substr($r['paid'], 0, 10)) ?>" max="<?= today() ?>" required title="Date paid - change it to edit" onchange="this.form.requestSubmit()"></form>
          <?php else: ?>—<?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="16" class="empty">No employees for this month and location.</td></tr><?php else: ?>
      <tr class="att-total no-print"><td colspan="13" class="att-total-lbl" style="text-align:right"><b>Total payout (<?= e($monthName . ' ' . $year) ?>)</b></td><td><b><?= e(money(array_sum(array_column($rows, 'net')))) ?></b></td><td class="no-print"></td><td class="no-print"></td></tr>
    <?php endif; ?>
    </tbody></table></div>
  </div>
  <?php $nextMonth = date('F Y', strtotime($first . ' +1 month')); $perMonth = (int)cfg('weekoff_per_month'); $carried = array_filter($rows, fn($r) => $r['carry_fwd'] > 0); ?>
  <?php if ($carried): ?>
  <div class="att-carry"><h3>Week Off Carry Forward to <?= e($nextMonth) ?></h3>
    <div class="table-wrap"><table class="t att-table"><thead><tr><th>#</th><th>Employee Name</th><th>Code</th><th>Unused in <?= e($monthName) ?> (carried)</th><th>Total Week Offs in <?= e($nextMonth) ?></th></tr></thead><tbody>
    <?php foreach (array_values($carried) as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td><td><b><?= e($r['name']) ?></b></td><td class="code"><?= e($r['emp_code']) ?></td><td><?= $r['carry_fwd'] ?></td><td><b><?= $perMonth + $r['carry_fwd'] ?></b> <span class="muted">(<?= $perMonth ?> + <?= $r['carry_fwd'] ?>)</span></td></tr>
    <?php endforeach; ?></tbody></table></div></div>
  <?php endif; ?>
  <p class="att-note">Overtime (OT) = total extra time worked beyond the shift. <span class="no-print">Every 7h of OT earns 1 extra day's basic pay (7h = 1, 14h = 2). Total Payout = attendance-based salary + extra OT pay. </span>Week Off Carry Fwd = unused week offs added to next month's balance. Generated <?= e(date('d M Y, h:i A')) ?> by Liberty Office.</p>
</section>
<?php render_footer(); ?>
