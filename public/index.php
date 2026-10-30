<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];
$ts = today_status($user);
$state = $ts['state'];
$year = (int)date('Y');

// --- stats ---
$days = month_overview($user, date('Y-m'));
$sum = month_summary($days);
$active = max(0, $sum['weekdays'] - $sum['hol_weekday'] - $sum['weekoff'] - (int)ceil($sum['leave']));
$presentDays = $sum['present'];
$prev = month_summary(month_overview($user, date('Y-m', strtotime('first day of last month'))));
$delta = round($sum['rate'] - $prev['rate'], 1);

$bal = leave_balances($uid, $year);
$taken = 0.0; $parts = [];
$remaining = 0.0; $totalQuota = 0.0;
foreach ($bal as $b) {
    if ($b['approved'] > 0) $parts[] = rtrim(rtrim(number_format($b['approved'], 1), '0'), '.') . ' ' . $b['name'] . ' ';
    $taken += $b['approved'];
    if ($b['quota'] > 0) { $remaining += $b['remaining']; $totalQuota += $b['quota']; }
}
$workdaysElapsed = count(working_dates(date('Y-m-01'), today()));
$logsDone = (int)q_val('SELECT COUNT(*) FROM work_logs WHERE user_id=? AND status IN ("submitted","approved") AND log_date BETWEEN ? AND ?', [$uid, date('Y-m-01'), today()]);
$todayLog = q_one('SELECT * FROM work_logs WHERE user_id=? AND log_date=?', [$uid, today()]);
$pendingLogs = (int)q_val('SELECT COUNT(*) FROM work_logs WHERE user_id=? AND status="draft" AND log_date BETWEEN ? AND ?', [$uid, date('Y-m-01'), today()]);

$anns = q_all('SELECT * FROM announcements ORDER BY posted_on DESC, id DESC LIMIT 3');
$unreadAnnouncements = (int)q_val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0 AND message LIKE 'New announcement:%'", [$uid]);
$hols = q_all('SELECT * FROM holidays WHERE holiday_date >= ? ORDER BY holiday_date LIMIT 3', [today()]);
$acts = q_all('SELECT * FROM activity_log WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 4', [$uid]);

$required = $ts['required_min'];
$pct = $required ? min(100, (int)round($ts['total_min'] / $required * 100)) : 0;   // overall hours = productive + break
$capPct = min(100, (int)round($ts['total_min'] / max(1, $required) * 100));
$statusText = ['none' => 'Not Checked In', 'in' => 'Shift in Progress • Checked In', 'break' => 'On Break • Timer Paused', 'out' => 'Shift Completed • Checked Out'][$state];
$ip = client_ip();
$weekoffToday = is_weekoff($uid, today());
$wb = weekoff_balance($user, date('Y-m'));
$woErr = $state === 'none' && !$weekoffToday ? weekoff_check($user, today()) : null;

render_header('Dashboard', 'dashboard', $user);
?>
<?php if ($ts['first_in']): ?>
<div class="banner"><?= icon('check', 22) ?><span><b>Checked in at <?= e(fmt_time($ts['first_in'])) ?> . <?= e($ts['loc_in']) ?></b></span><button class="alert-x" data-dismiss aria-label="Dismiss"><?= icon('x', 18) ?></button></div>
<?php endif; ?>

<div class="page-head welcome">
  <div><h1>Welcome, <?= e(explode(' ', $user['name'])[0]) ?></h1>
  <p class="muted"><?= date('l, F j, Y') ?><?= $user['team'] !== '' ? ' • ' . e($user['team']) : '' ?></p></div>
</div>

