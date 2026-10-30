<?php
declare(strict_types=1);
/**
 * CLI: php -c php.ini database/setup.php [--no-demo]
 * Creates the database + app user, (re)creates all tables and loads demo data.
 * WARNING: drops and recreates every Liberty Office table.
 */
if (PHP_SAPI !== 'cli') exit('CLI only.');
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/attendance_lib.php';
require __DIR__ . '/../app/leave_lib.php';
require __DIR__ . '/../app/payroll_lib.php';
require __DIR__ . '/../app/admin_lib.php';
require __DIR__ . '/../app/offer_letter.php';

$demo = !in_array('--no-demo', $argv, true);
$c = cfg('db');

// 1. database + dedicated app user (needs a MySQL admin login; defaults to root without password)
$root = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", getenv('TF_ROOT_USER') ?: 'root', getenv('TF_ROOT_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec("CREATE DATABASE IF NOT EXISTS `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$root->exec("CREATE USER IF NOT EXISTS '{$c['user']}'@'localhost' IDENTIFIED BY '{$c['pass']}'");
$root->exec("CREATE USER IF NOT EXISTS '{$c['user']}'@'127.0.0.1' IDENTIFIED BY '{$c['pass']}'");
$root->exec("GRANT ALL PRIVILEGES ON `{$c['name']}`.* TO '{$c['user']}'@'localhost'");
$root->exec("GRANT ALL PRIVILEGES ON `{$c['name']}`.* TO '{$c['user']}'@'127.0.0.1'");
$root->exec('FLUSH PRIVILEGES');

// 2. schema
db()->exec(file_get_contents(__DIR__ . '/schema.sql'));
echo "Schema created.\n";

// 3. reference data (always loaded)
q('INSERT INTO shifts (id, name, label, start_time, end_time, required_min, break_allow_min, grace_min) VALUES (1,"General","Standard 7H Shift","10:30:00","17:30:00",420,60,15)');
foreach ([['SL', 'Sick Leave', 'Active Cycle', 8, 1, '#c62828', 1], ['PL', 'Paid Leave', 'Calendar Year', 12, 1, '#8e1b1b', 2],
          ['LOP', 'Unpaid / LOP', 'Zero Impact', 0, 0, '#94a3b8', 3]] as $t) {
    q('INSERT INTO leave_types (code, name, badge, quota, is_paid, color, sort_order) VALUES (?,?,?,?,?,?,?)', $t);
}
foreach ([['2026-01-01', "New Year's Day"], ['2026-01-26', 'Republic Day'], ['2026-03-04', 'Holi'], ['2026-08-15', 'Independence Day'], ['2026-10-02', 'Gandhi Jayanti'],
          ['2026-10-20', 'Dussehra'], ['2026-11-09', 'Diwali Holiday'], ['2026-12-25', 'Christmas Day'], ['2026-12-31', 'Year-End Shutdown'],
          ['2027-01-01', "New Year's Day"], ['2027-01-26', 'Republic Day']] as [$d, $n]) {
    q('INSERT INTO holidays (holiday_date, name, note) VALUES (?,?,"Corporate Paid")', [$d, $n]);
}

$mk = function (string $code, string $name, string $email, string $pw, string $role, string $dept, string $team, string $title, ?int $mgr, string $joined, float $salary) {
    q('INSERT INTO users (emp_code, name, email, password_hash, role, department, team, designation, manager_id, shift_id, joined_on, base_salary) VALUES (?,?,?,?,?,?,?,?,?,1,?,?)',
        [$code, $name, $email, password_hash($pw, PASSWORD_DEFAULT), $role, $dept, $team, $title, $mgr, $joined, $salary]);
    return (int)db()->lastInsertId();
};

$admin = $mk('ADMIN', 'Liberty Admin', 'liberty.admin@liberty.com', 'Liberty@2008', 'admin', 'Administration', 'Admin', 'System Administrator', null, '2020-01-06', 0);
if (!$demo) { echo "Done (no demo data). Admin login: liberty.admin@liberty.com / Liberty@2008 (use the Administrator toggle on the login page).\n"; exit; }

// 4. demo employees
$pw = 'Welcome@123';
$sarah = $mk(next_emp_code(), 'Sarah Jenkins', 'sarah.jenkins@enterprise.corp', $pw, 'manager', 'Engineering', 'Product Core Team', 'Lead Product Eng', $admin, '2021-03-15', 2400000);
$alex = $mk(next_emp_code(), 'Alex Morgan', 'alex.morgan@enterprise.corp', $pw, 'employee', 'Engineering', 'UI Core Team', 'Senior UI Engineer', $sarah, '2022-06-01', 1800000);
$marcus = $mk(next_emp_code(), 'Marcus Vance', 'marcus.vance@enterprise.corp', $pw, 'employee', 'Engineering', 'Platform Team', 'Lead Staff Engineer', $sarah, '2020-09-14', 2600000);
$priya = $mk(next_emp_code(), 'Priya Nair', 'priya.nair@enterprise.corp', $pw, 'employee', 'Design', 'UX Research', 'Product Designer', $sarah, '2023-02-20', 1500000);
$david = $mk(next_emp_code(), 'David Kim', 'david.kim@enterprise.corp', $pw, 'employee', 'Finance', 'Payroll', 'Payroll Analyst', $admin, '2023-08-07', 1300000);
$test = $mk(next_emp_code(), 'Test Employee', 'test.employee@enterprise.corp', 'Test@12345', 'employee', 'Engineering', 'QA Team', 'QA Engineer', $sarah, date('Y-m-d'), 900000);
$employees = [$sarah, $alex, $marcus, $priya, $david];
// richer profile details for the demo people
foreach ([$sarah => ['1989-04-12','Female','O+','12 Lake View Road, Pune','Tom Jenkins','+91 98200 11111'], $alex => ['1992-09-03','Male','B+','45 Green Park, Pune','Jamie Morgan','+91 98200 22222'], $marcus => ['1987-01-25','Male','A+','8 Hill Street, Pune','Dana Vance','+91 98200 33333'], $priya => ['1995-07-18','Female','AB+','23 Orchid Lane, Pune','Anil Nair','+91 98200 44444'], $david => ['1993-11-30','Male','O-','90 River Road, Pune','Eun Kim','+91 98200 55555']] as $id => $d) {
    q('UPDATE users SET dob=?, gender=?, blood_group=?, address=?, emergency_name=?, emergency_phone=?, phone=? WHERE id=?', [$d[0], $d[1], $d[2], $d[3], $d[4], $d[5], '+91 99000 ' . (10000 + $id), $id]);
}

$today = today();
$workday = function (string $from, int $dir = 1): string {          // nearest working day on/after (dir=1) or on/before (dir=-1)
    while (is_weekend($from) || holidays_between($from, $from)) $from = date('Y-m-d', strtotime("$from " . ($dir > 0 ? '+1' : '-1') . ' day'));
    return $from;
};
$addWork = function (string $from, int $n) use ($workday): string {   // date after n-1 further working days
    $d = $from;
    for ($i = 1; $i < $n; $i++) $d = $workday(date('Y-m-d', strtotime("$d +1 day")));
    return $d;
};
$typeId = fn(string $code) => (int)q_val('SELECT id FROM leave_types WHERE code=?', [$code]);

// 5. leave history  [user, type, start offset (days), working days, half, status, reason, note]
$leaves = [
    [$alex, 'PL', -60, 3, 0, 'approved', 'Summer holiday trip', 'Enjoy!'], [$alex, 'PL', -38, 1, 1, 'approved', 'Personal bank appointment', ''],
    [$alex, 'SL', -14, 2, 0, 'approved', 'Viral infection with bed rest advised', 'Get well soon.'], [$alex, 'PL', -22, 1, 0, 'rejected', 'Family function', 'Release deadline that day, please reschedule.'],
    [$alex, 'PL', 3, 2, 0, 'pending', 'Family commitment out of town', ''],
    [$marcus, 'PL', -30, 3, 0, 'approved', 'Family wedding event', ''], [$marcus, 'PL', 5, 2, 0, 'pending', 'Long weekend break', ''],
    [$priya, 'SL', -9, 1, 0, 'approved', 'Migraine', ''], [$sarah, 'PL', -45, 4, 0, 'approved', 'Annual vacation', ''], [$david, 'LOP', -20, 1, 0, 'approved', 'Personal work', ''],
];
foreach ($leaves as [$uid, $code, $off, $n, $half, $status, $reason, $note]) {
    $from = $workday(date('Y-m-d', strtotime("$today $off days")));
    $to = $half ? $from : $addWork($from, $n);
    $days = calc_leave_days($from, $to, (bool)$half);
    q('INSERT INTO leave_requests (user_id, leave_type_id, from_date, end_date, days, half_day, reason, status, applied_at, reviewed_by, reviewed_at, review_note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$uid, $typeId($code), $from, $to, $days, $half, $reason, $status, date('Y-m-d H:i:s', strtotime("$from -3 days 10:15")),
         $status === 'pending' ? null : $sarah, $status === 'pending' ? null : date('Y-m-d H:i:s', strtotime("$from -2 days 15:40")), $note]);
}
$approvedLeave = [];
foreach (q_all('SELECT user_id, from_date, end_date FROM leave_requests WHERE status="approved"') as $l) {
    for ($t = strtotime($l['from_date']); $t <= strtotime($l['end_date']); $t += 86400) $approvedLeave[$l['user_id']][date('Y-m-d', $t)] = true;
}

// 6. simulated punch history
mt_srand(2026);
$full = fn(int $id) => q_one('SELECT u.*, s.name AS shift_name, s.label AS shift_label, s.start_time, s.end_time, s.required_min, s.break_allow_min, s.grace_min FROM users u JOIN shifts s ON s.id=u.shift_id WHERE u.id=?', [$id]);
$ins = $pdo = db()->prepare('INSERT INTO punch_events (user_id, work_date, event_type, event_time, ip, location, source) VALUES (?,?,?,?,?,?,"Biometric Terminal")');
foreach ($employees as $uid) {
    $u = $full($uid);
    $start = max($u['joined_on'], date('Y-m-d', strtotime("$today -75 days")));
    for ($t = strtotime($start); $t < strtotime($today); $t += 86400) {
        $d = date('Y-m-d', $t);
        if (is_weekend($d) || holidays_between($d, $d) || isset($approvedLeave[$uid][$d])) continue;
        $roll = mt_rand(1, 100);
        if ($roll <= 4) continue;                                       // genuine absence
        $inMin = $roll > 90 ? mt_rand(16, 40) : mt_rand(-12, 14);       // ~10% late
        $in = strtotime("$d 10:30:00") + $inMin * 60;
        $bs = strtotime("$d 12:30:00") + mt_rand(0, 45) * 60;
        $be = $bs + mt_rand(25, 50) * 60;
        $out = $in + (420 * 60) + ($be - $bs) + mt_rand(-5, 40) * 60;
        $ip = '192.168.1.' . mt_rand(20, 80);
        foreach ([['in', $in], ['break_start', $bs], ['break_end', $be], ['out', $out]] as [$type, $ts]) $ins->execute([$uid, $d, $type, date('Y-m-d H:i:s', $ts), $ip, 'Barrackpore Office']);
        recompute_attendance($u, $d);
    }
}
// one missed day for Alex so the calendar shows an Absent cell + a pending regularization
$missed = $workday(date('Y-m-d', strtotime("$today -4 days")), -1);
q('DELETE FROM punch_events WHERE user_id=? AND work_date=?', [$alex, $missed]);
q('DELETE FROM attendance WHERE user_id=? AND work_date=?', [$alex, $missed]);
echo "Attendance history generated.\n";

// 7. work logs
$samples = [
    ['Sprint 34: UI Foundation', "- Completed high-fidelity wireframes for the attendance punch drawer module.\n- Reviewed and closed 4 design QA tickets for the mobile roster card view."],
    ['Navigation Component Tokenization', "- Migrated navigation components to design tokens.\n- Paired with platform team on theming bugs."],
    ['Sprint 34: Accessibility', "- Fixed keyboard focus order on the leave form.\n- Added aria-labels to icon buttons and verified with screen reader."],
    ['Release Prep', "- Cut release candidate, ran regression suite.\n- Updated changelog and deployment checklist."],
];
foreach ($employees as $uid) {
    $n = 0;
    for ($i = 1; $i <= 12; $i++) {
        $d = date('Y-m-d', strtotime("$today -$i days"));
        if (!q_val('SELECT COUNT(*) FROM attendance WHERE user_id=? AND work_date=?', [$uid, $d])) continue;
        $s = $samples[($uid + $i) % count($samples)];
        $status = $i <= 2 ? 'submitted' : 'approved';
        if ($uid === $alex && $i === 1) $status = 'approved';
        q('INSERT IGNORE INTO work_logs (user_id, log_date, content, tag, status, reviewed_by, reviewed_at, updated_at) VALUES (?,?,?,?,?,?,?,?)',
            [$uid, $d, $s[1], $s[0], $status, $status === 'approved' ? $sarah : null, $status === 'approved' ? "$d 18:45:00" : null, "$d 18:30:00"]);
    }
}
// today's draft for Alex (shown on the dashboard)
q('INSERT INTO work_logs (user_id, log_date, content, tag, status, updated_at) VALUES (?,?,?,?,"draft",NOW())',
    [$alex, $today, "- Completed high-fidelity wireframes for the attendance punch drawer module.\n- Reviewed and closed 4 design QA tickets for the mobile roster card view.", 'Sprint 34: UI Foundation']);

// 8. announcements, activity, notifications
q('INSERT INTO announcements (dept, title, body, posted_on, created_by) VALUES (?,?,?,?,?)', ['Payroll Dept', 'Tax Declaration Cutoff Date for Q4', 'Please submit investment proofs and medical reimbursement slips by October 31 via the Documents portal.', date('Y-m-d', strtotime('-3 days')), $admin]);
q('INSERT INTO announcements (dept, title, body, posted_on, created_by) VALUES (?,?,?,?,?)', ['Operations', 'Biometric Scanner Maintenance â€“ Floor 4', 'Turnstiles on East Wing will be serviced on Friday evening. Please use the mobile app geo-punch as fallback.', date('Y-m-d', strtotime('-5 days')), $admin]);
q('INSERT INTO announcements (dept, title, body, posted_on, created_by) VALUES (?,?,?,?,?)', ['HR Operations', 'Welcome to Liberty Office', 'Punch in and out, apply for leave and submit daily work logs from one place. Contact HR Operations with any questions.', date('Y-m-d', strtotime('-20 days')), $admin]);

q('INSERT INTO activity_log (user_id, icon, title, detail, created_at) VALUES (?,?,?,?,?)', [$alex, 'log', 'Work Log Approved (Yesterday)', 'Sprint manager Sarah Jenkins signed off on daily log.', date('Y-m-d H:i:s', strtotime('yesterday 18:45'))]);
q('INSERT INTO activity_log (user_id, icon, title, detail, created_at) VALUES (?,?,?,?,?)', [$alex, 'check', 'Leave approved', 'Sick Leave approved by Sarah Jenkins.', date('Y-m-d H:i:s', strtotime('-12 days 11:05'))]);
foreach ([$sarah, $admin] as $rv) {
    notify($rv, 'Alex Morgan applied for Paid Leave.', 'admin.php?tab=approvals');
    notify($rv, 'Marcus Vance applied for Paid Leave.', 'admin.php?tab=approvals');
}
notify($alex, 'Welcome to Liberty Office! Your account is ready.', 'index.php');
notify($alex, 'Tax Declaration cutoff is October 31.', 'index.php');

// pending regularization for Alex's missed day
q('INSERT INTO regularizations (user_id, work_date, req_in, req_out, reason) VALUES (?,?,"10:35:00","17:40:00","Badge reader offline on my floor; worked the full day.")', [$alex, $missed]);
notify($sarah, 'Alex Morgan requested attendance regularization for ' . fmt_date($missed, 'M d') . '.', 'admin.php?tab=approvals');

// offer letters for every demo employee
foreach (q_all('SELECT id FROM users WHERE role <> "admin"') as $e2) create_offer_letter((int)$e2['id']);

// 9. payslips for the last completed month (the simulated attendance covers ~75 days)
foreach (q_all('SELECT * FROM users WHERE role <> "admin"') as $emp) {
    for ($i = 1; $i >= 1; $i--) {
        $m = strtotime(date('Y-m-01') . " -$i months");
        generate_payslip($emp, (int)date('Y', $m), (int)date('n', $m));
    }
}

echo "Demo data loaded.\n\nLogins (EMP ID or email):\n  liberty.admin@liberty.com  Liberty@2008  (administrator - use the Administrator toggle)\n  LOE-001  Welcome@123   (manager: Sarah Jenkins)\n  LOE-002  Welcome@123   (employee: Alex Morgan)\n  LOE-003  Welcome@123   (employee: Marcus Vance)\n";
