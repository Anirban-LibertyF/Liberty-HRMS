<?php
declare(strict_types=1);
// Included from public/actions.php for do=admin_*. $user, $uid, $do, $back and done() are in scope.
$isAdmin = $user['role'] === 'admin';
if (!is_staff($user)) done(false, 'Access denied.', 'index.php');

/** Loads the target employee and checks the reviewer may act on them. */
$target = function (int $employeeId) use ($user): array {
    $emp = q_one('SELECT * FROM users WHERE id=?', [$employeeId]);
    if (!$emp || !can_review($user, $emp)) done(false, 'You are not allowed to review this employee.', 'admin.php');
    return $emp;
};
$decision = ($_POST['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';
$note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 300);
$id = (int)($_POST['id'] ?? 0);

switch ($do) {
    case 'admin_leave_review':
        $lr = q_one('SELECT lr.*, lt.name AS type_name FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id WHERE lr.id=? AND lr.status="pending"', [$id]);
        if (!$lr) done(false, 'Request not found or already reviewed.', $back);
        $emp = $target((int)$lr['user_id']);
        q('UPDATE leave_requests SET status=?, reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?', [$decision, $user['id'], $note, $id]);
        notify((int)$emp['id'], "Your {$lr['type_name']} request (" . leave_period($lr) . ") was $decision.", 'leave.php');
        log_activity((int)$emp['id'], 'check', 'Leave ' . $decision, "{$lr['type_name']} · " . leave_period($lr) . " · by {$user['name']}");
        done(true, "Leave request $decision.", $back);

    case 'admin_reg_review':
        $rg = q_one('SELECT * FROM regularizations WHERE id=? AND status="pending"', [$id]);
        if (!$rg) done(false, 'Request not found or already reviewed.', $back);
        $emp = $target((int)$rg['user_id']);
        $pdo = db(); $pdo->beginTransaction();
        try {
            q('UPDATE regularizations SET status=?, reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?', [$decision, $user['id'], $note, $id]);
            if ($decision === 'approved') {
                // Replace that day's punches with the approved in/out so the attendance row recomputes consistently.
                q('DELETE FROM punch_events WHERE user_id=? AND work_date=?', [$emp['id'], $rg['work_date']]);
                foreach ([['in', $rg['req_in']], ['out', $rg['req_out']]] as [$type, $time]) {
                    q('INSERT INTO punch_events (user_id, work_date, event_type, event_time, ip, location, source) VALUES (?,?,?,?,?,?,?)',
                        [$emp['id'], $rg['work_date'], $type, $rg['work_date'] . ' ' . $time, '', 'Regularized by ' . $user['name'], 'Regularization']);
                }
                $full = q_one('SELECT u.*, s.name AS shift_name, s.start_time, s.end_time, s.required_min, s.break_allow_min, s.grace_min FROM users u JOIN shifts s ON s.id=u.shift_id WHERE u.id=?', [$emp['id']]);
                recompute_attendance($full, $rg['work_date']);
                q('UPDATE attendance SET regularized=1 WHERE user_id=? AND work_date=?', [$emp['id'], $rg['work_date']]);
            }
            $pdo->commit();
        } catch (Throwable $ex) { $pdo->rollBack(); throw $ex; }
        notify((int)$emp['id'], 'Your attendance regularization for ' . fmt_date($rg['work_date'], 'M d') . " was $decision.", 'attendance.php?date=' . $rg['work_date'] . '&m=' . substr($rg['work_date'], 0, 7));
        done(true, "Regularization $decision.", $back);

}

// Everything below is administrator-only.
if (!$isAdmin) done(false, 'Administrator role required.', 'admin.php');

switch ($do) {
    case 'admin_user_create':
    case 'admin_user_update':
        $editing = $do === 'admin_user_update';
        $target = $editing ? q_one('SELECT * FROM users WHERE id=? AND role <> "admin"', [$id]) : null;
        if ($editing && !$target) done(false, 'Employee not found.', 'admin.php?tab=employees');
        $formUrl = $editing ? "admin_employee.php?id=$id" : 'admin_employee.php?new=1';
        [$fields, $err] = employee_fields_from_post($_POST, $editing ? $id : null);
        $password = (string)($_POST['password'] ?? '');
        if (!$err && ($editing ? $password !== '' : true) && (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password))) $err = 'Password must be at least 8 characters with letters and numbers.';
        if ($err) { $_SESSION['form_old'] = $fields; done(false, $err, $formUrl); }
        if ($editing) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            q("UPDATE users SET $set WHERE id = ?", [...array_values($fields), $id]);
            if ($password !== '') q('UPDATE users SET password_hash=?, failed_attempts=0, locked_until=NULL WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            done(true, 'Employee details saved.', $formUrl);
        }
        $pdo = db(); $pdo->beginTransaction();
        try {
            $code = next_emp_code();
            $cols = array_keys($fields);
            q('INSERT INTO users (emp_code, password_hash, ' . implode(', ', $cols) . ') VALUES (?, ?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')',
                [$code, password_hash($password, PASSWORD_DEFAULT), ...array_values($fields)]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $ex) { $pdo->rollBack(); throw $ex; }
        notify($newId, 'Welcome to Liberty Office! Your account is ready.', 'index.php');
        $letter = true;
        try { create_offer_letter($newId); notify($newId, 'Your offer letter is available under Documents.', 'documents.php'); }
        catch (Throwable $ex) { error_log('Offer letter failed: ' . $ex->getMessage()); $letter = false; }
        done(true, "Employee created. Employee ID: $code — they can sign in with $code or {$fields['email']}."
            . ($letter ? ' An offer letter was added to their Documents.' : ' The offer letter could not be generated; use "Regenerate offer letter" on this page.'), "admin_employee.php?id=$newId");

    case 'admin_offerletter_regen':
        if (!q_val('SELECT COUNT(*) FROM users WHERE id=? AND role <> "admin"', [$id])) done(false, 'Employee not found.', $back);
        create_offer_letter($id);
        notify($id, 'Your offer letter is available under Documents.', 'documents.php');
        done(true, "Offer letter regenerated and added to the employee's Documents.", $back);

    case 'admin_attendance_save':
        $emp = q_one('SELECT u.*, s.name AS shift_name, s.start_time, s.end_time, s.required_min, s.break_allow_min, s.grace_min FROM users u JOIN shifts s ON s.id=u.shift_id WHERE u.id=? AND u.role <> "admin"', [(int)($_POST['user_id'] ?? 0)]);
        $date = (string)($_POST['work_date'] ?? ''); $status = $_POST['status'] ?? '';
        $reason = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 200);
        $tin = (string)($_POST['time_in'] ?? ''); $tout = (string)($_POST['time_out'] ?? ''); $brk = (int)($_POST['break_min'] ?? 0);
        $backEmp = 'admin_employee.php?id=' . (int)($_POST['user_id'] ?? 0);
        if (!$emp) done(false, 'Employee not found.', $back);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > today()) done(false, 'Choose a valid date that is not in the future.', $back);
        if ($date < $emp['joined_on']) done(false, 'That date is before the employee joined (' . fmt_date($emp['joined_on']) . ').', $back);
        if ($reason === '') done(false, 'Please give a reason for the edit.', $back);
        if (!in_array($status, ['present', 'absent'], true)) done(false, 'Choose a status.', $back);

        $pdo = db(); $pdo->beginTransaction();
        try {
            if ($status === 'present') {
                if (!preg_match('/^\d{2}:\d{2}$/', $tin)) done(false, 'Enter a check-in time.', $back);
                $hasOut = preg_match('/^\d{2}:\d{2}$/', $tout) === 1;
                if (!$hasOut && $date !== today()) done(false, 'Enter a check-out time for past dates.', $back);
                $tIn = strtotime("$date $tin");
                if ($hasOut) {
                    $tOut = strtotime("$date $tout");
                    if ($tOut <= $tIn) done(false, 'Check-out must be after check-in.', $back);
                    if ($brk < 0 || $brk * 60 >= $tOut - $tIn) done(false, 'Break must be shorter than the time between check-in and check-out.', $back);
                } elseif ($brk > 0) { $brk = 0; }
                if ($date === today() && ($tIn > time() || ($hasOut && $tOut > time()))) done(false, 'Times for today cannot be in the future.', $back);
                q('DELETE FROM punch_events WHERE user_id=? AND work_date=?', [$emp['id'], $date]);
                $evs = [['in', $tIn]];
                if ($hasOut) {
                    if ($brk > 0) { $mid = $tIn + intdiv(($tOut - $tIn) - $brk * 60, 2); $evs[] = ['break_start', $mid]; $evs[] = ['break_end', $mid + $brk * 60]; }
                    $evs[] = ['out', $tOut];
                }
                foreach ($evs as [$type, $ts]) {
                    q('INSERT INTO punch_events (user_id, work_date, event_type, event_time, ip, location, source) VALUES (?,?,?,?,?,?,?)',
                        [$emp['id'], $date, $type, date('Y-m-d H:i:s', $ts), '', 'Edited by administrator', 'Admin Edit']);
                }
                recompute_attendance($emp, $date);
                q('UPDATE attendance SET regularized=1 WHERE user_id=? AND work_date=?', [$emp['id'], $date]);
                $summary = "Present $tin" . ($hasOut ? "–$tout" : ' (open)');
            } else {
                q('DELETE FROM punch_events WHERE user_id=? AND work_date=?', [$emp['id'], $date]);
                q('DELETE FROM attendance WHERE user_id=? AND work_date=?', [$emp['id'], $date]);
                $summary = 'Marked absent';
            }
            // an admin edit settles any pending regularization request for that day
            q('UPDATE regularizations SET status="approved", reviewed_by=?, reviewed_at=NOW(), review_note="Resolved by administrator edit" WHERE user_id=? AND work_date=? AND status="pending"', [$user['id'], $emp['id'], $date]);
            q('INSERT INTO activity_log (user_id, icon, title, detail, created_at) VALUES (?,?,?,?,NOW())', [$emp['id'], 'check', 'Attendance edited by administrator', fmt_date($date, 'M d, Y') . ": $summary. Reason: $reason"]);
            $pdo->commit();
        } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
        notify((int)$emp['id'], 'Your attendance for ' . fmt_date($date, 'M d') . " was updated by the administrator ($summary).", 'attendance.php?date=' . $date . '&m=' . substr($date, 0, 7));
        done(true, "Attendance saved for {$emp['name']} on " . fmt_date($date) . " ($summary).", $back);
    case 'admin_user_toggle':
        q('UPDATE users SET status=IF(status="active","inactive","active") WHERE id=? AND role <> "admin"', [$id]);
        done(true, 'Account status updated.', $back);

    case 'admin_user_unlock':
        q('UPDATE users SET failed_attempts=0, locked_until=NULL WHERE id=? AND role <> "admin"', [$id]);
        done(true, 'Sign-in unlocked.', $back);

    case 'admin_holiday_edit':
        $d = (string)($_POST['holiday_date'] ?? ''); $n = trim((string)($_POST['name'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $n === '') done(false, 'Provide a date and a name.', $back);
        if (q_val('SELECT COUNT(*) FROM holidays WHERE holiday_date=? AND id<>?', [$d, $id])) done(false, 'Another holiday already exists on that date.', $back);
        q('UPDATE holidays SET holiday_date=?, name=?, note=? WHERE id=?', [$d, mb_substr($n, 0, 120), mb_substr(trim((string)($_POST['note'] ?? '')), 0, 150), $id]);
        done(true, 'Holiday updated.', $back);

    case 'admin_holiday_add':
        $d = (string)($_POST['holiday_date'] ?? ''); $n = trim((string)($_POST['name'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $n === '') done(false, 'Provide a date and a name.', $back);
        q('INSERT INTO holidays (holiday_date, name, note) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), note=VALUES(note)', [$d, mb_substr($n, 0, 120), mb_substr(trim((string)($_POST['note'] ?? 'Corporate Paid')), 0, 150)]);
        done(true, 'Holiday saved.', $back);
    case 'admin_holiday_delete':
        q('DELETE FROM holidays WHERE id=?', [$id]);
        done(true, 'Holiday removed.', $back);

    case 'admin_announce_add':
        $t = trim((string)($_POST['title'] ?? '')); $b = trim((string)($_POST['body'] ?? ''));
        if ($t === '' || $b === '') done(false, 'Title and message are required.', $back);
        q('INSERT INTO announcements (dept, title, body, posted_on, created_by) VALUES (?,?,?,CURDATE(),?)', [mb_substr(trim((string)($_POST['dept'] ?? 'HR')), 0, 60), mb_substr($t, 0, 200), mb_substr($b, 0, 1000), $user['id']]);
        foreach (q_all('SELECT id FROM users WHERE status="active"') as $r) notify((int)$r['id'], 'New announcement: ' . mb_substr($t, 0, 80), 'index.php');
        done(true, 'Announcement published.', $back);
    case 'admin_announce_delete':
        q('DELETE FROM announcements WHERE id=?', [$id]);
        done(true, 'Announcement deleted.', $back);

    case 'admin_payroll_run':
        $m = (string)($_POST['month'] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})$/', $m, $mm) || $m > date('Y-m')) done(false, 'Choose a valid month (not in the future).', $back);
        $n = 0; $skipped = [];
        $label = date('F Y', strtotime("$m-01"));
        foreach (q_all('SELECT * FROM users WHERE status="active" AND role <> "admin"') as $emp) {
            if (generate_payslip($emp, (int)$mm[1], (int)$mm[2])) {
                $n++;
                notify((int)$emp['id'], "Your payslip for $label is available.", 'payslips.php');
            } else {
                $skipped[] = $emp['emp_code'];
            }
        }
        $msg = "Payslips generated for $n employee(s) for $label.";
        if ($skipped) $msg .= ' Skipped (joined after this month): ' . implode(', ', $skipped) . '.';
        done($n > 0, $n > 0 ? $msg : "No payslips generated for $label. " . ($skipped ? 'All active employees joined after this month: ' . implode(', ', $skipped) . '.' : 'No active employees.'), $back);

    default:
        done(false, 'Unknown admin action.', $back);
}
