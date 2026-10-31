<?php
declare(strict_types=1);

function is_weekend(string $date): bool { return in_array((int)date('N', strtotime($date)), cfg('weekend'), true); }

function holidays_between(string $from, string $to): array
{
    $out = [];
    foreach (q_all('SELECT * FROM holidays WHERE holiday_date BETWEEN ? AND ?', [$from, $to]) as $h) $out[$h['holiday_date']] = $h;
    return $out;
}

/** Day-by-day list of working dates (no weekends / holidays) in a range. */
function working_dates(string $from, string $to): array
{
    $hol = holidays_between($from, $to);
    $out = [];
    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
        $d = date('Y-m-d', $t);
        if (!is_weekend($d) && !isset($hol[$d])) $out[] = $d;
    }
    return $out;
}

function day_events(int $uid, string $date): array
{
    return q_all('SELECT * FROM punch_events WHERE user_id = ? AND work_date = ? ORDER BY event_time, id', [$uid, $date]);
}

/** Replays punch events into worked / break seconds and open-interval markers. */
function summarize_events(array $events): array
{
    $work = 0; $brk = 0; $wStart = null; $bStart = null; $firstIn = null; $lastOut = null; $ipIn = ''; $ipOut = ''; $locIn = ''; $locOut = ''; $ins = 0;
    foreach ($events as $ev) {
        $t = strtotime($ev['event_time']);
        switch ($ev['event_type']) {
            case 'in':
                if ($wStart === null && $bStart === null) $wStart = $t;
                $ins++;
                if ($firstIn === null) { $firstIn = $ev['event_time']; $ipIn = $ev['ip']; $locIn = $ev['location']; }
                break;
            case 'break_start':
                if ($wStart !== null) { $work += $t - $wStart; $wStart = null; $bStart = $t; }
                break;
            case 'break_end':
                if ($bStart !== null) { $brk += $t - $bStart; $bStart = null; $wStart = $t; }
                break;
            case 'out':
                if ($wStart !== null) { $work += $t - $wStart; $wStart = null; }
                $lastOut = $ev['event_time']; $ipOut = $ev['ip']; $locOut = $ev['location'];
                break;
        }
    }
    $state = $bStart !== null ? 'break' : ($wStart !== null ? 'in' : ($firstIn ? 'out' : 'none'));
    return [
        'work_sec' => $work, 'break_sec' => $brk, 'work_start' => $wStart, 'break_start' => $bStart,
        'first_in' => $firstIn, 'last_out' => $lastOut, 'ip_in' => $ipIn, 'ip_out' => $ipOut, 'loc_in' => $locIn, 'loc_out' => $locOut,
        'state' => $state, 'punch_count' => count($events),
    ];
}

/** Recomputes and stores the attendance row for a day from its punch events. */
function recompute_attendance(array $user, string $date): ?array
{
    $uid = (int)$user['id'];
    $s = summarize_events(day_events($uid, $date));
    if (!$s['first_in']) { q('DELETE FROM attendance WHERE user_id=? AND work_date=? AND regularized=0', [$uid, $date]); return null; }

    $isToday = $date === today();   // open intervals on past days (missed punch-out) are not counted
    $now = time();
    $liveWork = $s['work_sec'] + ($isToday && $s['work_start'] ? $now - $s['work_start'] : 0);
    $liveBreak = $s['break_sec'] + ($isToday && $s['break_start'] ? $now - $s['break_start'] : 0);
    $workedMin = intdiv($liveWork, 60);
    $breakMin = intdiv($liveBreak, 60);
    $required = (int)$user['required_min'];
    $late = strtotime($s['first_in']) > strtotime("$date {$user['start_time']}") + (int)$user['grace_min'] * 60 ? 1 : 0;
    $status = ($s['state'] === 'out' && $workedMin < intdiv($required, 2)) ? 'half_day' : 'present';

    q('INSERT INTO attendance (user_id, work_date, status, first_in, last_out, worked_min, break_min, overtime_min, is_late, ip_in, ip_out, loc_in, loc_out)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE status=VALUES(status), first_in=VALUES(first_in), last_out=VALUES(last_out), worked_min=VALUES(worked_min),
         break_min=VALUES(break_min), overtime_min=VALUES(overtime_min), is_late=VALUES(is_late), ip_in=VALUES(ip_in), ip_out=VALUES(ip_out),
         loc_in=VALUES(loc_in), loc_out=VALUES(loc_out), regularized=0',
        [$uid, $date, $status, $s['first_in'], $s['last_out'], $workedMin, $breakMin, max(0, $workedMin + $breakMin - $required), $late,
         $s['ip_in'], $s['ip_out'], $s['loc_in'], $s['loc_out']]);
    return $s;
}