<div class="grid g-2-1">
  <section class="card topline">
    <div class="shift-top">
      <h2><span class="dot <?= $state === 'in' ? 'on' : ($state === 'break' ? 'brk' : '') ?>"></span> <?= e($statusText) ?></h2>
      <span class="pill mono" style="font-size:11px"><?= e(strtoupper($user['shift_label'])) ?></span>
    </div>
    <div class="eyebrow">Overall working time (work + break)</div>
    <div class="timer-row" style="margin-top:8px">
      <div id="shiftTimer" class="timer" data-base="<?= $ts['live_total_sec'] ?>" data-running="<?= in_array($state, ['in', 'break'], true) ? 1 : 0 ?>">00h 00m 00s <small>overall today</small></div>
      <div class="punch-box">
        <div><small class="eyebrow">First punch in</small><b><?= $ts['first_in'] ? e(date('h:i A', strtotime($ts['first_in']))) : '--:--' ?></b>
          <small>Expected <?= e(date('h:i A', strtotime(today() . ' ' . $user['start_time']))) ?></small></div>
        <div><small class="eyebrow">Last punch out</small><b><?= $ts['last_out'] ? e(date('h:i A', strtotime($ts['last_out']))) : '--:--' ?></b>
          <small>Expected <?= e(date('h:i A', $ts['expected_out'])) ?></small></div>
      </div>
    </div>
    <p class="muted" style="margin-top:12px;font-size:13px">↗ Target: <?= sprintf('%02dh %02dm', intdiv($required, 60), $required % 60) ?> daily requirement (<?= $pct ?>% completed)</p>

    <div class="punch-cta">
      <?php $working = $state === 'in' || $state === 'break'; ?>
      <?php $dayDone = $state === 'out' && $user['role'] !== 'admin'; ?>
      <?php if ($weekoffToday): ?>
        <div class="weekoff-note"><?= icon('palm', 22) ?><div><b>Today is your week off</b><small>No punch-in needed. Changed your mind? You can cancel it.</small></div>
          <form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="do" value="weekoff_cancel"><input type="hidden" name="back" value="index.php"><button class="btn btn-sm">Cancel week off</button></form></div>
      <?php elseif ($dayDone): ?>
        <div class="weekoff-note"><?= icon('check', 22) ?><div><b>Today's punch in/out is complete</b><small>One punch in and punch out per day. Need a change? Contact your admin.</small></div></div>
      <?php else: ?>
      <form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="do" value="<?= $working ? 'punch_out' : 'punch_in' ?>"><input type="hidden" name="back" value="index.php">
        <?php if (!$working): ?><div class="loc-field"><label for="punchLoc">Location</label><input class="input" id="punchLoc" name="location" maxlength="120" value="Barrackpore Office" required placeholder="Where are you working from?"></div><?php endif; ?>
        <button class="punch-btn <?= $working ? 'punch-out' : 'punch-in' ?>">
          <span class="pb-ic"><?= icon($working ? 'out' : 'in', 30) ?></span>
          <span class="pb-txt"><b><?= $working ? 'PUNCH OUT' : ($state === 'out' ? 'PUNCH IN AGAIN' : 'PUNCH IN') ?></b>
            <small><?= $working ? 'Tap to end your shift for today' : ($state === 'out' ? 'Tap to start another session' : 'Tap to start your shift') ?></small></span>
        </button>
      </form>
      <?php if ($state === 'none'): ?>
        <form method="post" action="actions.php" data-confirm="Mark today as your week off? You will not be able to punch in today unless you cancel it."><?= csrf_field() ?><input type="hidden" name="do" value="weekoff_mark"><input type="hidden" name="back" value="index.php">
          <button class="btn btn-lg btn-block btn-weekoff" <?= $woErr ? 'disabled' : '' ?>><?= icon('palm', 18) ?> Mark today as Week Off</button>
          <small class="muted" style="display:block;margin-top:6px"><?= $woErr ? e($woErr) : 'Week offs left this month: <b>' . $wb['left'] . '</b> of ' . $wb['total'] . ($wb['carry'] ? ' (incl. ' . $wb['carry'] . ' carried over)' : '') . ' · max 1 per week' ?></small></form>
      <?php endif; ?>
      <?php endif; ?>
      <div class="punch-secondary">
        <?php if ($working): ?>
          <form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="do" value="<?= $state === 'break' ? 'break_end' : 'break_start' ?>"><input type="hidden" name="back" value="index.php">
            <button class="btn btn-lg btn-block btn-violet <?= $state === 'break' ? 'btn-brand' : '' ?>"><?= icon('coffee', 18) ?> <?= $state === 'break' ? 'Resume Work' : 'Take a Break' ?></button></form>
        <?php endif; ?>
        <button type="button" class="btn btn-lg btn-block btn-violet" data-open="#punchDlg"><?= icon('history', 18) ?> Day Punches (<?= $ts['punch_count'] ?>)</button>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2>Today's Attendance Breakdown</h2><span class="badge <?= $state === 'none' ? 'b-slate' : 'b-green' ?>"><?= $state === 'none' ? 'Not Yet' : 'Present' ?></span></div>
    <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:8px"><span class="muted">Overall Working Hours (<?= sprintf('%02dh %02dm', intdiv($ts['total_min'], 60), $ts['total_min'] % 60) ?>)</span><b>Shift Capacity: <?= $capPct ?>%</b></div>
    <div class="bar"><i class="g" style="width:<?= round(min($ts['worked_min'], $required) / max($required, 1) * 100) ?>%"></i><i class="a" style="width:<?= round($ts['break_min'] / max($required, 1) * 100) ?>%"></i></div>
    <div class="rowline"><span class="lbl"><i class="dot" style="background:var(--brand)"></i> Productive Working Time</span><b><?= fmt_hm($ts['worked_min']) ?></b></div>
    <div class="rowline"><span class="lbl"><i class="dot" style="background:#fbbf24"></i> Break &amp; Lunch Interval</span><b><?= fmt_hm($ts['break_min']) ?></b></div>
    <div class="rowline"><span class="lbl"><i class="dot" style="background:#94a3b8"></i> Overtime Registered</span><b><?= fmt_hm($ts['overtime_min']) ?></b></div>
    <div style="display:flex;justify-content:space-between;border-top:1px solid var(--line);margin-top:22px;padding-top:14px;font-size:12px" class="muted">
      <span class="mono"><?= $state === 'none' ? 'No punch recorded' : e($ts['loc_in']) ?></span>
    </div>
  </section>
