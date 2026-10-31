<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_admin();

$isNew = !empty($_GET['new']);
$emp = null;
if (!$isNew) {
    $emp = q_one('SELECT * FROM users WHERE id=? AND role <> "admin"', [(int)($_GET['id'] ?? 0)]);
    if (!$emp) { flash('error', 'Employee not found.'); redirect('admin.php?tab=employees'); }
}
$v = fn(string $k, $d = '') => e($emp[$k] ?? $_SESSION['form_old'][$k] ?? $d);
$managers = q_all('SELECT id, name, emp_code FROM users WHERE role IN ("manager") AND status="active" ' . ($emp ? 'AND id<>' . (int)$emp['id'] : '') . ' ORDER BY name');
$shifts = q_all('SELECT * FROM shifts');
$sel = fn(string $k, string $val) => (($emp[$k] ?? $_SESSION['form_old'][$k] ?? '') == $val) ? 'selected' : '';

if ($emp) {
    $ym = preg_match('/^\d{4}-\d{2}$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
    $days = month_overview($emp, $ym);
    $sum = month_summary($days);
    $bal = leave_balances((int)$emp['id'], (int)date('Y'));
    $leaves = q_all('SELECT lr.*, lt.name AS type_name FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id WHERE lr.user_id=? ORDER BY lr.applied_at DESC LIMIT 8', [$emp['id']]);
    $logs = q_all('SELECT * FROM work_logs WHERE user_id=? AND status <> "draft" ORDER BY log_date DESC LIMIT 8', [$emp['id']]);
    $mgr = $emp['manager_id'] ? q_val('SELECT name FROM users WHERE id=?', [$emp['manager_id']]) : null;
}
$typeLabel = ['present' => ['Present', 'b-green'], 'absent' => ['Absent', 'b-red'], 'leave' => ['Leave', 'b-amber'], 'holiday' => ['Holiday', 'b-slate'], 'weekend' => ['Weekend', 'b-slate'], 'weekoff' => ['Week Off', 'b-slate'], 'upcoming' => ['—', 'b-slate'], 'none' => ['—', 'b-slate']];

render_header($isNew ? 'Add employee' : $emp['name'], 'employees', $user);
page_title($isNew ? 'Add employee' : $emp['name'], $isNew ? 'The employee ID (LOE-### with 3 random digits) is generated automatically when the account is created.'
    : e($emp['emp_code']) . ' · ' . e($emp['designation']) . ' · ' . e($emp['department']),
    '<a class="btn" href="admin.php?tab=employees">← All employees</a>');
?>
<form method="post" action="actions.php" class="card" autocomplete="off">
  <?= csrf_field() ?><input type="hidden" name="do" value="<?= $isNew ? 'admin_user_create' : 'admin_user_update' ?>"><?php if ($emp): ?><input type="hidden" name="id" value="<?= $emp['id'] ?>"><?php endif; ?>
  <div class="card-head"><h2><?= icon('user', 20) ?> <?= $isNew ? 'Employee details' : 'Edit details' ?></h2>
    <?php if ($emp): ?><span class="badge <?= $emp['status'] === 'active' ? 'b-green' : 'b-slate' ?>"><?= e(ucfirst($emp['status'])) ?></span><?php endif; ?></div>

  <div class="eyebrow" style="margin-bottom:10px">Personal</div>
  <div class="grid g-3">
    <div class="field"><label class="f">Full name <span class="req">*</span></label><input class="input" name="name" required maxlength="120" value="<?= $v('name') ?>"></div>
    <div class="field"><label class="f">Date of birth</label><input class="input" type="date" name="dob" max="<?= date('Y-m-d', strtotime('-1 day')) ?>" value="<?= $v('dob') ?>"></div>
    <div class="field"><label class="f">Gender</label><select class="input" name="gender"><option value="">—</option><?php foreach (['Male', 'Female', 'Other'] as $o): ?><option <?= $sel('gender', $o) ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="field"><label class="f">Blood group</label><select class="input" name="blood_group"><option value="">—</option><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $o): ?><option <?= $sel('blood_group', $o) ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="field"><label class="f">Phone</label><input class="input" name="phone" maxlength="30" value="<?= $v('phone') ?>"></div>
    <div class="field"><label class="f">Address</label><input class="input" name="address" maxlength="300" value="<?= $v('address') ?>"></div>
    <div class="field"><label class="f">Emergency contact name</label><input class="input" name="emergency_name" maxlength="120" value="<?= $v('emergency_name') ?>"></div>
    <div class="field"><label class="f">Emergency contact phone</label><input class="input" name="emergency_phone" maxlength="30" value="<?= $v('emergency_phone') ?>"></div>
  </div>

  <div class="eyebrow" style="margin:6px 0 10px">Employment</div>
  <div class="grid g-3">
    <div class="field"><label class="f">Employee ID</label><input class="input mono" value="<?= $emp ? e($emp['emp_code']) : 'Auto-generated (LOE-###)' ?>" disabled></div>
    <div class="field"><label class="f">Department <span class="req">*</span></label><input class="input" name="department" required maxlength="80" value="<?= $v('department') ?>"></div>
    <div class="field"><label class="f">Team</label><input class="input" name="team" maxlength="80" value="<?= $v('team') ?>"></div>
    <div class="field"><label class="f">Designation <span class="req">*</span></label><input class="input" name="designation" required maxlength="120" value="<?= $v('designation') ?>"></div>
    <div class="field"><label class="f">Role</label><select class="input" name="role"><option value="employee" <?= $sel('role', 'employee') ?>>Employee</option><option value="manager" <?= $sel('role', 'manager') ?>>Manager (can approve their team)</option></select></div>
    <div class="field"><label class="f">Reporting manager</label><select class="input" name="manager_id"><option value="">— none (admin approves) —</option><?php foreach ($managers as $m): ?><option value="<?= $m['id'] ?>" <?= $sel('manager_id', (string)$m['id']) ?>><?= e($m['emp_code'] . ' · ' . $m['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label class="f">Employment type</label><select class="input" name="employment_type"><?php foreach (['Full-time', 'Part-time', 'Contract', 'Intern'] as $o): ?><option <?= $sel('employment_type', $o) ?>><?= $o ?></option><?php endforeach; ?></select></div>
    <div class="field"><label class="f">Work location</label><input class="input" name="work_location" maxlength="80" value="<?= $v('work_location', 'Headquarters') ?>"></div>
    <div class="field"><label class="f">Shift</label><select class="input" name="shift_id"><?php foreach ($shifts as $s): ?><option value="<?= $s['id'] ?>" <?= $sel('shift_id', (string)$s['id']) ?>><?= e($s['name']) ?> (<?= e(substr($s['start_time'], 0, 5)) ?>–<?= e(substr($s['end_time'], 0, 5)) ?>)</option><?php endforeach; ?></select></div>
    <div class="field"><label class="f">Date of joining <span class="req">*</span></label><input class="input" type="date" name="joined_on" required value="<?= $v('joined_on', today()) ?>"></div>
    <div class="field"><label class="f">Monthly salary (₹)</label><input class="input" type="number" id="monthlySalary" name="monthly_salary" min="0" step="0.01" required value="<?= e(isset($emp['base_salary']) ? rtrim(rtrim(number_format((float)$emp['base_salary'] / 12, 2, '.', ''), '0'), '.') : (isset($_SESSION['form_old']['base_salary']) ? rtrim(rtrim(number_format((float)$_SESSION['form_old']['base_salary'] / 12, 2, '.', ''), '0'), '.') : '50000')) ?>">
      <small class="muted">Annual salary (auto): <b id="annualSalary">—</b></small></div>
    <script>(function(){var m=document.getElementById("monthlySalary"),a=document.getElementById("annualSalary");function f(){var v=parseFloat(m.value);a.textContent=isNaN(v)?"—":"₹"+(v*12).toLocaleString("en-IN",{maximumFractionDigits:2})}m.addEventListener("input",f);f()})();</script>
  </div>

  <div class="eyebrow" style="margin:6px 0 10px">Sign-in</div>
  <div class="grid g-3">
    <div class="field"><label class="f">Corporate email <span class="req">*</span></label><input class="input" type="email" name="email" required maxlength="160" value="<?= $v('email') ?>"></div>
    <div class="field"><label class="f"><?= $isNew ? 'Password' : 'New password' ?> <?= $isNew ? '<span class="req">*</span>' : '(leave blank to keep)' ?></label>
      <div style="display:flex;gap:8px"><input class="input" type="text" id="pw" name="password" minlength="8" maxlength="72" <?= $isNew ? 'required' : '' ?> autocomplete="new-password" placeholder="Min 8 chars, letters + numbers"><button type="button" class="btn btn-sm" id="genPw">Generate</button></div></div>
  </div>
  <div class="btn-row"><button class="btn btn-brand"><?= $isNew ? 'Create employee' : 'Save changes' ?></button><a class="btn" href="admin.php?tab=employees">Cancel</a></div>
</form>
<script>
document.getElementById('genPw').addEventListener('click', function () {
  var c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789', a = new Uint32Array(10), p = '';
  crypto.getRandomValues(a); a.forEach(function (n) { p += c[n % c.length]; });
  document.getElementById('pw').value = p + '@7';
});
</script>

<?php if ($emp): ?>
<div class="head-actions mt no-print">
  <form method="post" action="actions.php" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="admin_user_toggle"><input type="hidden" name="id" value="<?= $emp['id'] ?>"><input type="hidden" name="back" value="admin_employee.php?id=<?= $emp['id'] ?>">
    <button class="btn <?= $emp['status'] === 'active' ? 'btn-danger' : 'btn-brand' ?>"><?= $emp['status'] === 'active' ? 'Deactivate account' : 'Reactivate account' ?></button></form>
  <form method="post" action="actions.php" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="admin_offerletter_regen"><input type="hidden" name="id" value="<?= $emp['id'] ?>"><input type="hidden" name="back" value="admin_employee.php?id=<?= $emp['id'] ?>"><button class="btn">Regenerate offer letter</button></form>
  <form method="post" action="actions.php" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="admin_joiningletter_regen"><input type="hidden" name="id" value="<?= $emp['id'] ?>"><input type="hidden" name="back" value="admin_employee.php?id=<?= $emp['id'] ?>"><button class="btn">Regenerate joining letter</button></form>
  <form method="post" action="actions.php" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="admin_ndaletter_regen"><input type="hidden" name="id" value="<?= $emp['id'] ?>"><input type="hidden" name="back" value="admin_employee.php?id=<?= $emp['id'] ?>"><button class="btn">Regenerate NDA</button></form>
  <?php $ndaSigned = q_one('SELECT id, uploaded_at FROM documents WHERE user_id=? AND category="NDA Signed" ORDER BY id DESC LIMIT 1', [$emp['id']]); ?>
  <span class="muted">NDA: <?= $ndaSigned ? '<a href="download.php?t=doc&id=' . $ndaSigned['id'] . '" target="_blank" rel="noopener">signed copy received ' . e(fmt_date($ndaSigned['uploaded_at'])) . '</a>' : 'signed copy pending' ?></span>
  <?php if ($emp['failed_attempts'] >= 5): ?><form method="post" action="actions.php" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="admin_user_unlock"><input type="hidden" name="id" value="<?= $emp['id'] ?>"><input type="hidden" name="back" value="admin_employee.php?id=<?= $emp['id'] ?>"><button class="btn">Unlock sign-in</button></form><?php endif; ?>
  <span class="muted">Last sign-in: <?= e(fmt_date($emp['last_login'], 'M d, Y h:i A')) ?> · Manager: <?= e($mgr ?? '—') ?></span></div>

<div class="grid g-4 mt">
  <div class="card stat"><div class="eyebrow">Present (<?= e(date('M Y', strtotime("$ym-01"))) ?>)</div><div class="big"><?= $sum['present'] ?> <small>days</small></div></div>
  <div class="card stat"><div class="eyebrow">Absent</div><div class="big"><?= $sum['absent'] ?> <small>days</small></div></div>
  <div class="card stat"><div class="eyebrow">Average hours / day</div><div class="big"><?= $sum['avg_hours'] ?> <small>hrs</small></div></div>
  <div class="card stat"><div class="eyebrow">Attendance rate</div><div class="big"><?= $sum['rate'] ?>%</div></div>
</div>

<section class="card mt"><div class="card-head"><h2><?= icon('calendar', 20) ?> Attendance — <?= e(date('F Y', strtotime("$ym-01"))) ?></h2><?= att_edit_button($emp, today(), null, '+ Add / edit a date') ?>
  <div class="seg"><a href="admin_employee.php?id=<?= $emp['id'] ?>&m=<?= date('Y-m', strtotime("$ym-01 -1 month")) ?>">‹ Prev</a><a href="admin_employee.php?id=<?= $emp['id'] ?>&m=<?= date('Y-m', strtotime("$ym-01 +1 month")) ?>">Next ›</a></div></div>
  <div class="table-wrap"><table class="t"><thead><tr><th>Date</th><th>Status</th><th>In</th><th>Out</th><th>Worked</th><th>Break</th><th>Overtime</th><th></th></tr></thead><tbody>
  <?php foreach (array_reverse($days) as $d => $r): if ($r['type'] === 'none' || $r['type'] === 'upcoming') continue; $a = $r['att']; [$lbl, $cl] = $typeLabel[$r['type']]; ?>
    <tr><td><?= e(date('D, M j', strtotime($d))) ?></td><td><span class="badge <?= $cl ?>"><?= e($r['type'] === 'holiday' ? $r['holiday']['name'] : ($r['type'] === 'leave' ? $r['leave']['type_name'] : $lbl)) ?></span></td>
      <td><?= $a ? e(fmt_time($a['first_in'])) : '—' ?></td><td><?= $a ? e(fmt_time($a['last_out'])) : '—' ?></td><td><?= $a ? fmt_hm((int)$a['worked_min'], false) : '—' ?></td><td><?= $a ? (int)$a['break_min'] . ' min' : '—' ?></td><td><?= $a && $a['overtime_min'] ? (int)$a['overtime_min'] . ' min' : '—' ?></td><td><?= $d <= today() ? att_edit_button($emp, $d, $a) : '' ?></td></tr>
  <?php endforeach; ?></tbody></table></div></section>
<?php attendance_editor_dialog('admin_employee.php?id=' . $emp['id'] . '&m=' . $ym); ?>

<div class="grid g-2 mt" style="align-items:start">
  <section class="card"><div class="card-head"><h2><?= icon('leave', 20) ?> Leave</h2></div>
    <?php foreach ($bal as $b): ?><div class="kv"><span><?= e($b['name']) ?></span><b><?= (float)$b['quota'] > 0 ? rtrim(rtrim(number_format($b['remaining'], 1), '0'), '.') . ' / ' . rtrim(rtrim(number_format((float)$b['quota'], 1), '0'), '.') . ' left' : rtrim(rtrim(number_format($b['used'], 1), '0'), '.') . ' taken' ?></b></div><?php endforeach; ?>
    <div class="table-wrap" style="margin-top:12px"><table class="t"><tbody><?php foreach ($leaves as $l): ?><tr><td><?= e($l['type_name']) ?></td><td><?= e(leave_period($l)) ?></td><td><span class="badge <?= ['approved' => 'b-green', 'pending' => 'b-amber', 'rejected' => 'b-red'][$l['status']] ?? 'b-slate' ?>"><?= e(ucfirst($l['status'])) ?></span></td></tr><?php endforeach; if (!$leaves): ?><tr><td class="empty">No leave requests.</td></tr><?php endif; ?></tbody></table></div></section>
  <section class="card"><div class="card-head"><h2><?= icon('log', 20) ?> Recent work logs</h2><a href="admin.php?tab=logs&emp=<?= $emp['id'] ?>">All logs →</a></div>
    <?php foreach ($logs as $w): ?><div class="kv" style="display:block"><div style="display:flex;justify-content:space-between"><b><?= e(fmt_date($w['log_date'], 'D, M j')) ?> <?= $w['tag'] ? '· ' . e($w['tag']) : '' ?></b><span class="badge <?= ['approved' => 'b-green', 'submitted' => 'b-amber', 'rejected' => 'b-red'][$w['status']] ?? 'b-slate' ?>"><?= e(ucfirst($w['status'])) ?></span></div><p class="muted" style="white-space:pre-line"><?= e(mb_strimwidth($w['content'], 0, 160, '…')) ?></p></div><?php endforeach; if (!$logs): ?><p class="empty">No work logs.</p><?php endif; ?></section>
</div>
<?php endif; ?>
<?php unset($_SESSION['form_old']); render_footer(); ?>