/** Applies a punch action. $type: in | out | break_start | break_end. */
function do_punch(array $user, string $type, string $location = ''): array
{
    $uid = (int)$user['id'];
    $date = today();
    $s = summarize_events(day_events($uid, $date));
    $state = $s['state'];
    $ip = client_ip();
    $loc = $location !== '' ? mb_substr($location, 0, 120) : ip_location($ip);
    $ts = date('Y-m-d H:i:s');

    $ins = fn(string $t) => q('INSERT INTO punch_events (user_id, work_date, event_type, event_time, ip, location) VALUES (?,?,?,?,?,?)', [$uid, $date, $t, $ts, $ip, $loc]);

    switch ($type) {
        case 'in':
            if ($state === 'in' || $state === 'break') return [false, 'You are already checked in.'];
            if ($state === 'out' && $user['role'] !== 'admin') return [false, 'You have already punched in and out for today. Use Take a Break / Resume Work for the rest of your shift, or contact your admin.'];
            if (is_weekoff($uid, $date)) return [false, 'Today is marked as your week off. Cancel it first to punch in.'];
            $ins('in');
            log_activity($uid, 'in', $s['first_in'] ? 'Punch In (again)' : 'Morning Punch In', 'Checked in at ' . date('h:i A') . ' . ' . $loc . '. Status marked as Present.');
            $msg = 'Checked in at ' . date('h:i A') . ' . ' . $loc;
            break;
        case 'out':
            if ($state === 'none' || $state === 'out') return [false, 'You are not checked in.'];
            if ($state === 'break') $ins('break_end');
            $ins('out');
            log_activity($uid, 'out', 'Check Out', 'Checked out at ' . date('h:i A') . ' from ' . $loc . '.');
            $msg = 'Checked out at ' . date('h:i A') . '. Have a great evening!';
            break;
        case 'break_start':
            if ($state !== 'in') return [false, 'You must be checked in (and not on break) to start a break.'];
            $ins('break_start');
            $msg = 'Break started. Your work timer is paused.';
            break;
        case 'break_end':
            if ($state !== 'break') return [false, 'You are not on a break.'];
            $ins('break_end');
            $mins = max(1, intdiv(time() - $s['break_start'], 60));
            log_activity($uid, 'break', "Break ({$mins} mins)", 'Break from ' . date('h:i A', $s['break_start']) . ' to ' . date('h:i A') . '. Timer resumed.');
            $msg = 'Welcome back! Work timer resumed.';
            break;
        default:
            return [false, 'Unknown action.'];
    }
    recompute_attendance($user, $date);
    return [true, $msg];
}

/** Everything the dashboard needs about today's shift. */
function today_status(array $user): array
{
    $date = today();
    $s = summarize_events(day_events((int)$user['id'], $date));
    $now = time();
    $liveWork = $s['work_sec'] + ($s['work_start'] ? $now - $s['work_start'] : 0);
    $liveBreak = $s['break_sec'] + ($s['break_start'] ? $now - $s['break_start'] : 0);
    $required = (int)$user['required_min'];
    $s['live_work_sec'] = $liveWork;
    $s['live_break_sec'] = $liveBreak;
    $s['live_total_sec'] = $liveWork + $liveBreak;
    $s['worked_min'] = intdiv($liveWork, 60);
    $s['break_min'] = intdiv($liveBreak, 60);
    $s['total_min'] = $s['worked_min'] + $s['break_min'];   // overall working hours = productive time + break & lunch
    $s['overtime_min'] = max(0, $s['total_min'] - $required);
    $s['required_min'] = $required;
    $s['expected_out'] = strtotime($date . ' ' . $user['end_time']);   // fixed office closing time (5:30 PM)
    $s['events'] = day_events((int)$user['id'], $date);
    return $s;
}