</div>

<div class="grid g-4 mt">
  <div class="card stat"><div class="stat-head"><span class="eyebrow">Month Attendance</span><?= icon('calendar', 18) ?></div>
    <div class="big"><?= $sum['rate'] ?>% <?php if ($prev['weekdays']): ?><span class="badge <?= $delta >= 0 ? 'b-green' : 'b-red' ?>" style="font-size:11px"><?= $delta >= 0 ? '↑' : '↓' ?><?= abs($delta) ?>%</span><?php endif; ?></div>
    <div class="meter"><i style="width:<?= $sum['rate'] ?>%"></i></div>
    <p class="muted" style="font-size:12.5px;margin-top:10px"><?= $presentDays ?> of <?= max($active, $presentDays) ?> active workdays present</p></div>
  <div class="card stat"><div class="stat-head"><span class="eyebrow">Leaves Taken</span><?= icon('leave', 18) ?></div>
    <div class="big"><?= rtrim(rtrim(number_format($taken, 1), '0'), '.') ?: '0' ?> <small>Days</small> <span class="pill mono" style="font-size:10px">YTD <?= $year ?></span></div>
    <div class="meter"><i style="width:<?= min(100, $taken / max(1, $totalQuota) * 100) ?>%;background:var(--ink)"></i></div>
    <p class="muted" style="font-size:12.5px;margin-top:10px"><?= $parts ? e(implode('• ', $parts)) : 'No approved leave yet' ?></p></div>
  <div class="card stat"><div class="stat-head"><span class="eyebrow">Leaves Remaining</span><?= icon('check', 18) ?></div>
    <div class="big"><?= rtrim(rtrim(number_format($remaining, 1), '0'), '.') ?: '0' ?> <small>Days</small> <span class="badge b-mint" style="font-size:11px"><?= $remaining > 10 ? 'Good balance' : 'Low balance' ?></span></div>
    <div class="meter"><i style="width:<?= min(100, $remaining / max(1, $totalQuota) * 100) ?>%"></i></div>
    <p class="muted" style="font-size:12.5px;margin-top:10px">Valid until Dec 31, <?= $year ?></p></div>
  <div class="card stat"><div class="stat-head"><span class="eyebrow">Work Logs Logged</span><?= icon('log', 18) ?></div>
    <div class="big"><?= $logsDone ?><small style="font-size:22px">/<?= max($workdaysElapsed, $logsDone) ?></small> <?php if ($pendingLogs): ?><span class="badge b-amber" style="font-size:11px"><?= $pendingLogs ?> Pending</span><?php endif; ?></div>
    <div class="meter"><i style="width:<?= $workdaysElapsed ? min(100, $logsDone / $workdaysElapsed * 100) : 0 ?>%;background:var(--ink)"></i></div>
    <p class="muted" style="font-size:12.5px;margin-top:10px"><?= $todayLog && $todayLog['status'] !== 'draft' ? "Today's log submitted" : "Submit today's log from Daily Work Log" ?></p></div>
