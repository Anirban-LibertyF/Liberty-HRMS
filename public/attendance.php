<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];

$ym = preg_match('/^\d{4}-\d{2}$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
$first = $ym . '-01';
$prevM = date('Y-m', strtotime("$first -1 month"));
$nextM = date('Y-m', strtotime("$first +1 month"));
$view = ($_GET['view'] ?? '') === 'holidays' ? 'holidays' : 'mine';
$scope = ($_GET['scope'] ?? '') === 'year' ? 'year' : 'month';   // holiday list: one month or the whole year
$yr = (int)substr($ym, 0, 4);

$days = month_overview($user, $ym);
$sum = month_summary($days);
$wb = weekoff_balance($user, $ym);

$sel = $_GET['date'] ?? '';
if (!isset($days[$sel])) $sel = isset($days[today()]) ? today() : array_key_first($days);
$d = $days[$sel];
$att = $d['att'];
$log = q_one('SELECT * FROM work_logs WHERE user_id=? AND log_date=?', [$uid, $sel]);

$lead = (int)date('N', strtotime($first)) - 1;               // blanks before the 1st (Mon first)
$gridStart = strtotime("$first -$lead days");
$cells = (int)ceil(($lead + count($days)) / 7) * 7;
$statusLabel = ['present' => 'Present', 'absent' => 'Absent', 'leave' => 'On Leave', 'holiday' => 'Holiday', 'weekend' => 'Weekend', 'weekoff' => 'Week Off', 'upcoming' => 'Upcoming', 'none' => '—'];
$badgeCls = ['present' => 'b-green', 'absent' => 'b-red', 'leave' => 'b-amber', 'holiday' => 'b-slate', 'weekend' => 'b-slate', 'weekoff' => 'b-slate', 'upcoming' => 'b-blue', 'none' => 'b-slate'];

render_header('Attendance Calendar', 'attendance', $user);
page_title('Attendance Calendar', 'Review your daily punch logs and company holidays.',
    '<span class="pill mono" style="font-size:11px">Shift: ' . e($user['shift_name']) . ' (' . e(substr($user['start_time'], 0, 5)) . ' - ' . e(substr($user['end_time'], 0, 5)) . ')</span>
     <div class="seg"><a href="attendance.php?m=' . $ym . '" class="' . ($view === 'mine' ? 'on' : '') . '">My Attendance</a></div>
     <a href="attendance.php?view=holidays&m=' . $ym . '" class="btn btn-holiday' . ($view === 'holidays' ? ' on' : '') . '">Company Holiday List</a>');
?>
<?php if ($view === 'holidays'): ?>
<div class="head-actions no-print" style="margin:-8px 0 20px">
  <?php if ($scope === 'month'): ?>
    <a class="btn btn-sm" href="attendance.php?view=holidays&m=<?= $prevM ?>" aria-label="Previous month">‹</a>
    <b style="min-width:150px;text-align:center"><?= date('F Y', strtotime($first)) ?></b>
    <a class="btn btn-sm" href="attendance.php?view=holidays&m=<?= $nextM ?>" aria-label="Next month">›</a>
    <a class="btn btn-sm" href="attendance.php?view=holidays">This month</a>
    <a class="btn btn-brand" href="attendance.php?view=holidays&scope=year&m=<?= $ym ?>"><?= icon('palm', 16) ?> View all <?= $yr ?> holidays</a>
  <?php else: ?>
    <a class="btn btn-sm" href="attendance.php?view=holidays&scope=year&m=<?= ($yr - 1) ?>-01" aria-label="Previous year">‹</a>
    <b style="min-width:150px;text-align:center"><?= $yr ?></b>
    <a class="btn btn-sm" href="attendance.php?view=holidays&scope=year&m=<?= ($yr + 1) ?>-01" aria-label="Next year">›</a>
    <a class="btn" href="attendance.php?view=holidays&m=<?= $ym ?>">Back to monthly view</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="head-actions no-print" style="margin:-8px 0 20px">
  <a class="btn btn-sm" href="attendance.php?m=<?= $prevM ?>" aria-label="Previous month">‹</a>
  <b style="min-width:150px;text-align:center"><?= date('F Y', strtotime($first)) ?></b>
  <a class="btn btn-sm" href="attendance.php?m=<?= $nextM ?>" aria-label="Next month">›</a>
  <a class="btn btn-sm" href="attendance.php">Today</a>
</div>
<?php endif; ?>

<?php if ($view === 'holidays'):
    $hl = $scope === 'year'
        ? q_all('SELECT * FROM holidays WHERE YEAR(holiday_date)=? ORDER BY holiday_date', [$yr])
        : q_all('SELECT * FROM holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date', [$first, date('Y-m-t', strtotime($first))]); ?>
  <section class="card">
    <div class="card-head"><h2><?= icon('palm', 20) ?> Company Holidays — <?= $scope === 'year' ? $yr : e(date('F Y', strtotime($first))) ?></h2></div>
    <div class="table-wrap"><table class="t"><thead><tr><th>Date</th><th>Day</th><th>Holiday</th><th>Type</th></tr></thead><tbody>
      <?php foreach ($hl as $h): ?><tr><td><?= e(fmt_date($h['holiday_date'])) ?></td><td><?= e(date('l', strtotime($h['holiday_date']))) ?></td><td><b><?= e($h['name']) ?></b></td><td><?= e($h['note']) ?></td></tr><?php endforeach; ?>
      <?php if (!$hl): ?><tr><td colspan="4" class="empty">No holidays listed for <?= $scope === 'year' ? 'this year' : 'this month' ?>.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
<?php else: ?>

<section class="card" style="padding:16px 22px;margin-bottom:20px">
  <div class="eyebrow" style="margin-bottom:10px">Monthly Status Breakdown</div>
  <div class="legend">
    <span class="badge b-green">● Present: <?= $sum['present'] ?> days</span>
    <span class="badge b-red">● Absent: <?= $sum['absent'] ?> day<?= $sum['absent'] == 1 ? '' : 's' ?></span>
    <span class="badge b-amber">● Leave: <?= rtrim(rtrim(number_format($sum['leave'], 1), '0'), '.') ?: 0 ?> days</span>
    <span class="badge b-slate">● Holiday: <?= $sum['holiday'] ?> days</span>
    <span class="badge b-slate">● Week Off: <?= $sum['weekoff'] ?> day<?= $sum['weekoff'] == 1 ? '' : 's' ?> (<?= $wb['left'] ?> left<?= $wb['carry'] ? ', ' . $wb['carry'] . ' carried over' : '' ?>)</span>
    <a href="policy.php" style="margin-left:auto;font-size:12.5px;display:flex;gap:6px;align-items:center"><?= icon('shield', 14) ?> Attendance Policy Rules</a>
  </div>
  <?php $pay = payroll_calc($user, $ym); $isCur = $ym === date('Y-m');
        $paidAt = q_val('SELECT paid_at FROM payout_paid WHERE user_id=? AND pay_month=?', [$uid, $ym]); ?>
  <div class="sal-row">
    <div><span>Attendance Salary</span><b><?= e(money($pay['att_pay'])) ?></b></div>
    <div<?= $pay['ot_payouts'] ? ' title="' . $pay['ot_payouts'] . ' extra payout(s) x ' . e(money($pay['daily'])) . ' (one day\'s basic pay)"' : '' ?>><span>Overtime Pay</span><b><?= e(money($pay['ot_pay'])) ?></b></div>
    <div class="sal-net"><span>Net Salary<?= $isCur ? ' (so far)' : '' ?></span><b><?= e(money($pay['net'])) ?></b></div>
    <div><span>Salary Date</span><b><?= $paidAt ? e(fmt_date($paidAt)) : '<span class="muted" style="font-weight:500">Not paid yet</span>' ?></b></div>
  </div>
</section>

<div class="grid g-3-2" style="align-items:start">
  <div class="cal">
    <div class="cal-head"><?php foreach (['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'] as $i => $w): ?><div><?= $w ?></div><?php endforeach; ?></div>
    <div class="cal-grid">
    <?php for ($i = 0; $i < $cells; $i++):
        $t = $gridStart + $i * 86400; $ds = date('Y-m-d', $t);
        if (!isset($days[$ds])): ?>
          <div class="cal-cell out"><div class="cal-num"><span><?= date('d', $t) ?></span></div></div>
        <?php continue; endif;
        $r = $days[$ds]; $a = $r['att'];
        $dotCls = ['present' => 'dot-g', 'absent' => 'dot-r', 'leave' => 'dot-a', 'weekoff' => 'dot-s'][$r['type']] ?? '';
    ?>
      <a class="cal-cell <?= $ds === $sel ? 'sel' : '' ?>" href="attendance.php?m=<?= $ym ?>&date=<?= $ds ?>" aria-label="<?= e(date('l, F j', $t)) ?>: <?= e($statusLabel[$r['type']]) ?>">
        <div class="cal-num"><?php if ($ds === today()): ?><span class="today"><?= date('d', $t) ?></span><?php else: ?><span><?= date('d', $t) ?></span><?php endif; ?><i class="<?= $dotCls ?>"></i></div>
        <?php if ($r['type'] === 'present'): ?>
          <span class="ev ev-present"><?= $a['status'] === 'half_day' ? 'Half Day' : 'Present' ?><?= $a['overtime_min'] > 0 ? ' <sup>+' . (int)$a['overtime_min'] . 'm OT</sup>' : '' ?>
            <small><?= date('H:i', strtotime($a['first_in'])) ?><?= $a['last_out'] ? ' - ' . date('H:i', strtotime($a['last_out'])) : ' - …' ?></small></span>
        <?php elseif ($r['type'] === 'holiday'): ?>
          <span class="ev ev-holiday"><?= e($r['holiday']['name']) ?></span>
        <?php elseif ($r['type'] === 'weekoff'): ?>
          <span class="ev ev-weekoff">Week Off</span>
        <?php elseif ($r['type'] === 'weekend'): ?>
          <span class="ev ev-weekend">Weekend Off</span>
        <?php elseif ($r['type'] === 'leave'): ?>
          <span class="ev ev-leave"><?= e($r['leave']['type_name']) ?><small><?= $r['leave']['status'] === 'approved' ? 'Approved' : 'Pending' ?></small></span>
        <?php elseif ($r['type'] === 'absent'): ?>
          <span class="ev ev-absent">✕ Absent<small>No punch record</small></span>
        <?php endif; ?>
      </a>
    <?php endfor; ?>
    </div>
  </div>

  <div class="stack">
  <aside class="card detail" id="dayDetail">
    <div class="eyebrow" style="color:var(--brand)">Audit Log Details</div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin:6px 0 8px;flex-wrap:wrap">
      <h2 style="font-size:21px"><?= e(date('l, M j, Y', strtotime($sel))) ?></h2>
      <span class="badge <?= $badgeCls[$d['type']] ?>"><?= $att && $att['regularized'] ? 'Regularized' : e($d['type'] === 'present' ? 'Approved' : $statusLabel[$d['type']]) ?></span>
    </div>
    <?php if ($att):
        $start = strtotime("$sel {$user['start_time']}"); $endS = strtotime("$sel {$user['end_time']}");
        $fi = strtotime($att['first_in']); $lo = $att['last_out'] ? strtotime($att['last_out']) : null;
        $span = max(1, $endS - $start);
        $l = max(0, min(100, ($fi - $start) / $span * 100)); $w = max(2, min(100 - $l, (($lo ?? ($sel === today() ? time() : $endS)) - $fi) / $span * 100));
        $lateMin = (int)max(0, round(($fi - $start) / 60));
    ?>
      <p class="muted" style="font-size:13px;margin-bottom:16px">Punch data verified via <?= e($att['regularized'] ? 'approved regularization' : 'portal terminal') ?>.</p>
      <div class="mini" style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:13px"><span>Shift Horizon:</span><b style="display:inline;font-size:13px;margin:0"><?= date('h:i A', $start) ?> - <?= date('h:i A', $endS) ?></b></div>
        <div class="trk" style="position:relative"><i style="position:absolute;left:<?= $l ?>%;width:<?= $w ?>%"></i></div>
        <div style="display:flex;justify-content:space-between;font-size:11.5px" class="mono muted"><span><?= date('h:i A', $fi) ?></span><span><?= fmt_hm((int)$att['worked_min'], false) ?> recorded</span><span><?= $lo ? date('h:i A', $lo) : 'Open' ?></span></div>
      </div>
      <div class="field-row" style="margin-bottom:12px">
        <div class="mini"><div class="eyebrow" style="color:var(--brand)">Check-in</div><b><?= date('h:i A', $fi) ?></b><small><?= e($att['loc_in']) ?></small></div>
        <div class="mini"><div class="eyebrow">Check-out</div><b><?= $lo ? date('h:i A', $lo) : '--' ?></b><small><?= e($att['loc_out'] ?: '—') ?></small></div>
      </div>
      <div class="box">
        <div class="kv"><span>Total Duration</span><b><?= fmt_hm((int)$att['worked_min'], false) ?></b></div>
        <div class="kv"><span>Overtime Accrued</span><span><?= $att['overtime_min'] > 0 ? '<span class="badge b-blue">+' . (int)$att['overtime_min'] . 'm Approved</span>' : '—' ?></span></div>
        <div class="kv"><span>Break Time Logged</span><b style="font-weight:500"><?= (int)$att['break_min'] ?> mins</b></div>
      </div>
    <?php else: ?>
      <p style="margin:14px 0 18px" class="muted"><?php
        echo match ($d['type']) {
            'holiday' => '<b>' . e($d['holiday']['name']) . '</b> — ' . e($d['holiday']['note']) . '. No attendance expected.',
            'weekoff' => 'You marked this day as a week off. No attendance expected.',
            'weekend' => 'Weekly off. No attendance expected.',
            'leave' => e($d['leave']['type_name']) . ' (' . e($d['leave']['status']) . ') — ' . e($d['leave']['reason']),
            'absent' => 'No punch record exists for this working day. ',
            'upcoming' => 'This day has not occurred yet.',
            default => 'No data for this date.',
        }; ?></p>
    <?php endif; ?>

    <?php if ($log): ?>
      <div class="mini" style="margin-top:16px;background:var(--surface-2)">
        <div class="eyebrow"><?= icon('clip', 13) ?> Daily work log · <?= e(ucfirst($log['status'])) ?></div>
        <b style="font-size:16px"><?= e($log['tag'] ?: 'Work log') ?></b>
        <p class="muted" style="font-size:12.5px;white-space:pre-line"><?= e(mb_strimwidth($log['content'], 0, 140, '…')) ?></p>
        <a href="worklog.php?date=<?= e($sel) ?>" style="font-size:13px">Open Daily Work Log →</a>
      </div>
    <?php endif; ?>

  </aside>

  </div>
</div>

  <?php $ot = month_overtime($days, (int)$user['required_min']);
        $dayPay = round((float)$user['base_salary'] / 12 / (int)date('t', strtotime($first)), 2); ?>
<section class="card mt" id="otPanel" style="border-top:3px solid var(--brand-d)">
    <div class="eyebrow" style="color:var(--brand)">Overtime &middot; <?= e(date('F Y', strtotime($first))) ?></div>
    <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;margin:6px 0 4px;flex-wrap:wrap">
      <h2 style="font-size:21px"><?= fmt_hm($ot['total_min'], false) ?> <small class="muted" style="font-size:13px;font-weight:500">total overtime</small></h2>
      <span class="badge <?= $ot['payouts'] ? 'b-green' : 'b-slate' ?>"><?= $ot['payouts'] ?> extra payout<?= $ot['payouts'] === 1 ? '' : 's' ?></span>
    </div>
    <p class="muted" style="font-size:12.5px;margin-bottom:12px">Every <?= fmt_hm($ot['shift_min'], false) ?> of overtime in a month earns 1 extra day's payout.
      <?= $ot['rem_min'] ? e(fmt_hm($ot['shift_min'] - $ot['rem_min'], false)) . ' more for the next one.' : '' ?></p>
    <div class="kv" style="margin-bottom:10px"><span>One day's basic pay</span><b style="font-weight:500"><?= money($dayPay) ?></b></div>
    <div class="kv" style="margin-bottom:12px"><span><b>Overtime payout</b> (<?= $ot['payouts'] ?> &times; <?= money($dayPay) ?>)</span><b><?= money($dayPay * $ot['payouts']) ?></b></div>
    <?php if ($ot['rows']): ?>
      <div class="box ot-list">
        <?php foreach ($ot['rows'] as $od => $om): ?>
          <div class="kv"><a href="attendance.php?m=<?= e($ym) ?>&date=<?= e($od) ?>#dayDetail" style="color:inherit"><?= e(date('D, M j', strtotime($od))) ?></a><b style="font-weight:500">+<?= e(fmt_hm($om, false)) ?></b></div>
        <?php endforeach; ?>
      </div>
      <div class="kv" style="border-top:1px solid var(--line);padding-top:10px;margin-top:10px"><span><b>Total</b></span><b><?= fmt_hm($ot['total_min'], false) ?></b></div>
    <?php else: ?><p class="empty">No overtime recorded this month.</p><?php endif; ?>
  </section>

<?php endif; ?>
<?php render_footer(); ?>