/** Per-day classification for a month (used by the calendar and dashboard stats). */
function month_overview(array $user, string $ym): array
{
    $uid = (int)$user['id'];
    $first = $ym . '-01';
    $last = date('Y-m-t', strtotime($first));
    $today = today();

    $att = [];
    foreach (q_all('SELECT * FROM attendance WHERE user_id=? AND work_date BETWEEN ? AND ?', [$uid, $first, $last]) as $a) $att[$a['work_date']] = $a;
    $hol = holidays_between($first, $last);
    $leaves = [];
    foreach (q_all('SELECT lr.*, lt.name AS type_name, lt.code, lt.is_paid FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id
                    WHERE lr.user_id=? AND lr.status IN ("approved","pending") AND lr.from_date <= ? AND lr.end_date >= ?', [$uid, $last, $first]) as $l) {
        for ($t = max(strtotime($l['from_date']), strtotime($first)); $t <= min(strtotime($l['end_date']), strtotime($last)); $t += 86400) {
            $d = date('Y-m-d', $t);
            if (!is_weekend($d) && !isset($hol[$d])) $leaves[$d] = $l;
        }
    }
    $wo = [];
    foreach (q_all('SELECT off_date FROM week_offs WHERE user_id=? AND off_date BETWEEN ? AND ?', [$uid, $first, $last]) as $w) $wo[$w['off_date']] = true;
    $regs = [];
    foreach (q_all('SELECT * FROM regularizations WHERE user_id=? AND work_date BETWEEN ? AND ? AND status="pending"', [$uid, $first, $last]) as $r) $regs[$r['work_date']] = $r;

    $days = [];
    for ($t = strtotime($first); $t <= strtotime($last); $t += 86400) {
        $d = date('Y-m-d', $t);
        $row = ['date' => $d, 'type' => 'none', 'att' => $att[$d] ?? null, 'holiday' => $hol[$d] ?? null, 'leave' => $leaves[$d] ?? null, 'reg' => $regs[$d] ?? null];
        if (isset($att[$d])) $row['type'] = 'present';
        elseif (isset($hol[$d])) $row['type'] = 'holiday';
        elseif (isset($wo[$d])) $row['type'] = 'weekoff';
        elseif (is_weekend($d)) $row['type'] = 'weekend';
        elseif (isset($leaves[$d])) $row['type'] = 'leave';
        elseif ($d < $user['joined_on']) $row['type'] = 'none';
        elseif ($d < $today) $row['type'] = 'absent';
        else $row['type'] = 'upcoming';
        $days[$d] = $row;
    }
    return $days;
}

/**
 * Overtime for a month: each present day's overtime is added up; every full shift length (7h on the general shift) of
 * accumulated overtime earns one extra day of payout (7h = 1, 14h = 2 ...). Returns the dated rows too.
 */
function month_overtime(array $days, int $shiftMin): array
{
    $rows = []; $total = 0;
    foreach ($days as $d => $r) {
        $m = $r['type'] === 'present' ? (int)$r['att']['overtime_min'] : 0;
        if ($m > 0) { $rows[$d] = $m; $total += $m; }
    }
    $shiftMin = max(1, $shiftMin);
    return ['rows' => $rows, 'total_min' => $total, 'payouts' => intdiv($total, $shiftMin), 'rem_min' => $total % $shiftMin, 'shift_min' => $shiftMin];
}

/** Aggregated metrics for a month-overview. */
function month_summary(array $days): array
{
    $today = today();
    $sum = ['present' => 0, 'absent' => 0, 'leave' => 0.0, 'holiday' => 0, 'weekend' => 0, 'late' => 0, 'ot_min' => 0, 'worked_min' => 0,
            'paid_leave' => 0.0, 'weekdays' => 0, 'hol_weekday' => 0, 'weekoff' => 0];
    foreach ($days as $d => $r) {
        switch ($r['type']) {
            case 'present':
                $sum['present']++;
                $sum['worked_min'] += (int)$r['att']['worked_min'];
                $sum['ot_min'] += (int)$r['att']['overtime_min'];
                $sum['late'] += (int)$r['att']['is_late'];
                break;
            case 'absent': $sum['absent']++; break;
            case 'leave':
                $v = $r['leave']['half_day'] ? 0.5 : 1.0;
                $sum['leave'] += $v;
                if ($r['leave']['is_paid'] && $r['leave']['status'] === 'approved') $sum['paid_leave'] += $v;
                break;
            case 'holiday': $sum['holiday']++; if (!is_weekend($d)) $sum['hol_weekday']++; break;
            case 'weekend': $sum['weekend']++; break;
            case 'weekoff': $sum['weekoff']++; break;
        }
        if (!is_weekend($d) && $d <= $today && $r['type'] !== 'none') $sum['weekdays']++;
    }
    $payable = $sum['present'] + $sum['hol_weekday'] + $sum['paid_leave'] + $sum['weekoff'];
    $sum['payable'] = $payable;
    $sum['rate'] = $sum['weekdays'] > 0 ? min(100, round($payable / $sum['weekdays'] * 100, 1)) : 0;
    $sum['avg_hours'] = $sum['present'] ? round($sum['worked_min'] / $sum['present'] / 60, 1) : 0;
    return $sum;
}


// ---------- week offs ----------
function is_weekoff(int $uid, string $date): bool
{
    return (bool)q_val('SELECT COUNT(*) FROM week_offs WHERE user_id=? AND off_date=?', [$uid, $date]);
}

/** Week-off credit for a month: 4 per month plus whatever earlier months left unused. */
function weekoff_balance(array $user, string $ym): array
{
    $per = (int)cfg('weekoff_per_month');
    $start = max(substr((string)$user['joined_on'], 0, 7), (string)cfg('weekoff_start'));
    if ($ym < $start) $start = $ym;
    $used = fn(string $m) => (int)q_val('SELECT COUNT(*) FROM week_offs WHERE user_id=? AND DATE_FORMAT(off_date,"%Y-%m")=?', [$user['id'], $m]);
    $carry = 0;
    for ($m = $start; $m < $ym; $m = date('Y-m', strtotime($m . '-01 +1 month'))) $carry = max(0, $carry + $per - $used($m));
    $usedNow = $used($ym);
    return ['per_month' => $per, 'carry' => $carry, 'total' => $per + $carry, 'used' => $usedNow, 'left' => max(0, $per + $carry - $usedNow)];
}

/** Returns an error message if today cannot be marked as a week off, or null when it can. */
function weekoff_check(array $user, string $date): ?string
{
    $uid = (int)$user['id'];
    if ($date !== today()) return 'A week off can only be marked for today.';
    if (is_weekoff($uid, $date)) return 'Today is already marked as a week off.';
    if (day_events($uid, $date)) return 'You have already punched in today.';
    if (holidays_between($date, $date)) return 'Today is a company holiday.';
    if (q_val('SELECT COUNT(*) FROM leave_requests WHERE user_id=? AND status IN ("approved","pending") AND ? BETWEEN from_date AND end_date', [$uid, $date])) return 'You have a leave request covering today.';
    $mon = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $sun = date('Y-m-d', strtotime($mon . ' +6 days'));
    if (q_val('SELECT COUNT(*) FROM week_offs WHERE user_id=? AND off_date BETWEEN ? AND ?', [$uid, $mon, $sun])) return 'You have already taken your week off for this week (1 per week).';
    if (weekoff_balance($user, substr($date, 0, 7))['left'] < 1) return 'No week offs left this month.';
    return null;
}

function weekoff_mark(array $user, string $date): array
{
    if ($err = weekoff_check($user, $date)) return [false, $err];
    q('INSERT INTO week_offs (user_id, off_date) VALUES (?,?)', [$user['id'], $date]);
    log_activity((int)$user['id'], 'check', 'Week Off', 'Marked ' . date('D, M j', strtotime($date)) . ' as a week off.');
    return [true, 'Today is marked as your week off.'];
}

function weekoff_cancel(array $user, string $date): array
{
    if ($date !== today() || !is_weekoff((int)$user['id'], $date)) return [false, 'No week off to cancel.'];
    q('DELETE FROM week_offs WHERE user_id=? AND off_date=?', [$user['id'], $date]);
    return [true, 'Week off cancelled.'];
}