</div>

<div class="grid g-3-2 mt">
  <div class="stack">
    <section class="card">
      <div class="card-head"><h2>Recent Attendance &amp; Log Activity</h2><a href="worklog.php" style="font-size:13px">View All Log History</a></div>
      <?php if (!$acts): ?><p class="empty">No activity yet. Punch in to get started.</p><?php endif; ?>
      <?php foreach ($acts as $a): $isToday = date('Y-m-d', strtotime($a['created_at'])) === today(); ?>
        <div class="feed-item"><span class="feed-ic <?= $a['icon'] === 'break' ? '' : ($a['icon'] === 'log' ? 'blue' : '') ?>"><?= icon($a['icon'] === 'in' ? 'in' : ($a['icon'] === 'out' ? 'out' : ($a['icon'] === 'break' ? 'break' : 'check')), 18) ?></span>
          <div style="flex:1"><div class="tt"><span><?= e($a['title']) ?></span><time><?= $isToday ? e(date('h:i A', strtotime($a['created_at']))) . ' today' : e(date('M d, h:i A', strtotime($a['created_at']))) ?></time></div><p><?= e($a['detail']) ?></p></div></div>
      <?php endforeach; ?>
    </section>
    <section class="card">
      <div class="card-head"><h2><?= icon('palm', 20) ?> Upcoming Holidays</h2><a href="attendance.php?view=holidays&scope=year" style="font-size:13px">Full <?= $year ?> List</a></div>
      <?php foreach ($hols as $h): $n = (int)((strtotime($h['holiday_date']) - strtotime(today())) / 86400); ?>
        <div class="hol"><div class="hol-date"><small><?= strtoupper(date('M', strtotime($h['holiday_date']))) ?></small><b><?= date('d', strtotime($h['holiday_date'])) ?></b></div>
          <div><b><?= e($h['name']) ?></b><br><small><?= date('l', strtotime($h['holiday_date'])) ?> • <?= e($h['note']) ?></small></div>
          <span class="badge b-blue"><?= $n === 0 ? 'today' : "in $n days" ?></span></div>
      <?php endforeach; ?>
      <?php if (!$hols): ?><p class="empty">No upcoming holidays.</p><?php endif; ?>
    </section>
  </div>

  <div class="stack">
    <section class="card" id="teamAnnouncements" data-announcements-unread="<?= $unreadAnnouncements > 0 ? '1' : '0' ?>">
      <div class="card-head"><h2><?= icon('megaphone', 20) ?> Team Announcements<?php if ($unreadAnnouncements): ?> <span class="announcement-unread-dot" role="status" aria-label="New announcements"></span><?php endif; ?></h2><a href="notices.php" style="font-size:13px">All notices →</a></div>
      <?php foreach ($anns as $a): ?>
        <article class="ann"><div class="meta"><span><?= e($a['dept']) ?></span><span><?= $a['updated_at'] ? 'Updated ' . e(fmt_date($a['updated_at'], 'M d')) : e(fmt_date($a['posted_on'], 'M d')) ?></span></div><h3><?= e($a['title']) ?></h3><p><?= e($a['body']) ?></p></article>
      <?php endforeach; ?>
      <?php if (!$anns): ?><p class="empty">No announcements.</p><?php endif; ?>
    </section>
  </div>
</div>

<dialog id="punchDlg">
  <div class="modal-h"><h2>Today's Punches</h2><button class="icon-btn" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
  <div class="modal-b">
    <?php if (!$ts['events']): ?><p class="empty">No punches recorded today.</p><?php endif; ?>
    <?php $names = ['in' => 'Punch In', 'out' => 'Punch Out', 'break_start' => 'Break Started', 'break_end' => 'Break Ended']; foreach ($ts['events'] as $ev): ?>
      <div class="kv"><span><?= e($names[$ev['event_type']]) ?></span><span class="mono"><?= e(date('h:i:s A', strtotime($ev['event_time']))) ?></span></div>
      <div class="muted" style="font-size:12px;padding:0 16px 8px"><?= e($ev['location']) ?></div>
    <?php endforeach; ?>
  </div>
</dialog>
<?php render_footer(); ?>
