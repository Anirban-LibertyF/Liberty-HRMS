<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];

$slips = q_all('SELECT * FROM payslips WHERE user_id=? ORDER BY pay_year DESC, pay_month DESC', [$uid]);
$view = null;
if (!empty($_GET['id'])) $view = q_one('SELECT * FROM payslips WHERE id=? AND user_id=?', [(int)$_GET['id'], $uid]);
if ($view) {
    $gross = $view['basic'] + $view['hra'] + $view['allowances'];
    $ded = $view['pf'] + $view['tax'] + $view['prof_tax'] + $view['lop_amount'];
}

render_header('Payslips', 'payslips', $user);
if ($view): ?>
  <div class="no-print" style="margin-bottom:16px"><a href="payslips.php">← All payslips</a></div>
  <section class="card slip">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid var(--ink);padding-bottom:14px;margin-bottom:18px">
      <div><img src="assets/img/logo.png" alt="Liberty Foundation" style="height:44px;width:auto;display:block;margin-bottom:6px"><h1 style="font-size:24px">Liberty Office</h1><p class="muted">Payslip for <?= e(date('F Y', mktime(0, 0, 0, (int)$view['pay_month'], 1, (int)$view['pay_year']))) ?></p></div>
      <div style="text-align:right"><b><?= e($user['name']) ?></b><br><span class="muted"><?= e($user['emp_code']) ?> · <?= e($user['designation']) ?></span><br><span class="muted"><?= e($user['department']) ?></span></div>
    </div>
    <div class="grid g-2">
      <div><div class="eyebrow">Earnings</div>
        <div class="kv"><span>Basic</span><span><?= money($view['basic']) ?></span></div><div class="kv"><span>House Rent Allowance</span><span><?= money($view['hra']) ?></span></div>
        <div class="kv"><span>Special Allowances</span><span><?= money($view['allowances']) ?></span></div><div class="kv"><span>Overtime (1.5x)</span><span><?= money($view['overtime_pay']) ?></span></div>
        <div class="kv"><b>Gross + OT</b><b><?= money($gross + $view['overtime_pay']) ?></b></div></div>
      <div><div class="eyebrow">Deductions</div>
        <div class="kv"><span>Provident Fund (12%)</span><span><?= money($view['pf']) ?></span></div><div class="kv"><span>Income Tax (TDS)</span><span><?= money($view['tax']) ?></span></div>
        <div class="kv"><span>Professional Tax</span><span><?= money($view['prof_tax']) ?></span></div><div class="kv"><span>Loss of Pay (<?= rtrim(rtrim(number_format((float)$view['lop_days'], 1), '0'), '.') ?: 0 ?> d)</span><span><?= money($view['lop_amount']) ?></span></div>
        <div class="kv"><b>Total Deductions</b><b><?= money($ded) ?></b></div></div>
    </div>
    <div style="background:var(--brand-l);border-radius:10px;padding:18px;margin-top:20px;display:flex;justify-content:space-between;align-items:center"><span class="eyebrow" style="color:var(--brand-d)">Net Pay</span><b style="font-size:28px"><?= money($view['net_pay']) ?></b></div>
    <p class="muted" style="font-size:12px;margin-top:14px">Generated <?= e(fmt_date($view['generated_at'], 'M d, Y h:i A')) ?>. This is a system-generated payslip and needs no signature.</p>
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
