<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];

$slips = q_all('SELECT * FROM payslips WHERE user_id=? ORDER BY pay_year DESC, pay_month DESC', [$uid]);
$view = null;
if (!empty($_GET['id'])) $view = q_one('SELECT * FROM payslips WHERE id=? AND user_id=?', [(int)$_GET['id'], $uid]);
if ($view) {
    $monthStart = sprintf('%04d-%02d-01', $view['pay_year'], $view['pay_month']);
    $daysInMonth = (int)date('t', strtotime($monthStart));
    $lopDays = (float)$view['lop_days'];
    $workingDays = max(0.0, $daysInMonth - $lopDays);   // days the employee is paid for: every day of the month except loss-of-pay days
    $fmtDays = fn(float $d): string => rtrim(rtrim(number_format($d, 1), '0'), '.') ?: '0';
    $gross = (float)$view['basic'] + (float)$view['hra'] + (float)$view['allowances'] + (float)$view['overtime_pay'];
    $ded = (float)$view['pf'] + (float)$view['tax'] + (float)$view['prof_tax'] + (float)$view['lop_amount'];
    $earnings = [['Base Salary', 'basic'], ['House Rent Allowance', 'hra'], ['Special Allowances', 'allowances'], ['Overtime', 'overtime_pay']];
    $deductions = [['Provident Fund', 'pf'], ['Income Tax (TDS)', 'tax'], ['Professional Tax', 'prof_tax'], ['Loss of Pay (' . $fmtDays($lopDays) . ' d)', 'lop_amount']];
}

render_header('Payslips', 'payslips', $user);
if ($view): ?>
  <div class="no-print" style="margin-bottom:16px"><a href="payslips.php">← All payslips</a></div>
  <section class="card ps">
    <table class="psl">
      <tr><td colspan="2" class="psl-logo"><img src="assets/img/logo.png" alt="Liberty Foundation - since 2008"></td><td colspan="2" class="psl-co"><b>Liberty Foundation</b><br>Barrackpore, Kolkata, 700122, WB, IN</td></tr>
      <tr><td colspan="4" class="c"><b>Pay Slip for <?= e(date('F Y', strtotime($monthStart))) ?></b></td></tr>
      <tr><td>Employee Name</td><td><?= e($user['name']) ?></td><td>Days in Month</td><td><?= $daysInMonth ?></td></tr>
      <tr><td>Employee ID</td><td><?= e($user['emp_code']) ?></td><td>Total Working Days</td><td><?= e($fmtDays($workingDays)) ?></td></tr>
      <tr><td>Designation</td><td><?= e($user['designation']) ?></td><td>LOP Days</td><td><?= e($fmtDays($lopDays)) ?></td></tr>
      <tr><td>Department</td><td><?= e($user['department']) ?></td><td>Bank Name</td><td><?= e($user['bank_name'] ?: '—') ?></td></tr>
      <tr><td>Date of Joining</td><td><?= e(fmt_date($user['joined_on'], 'd/m/Y')) ?></td><td>Bank A/c No</td><td><?= e($user['bank_account_no'] ?: '—') ?></td></tr>
      <tr class="h"><td colspan="2" class="c">Earnings</td><td colspan="2" class="c">Deductions</td></tr>
      <?php foreach ($earnings as $i => [$label, $k]): [$dl, $dk] = $deductions[$i]; ?>
        <tr><td><?= e($label) ?></td><td class="n"><?= money($view[$k]) ?></td><td><?= e($dl) ?></td><td class="n"><?= money($view[$dk]) ?></td></tr>
      <?php endforeach; ?>
      <tr class="t"><td>Gross Salary</td><td class="n"><?= money($gross) ?></td><td>Total Deductions</td><td class="n"><?= money($ded) ?></td></tr>
      <tr class="t"><td colspan="3" class="c">Net Pay</td><td class="n"><?= money($view['net_pay']) ?></td></tr>
      <tr><td>Amount in Words</td><td colspan="3"><?= e(amount_in_words((float)$view['net_pay'])) ?></td></tr>
    </table>
    <div class="no-print btn-row"><button class="btn btn-dark" onclick="window.print()"><?= icon('print', 16) ?> Print / Save as PDF</button></div>
  </section>
<?php else:
  page_title('Payslips', 'Monthly salary statements generated from your attendance and approved overtime.'); ?>
  <section class="card"><div class="table-wrap"><table class="t">
    <thead><tr><th>Month</th><th>Gross</th><th>Deductions</th><th>Overtime</th><th>Net Pay</th><th></th></tr></thead><tbody>
    <?php foreach ($slips as $s): $g = $s['basic'] + $s['hra'] + $s['allowances']; $dd = $s['pf'] + $s['tax'] + $s['prof_tax'] + $s['lop_amount']; ?>
      <tr><td><b><?= e(date('F Y', mktime(0, 0, 0, (int)$s['pay_month'], 1, (int)$s['pay_year']))) ?></b></td><td><?= money($g) ?></td><td><?= money($dd) ?></td><td><?= money($s['overtime_pay']) ?></td><td><b><?= money($s['net_pay']) ?></b></td>
        <td><a class="btn btn-sm" href="payslips.php?id=<?= $s['id'] ?>">View</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$slips): ?><tr><td colspan="6" class="empty">No payslips have been generated for you yet.</td></tr><?php endif; ?>
  </tbody></table></div></section>
<?php endif;
render_footer();
