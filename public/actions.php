<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST only.'); }
$user = require_login();
csrf_check();

$uid = (int)$user['id'];
$do = (string)($_POST['do'] ?? '');
$back = safe_next($_POST['back'] ?? null, 'index.php');

/** Responds as JSON for fetch() calls, or flashes + redirects for normal form posts. */
function done(bool $ok, string $msg, string $to, array $extra = []): never
{
    if (is_ajax()) json_out($ok, $msg, $extra);
    flash($ok ? 'ok' : 'error', $msg);
    redirect($to);
}

try {
    switch ($do) {
        // ---------------- punching ----------------
        case 'punch':
            $st = today_status($user)['state'];
            [$ok, $msg] = do_punch($user, in_array($st, ['in', 'break'], true) ? 'out' : 'in');
            done($ok, $msg, $back);
        case 'punch_in':
        case 'punch_out':
        case 'break_start':
        case 'break_end':
            $loc = trim((string)($_POST['location'] ?? ''));
            if ($do === 'punch_in' && $loc === '') done(false, 'Please enter your location to punch in.', $back);
            [$ok, $msg] = do_punch($user, str_replace('punch_', '', $do), $do === 'punch_in' ? $loc : '');
            done($ok, $msg, $back);

        case 'weekoff_mark':
            [$ok, $msg] = weekoff_mark($user, today());
            done($ok, $msg, $back);
        case 'weekoff_cancel':
            [$ok, $msg] = weekoff_cancel($user, today());
            done($ok, $msg, $back);

        // ---------------- notifications ----------------
        case 'notif_read':
            q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$uid]);
            json_out(true);

        case 'announcement_read':
            q("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0 AND message LIKE 'New announcement:%'", [$uid]);
            json_out(true, '', ['unread' => (int)q_val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$uid])]);

        // ---------------- regularization ----------------
        case 'reg_request':
            $date = (string)($_POST['work_date'] ?? '');
            $in = (string)($_POST['req_in'] ?? '');
            $out = (string)($_POST['req_out'] ?? '');
            $reason = trim((string)($_POST['reason'] ?? ''));
            $to = 'attendance.php?date=' . urlencode($date) . '&m=' . substr($date, 0, 7);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > today()) done(false, 'Choose a valid date that is not in the future.', $to);
            if (is_weekend($date) || holidays_between($date, $date)) done(false, 'That day is a weekend or holiday; no regularization needed.', $to);
            if ($date < date('Y-m-d', strtotime('-60 days'))) done(false, 'Regularization is limited to the last 60 days.', $to);
            if (!preg_match('/^\d{2}:\d{2}$/', $in) || !preg_match('/^\d{2}:\d{2}$/', $out) || $out <= $in) done(false, 'Provide a valid in/out time (out must be after in).', $to);
            if (mb_strlen($reason) < 5) done(false, 'Please give a reason (at least 5 characters).', $to);
            if (q_val('SELECT COUNT(*) FROM regularizations WHERE user_id=? AND work_date=? AND status="pending"', [$uid, $date])) done(false, 'A regularization for this date is already pending.', $to);
            q('INSERT INTO regularizations (user_id, work_date, req_in, req_out, reason) VALUES (?,?,?,?,?)', [$uid, $date, $in . ':00', $out . ':00', mb_substr($reason, 0, 300)]);
            if ($user['manager_id']) notify((int)$user['manager_id'], "{$user['name']} requested attendance regularization for " . fmt_date($date, 'M d') . '.', 'admin.php?tab=approvals');
            foreach (q_all('SELECT id FROM users WHERE role="admin" AND id<>?', [$uid]) as $a) notify((int)$a['id'], "{$user['name']} requested attendance regularization for " . fmt_date($date, 'M d') . '.', 'admin.php?tab=approvals');
            log_activity($uid, 'check', 'Regularization requested', fmt_date($date, 'M d, Y') . " ($in – $out)");
            done(true, 'Regularization request sent for approval.', $to);

        // ---------------- leave ----------------
        case 'leave_apply':
        case 'leave_draft':
            $isDraft = $do === 'leave_draft';
            $type = q_one('SELECT * FROM leave_types WHERE id=?', [(int)($_POST['leave_type_id'] ?? 0)]);
            $from = (string)($_POST['from_date'] ?? '');
            $to = (string)($_POST['end_date'] ?? '') ?: $from;
            $half = !empty($_POST['half_day']);
            if ($half) $to = $from;
            $reason = trim((string)($_POST['reason'] ?? ''));
            $draftId = (int)($_POST['draft_id'] ?? 0) ?: null;
            if ($draftId && !q_val('SELECT id FROM leave_requests WHERE id=? AND user_id=? AND status="draft"', [$draftId, $uid])) $draftId = null;
            if (!$type) done(false, 'Select a leave type.', 'leave.php');
            if ($err = validate_leave($user, $type, $from, $to, $half, $reason, $draftId)) {
                if (!$isDraft || !$from) done(false, $err, 'leave.php' . ($draftId ? "?draft=$draftId" : ''));
            }
            $docName = null; $docPath = null;
            if (!empty($_FILES['doc']) && $_FILES['doc']['error'] !== UPLOAD_ERR_NO_FILE) [$docName, $docPath] = store_upload($_FILES['doc'], "leave/$uid");
            $days = calc_leave_days($from, $to, $half);
            $status = $isDraft ? 'draft' : 'pending';
            if ($draftId) {
                q('UPDATE leave_requests SET leave_type_id=?, from_date=?, end_date=?, days=?, half_day=?, reason=?, status=?, applied_at=NOW(), doc_name=COALESCE(?,doc_name), doc_path=COALESCE(?,doc_path) WHERE id=?',
                    [$type['id'], $from, $to, $days, (int)$half, mb_substr($reason, 0, 300), $status, $docName, $docPath, $draftId]);
            } else {
                q('INSERT INTO leave_requests (user_id, leave_type_id, from_date, end_date, days, half_day, reason, doc_name, doc_path, status, applied_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())',
                    [$uid, $type['id'], $from, $to, $days, (int)$half, mb_substr($reason, 0, 300), $docName, $docPath, $status]);
            }
            if ($isDraft) done(true, 'Draft saved.', 'leave.php');
            $fromL = fmt_date($from, 'M d'); $toL = fmt_date($to, 'M d');
            foreach (q_all('SELECT id FROM users WHERE status="active" AND id<>? AND (role="admin" OR id=?)', [$uid, (int)$user['manager_id']]) as $r) {
                notify((int)$r['id'], "{$user['name']} applied for {$type['name']} ($fromL - $toL).", 'admin.php?tab=approvals');
            }
            log_activity($uid, 'check', 'Leave requested', "{$type['name']}: $fromL" . ($from !== $to ? " - $toL" : '') . " ($days day(s))");
            $_SESSION['leave_ok'] = $from === $to ? "Leave request for $fromL submitted successfully." : "Leave request for $fromL - $toL submitted successfully.";
            redirect('leave.php');

        case 'leave_cancel':
            $id = (int)($_POST['id'] ?? 0);
            $n = q('UPDATE leave_requests SET status="cancelled" WHERE id=? AND user_id=? AND status IN ("pending","draft")', [$id, $uid])->rowCount();
            done((bool)$n, $n ? 'Leave request cancelled.' : 'Only pending requests can be cancelled.', 'leave.php');

        // ---------------- work log ----------------
        case 'worklog_save':
        case 'worklog_submit':
        case 'worklog_attach':
        case 'worklog_detach':
            $date = (string)($_POST['log_date'] ?? today());
            $wlBack = safe_next($_POST['back'] ?? null, 'worklog.php?date=' . $date);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > today() || $date < date('Y-m-d', strtotime('-14 days'))) done(false, 'Work logs can be edited for the last 14 days only.', $wlBack);
            $row = q_one('SELECT * FROM work_logs WHERE user_id=? AND log_date=?', [$uid, $date]);
            if ($row && in_array($row['status'], ['submitted', 'approved'], true)) done(false, 'This log was already submitted and is locked.', $wlBack);
            $content = mb_substr(trim((string)($_POST['content'] ?? ($row['content'] ?? ''))), 0, 4000);
            $tag = mb_substr(trim((string)($_POST['tag'] ?? ($row['tag'] ?? ''))), 0, 100);

            if ($do === 'worklog_detach') {
                if ($row) q('UPDATE work_logs SET attach_name=NULL, attach_path=NULL, updated_at=NOW() WHERE id=?', [$row['id']]);
                json_out(true, 'Attachment removed.');
            }
            $aName = $row['attach_name'] ?? null; $aPath = $row['attach_path'] ?? null;
            if ($do === 'worklog_attach' || (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE)) {
                [$aName, $aPath] = store_upload($_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE], "worklogs/$uid");
            }
            if ($do === 'worklog_submit' && mb_strlen($content) < 5) done(false, 'Write at least a short summary before submitting.', $wlBack);
            $status = $do === 'worklog_submit' ? 'submitted' : 'draft';
            q('INSERT INTO work_logs (user_id, log_date, content, tag, attach_name, attach_path, status, updated_at) VALUES (?,?,?,?,?,?,?,NOW())
               ON DUPLICATE KEY UPDATE content=VALUES(content), tag=VALUES(tag), attach_name=VALUES(attach_name), attach_path=VALUES(attach_path), status=VALUES(status), updated_at=NOW()',
                [$uid, $date, $content, $tag, $aName, $aPath, $status]);
            if ($do === 'worklog_submit') {
                if ($user['manager_id']) notify((int)$user['manager_id'], "{$user['name']} submitted the work log for " . fmt_date($date, 'M d') . '.', 'admin.php?tab=logs');
                log_activity($uid, 'log', 'Work Log Submitted', 'Daily log for ' . fmt_date($date, 'M d') . ' submitted.');
                done(true, 'Work log submitted.', $wlBack);
            }
            done(true, $do === 'worklog_attach' ? 'Attachment added.' : 'Draft saved.', $wlBack, ['name' => $aName]);

        // ---------------- profile ----------------
        case 'profile_update':
            $phone = preg_replace('/[^\d+\-\s()]/', '', (string)($_POST['phone'] ?? ''));
            $ph2 = fn(string $k) => mb_substr(preg_replace('/[^\d+\-\s()]/', '', (string)($_POST[$k] ?? '')), 0, 30);
            q('UPDATE users SET phone=?, address=?, emergency_name=?, emergency_phone=? WHERE id=?', [mb_substr($phone, 0, 30), mb_substr(trim((string)($_POST['address'] ?? '')), 0, 300), mb_substr(trim((string)($_POST['emergency_name'] ?? '')), 0, 120), $ph2('emergency_phone'), $uid]);
            done(true, 'Profile updated.', 'profile.php');
        case 'profile_photo_upload':
            $photo = store_profile_photo($_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], $uid);
            try {
                q('UPDATE users SET profile_photo=? WHERE id=?', [$photo, $uid]);
            } catch (Throwable $e) {
                @unlink(cfg('upload_dir') . '/' . $photo);
                throw $e;
            }
            if (!empty($user['profile_photo'])) {
                $oldPhoto = cfg('upload_dir') . '/' . $user['profile_photo'];
                if (is_file($oldPhoto) && !@unlink($oldPhoto)) error_log('Could not remove replaced profile photo for user ' . $uid);
            }
            done(true, 'Profile photo updated.', 'profile.php');
        case 'password_change':
            $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['new'] ?? ''); $new2 = (string)($_POST['new2'] ?? '');
            if (!password_verify($cur, $user['password_hash'])) done(false, 'Current password is incorrect.', 'profile.php#security');
            if (strlen($new) < 10 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) done(false, 'New password must be at least 10 characters and include letters and numbers.', 'profile.php#security');
            if ($new !== $new2) done(false, 'New passwords do not match.', 'profile.php#security');
            q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            done(true, 'Password changed successfully.', 'profile.php#security');

        // ---------------- documents ----------------
        case 'doc_upload':
            $title = trim((string)($_POST['title'] ?? ''));
            $cat = trim((string)($_POST['category'] ?? 'General'));
            if ($title === '') done(false, 'Give the document a title.', 'documents.php');
            [$name, $path] = store_upload($_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE], "docs/$uid");
            q('INSERT INTO documents (user_id, title, category, file_name, file_path, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,?,NOW())', [$uid, mb_substr($title, 0, 200), mb_substr($cat, 0, 60), $name, $path, $uid]);
            done(true, 'Document uploaded.', 'documents.php');
        case 'doc_delete':
            $d = q_one('SELECT * FROM documents WHERE id=? AND user_id=? AND uploaded_by=?', [(int)($_POST['id'] ?? 0), $uid, $uid]);
            if ($d) { q('DELETE FROM documents WHERE id=?', [$d['id']]); @unlink(cfg('upload_dir') . '/' . $d['file_path']); }
            done((bool)$d, $d ? 'Document deleted.' : 'Document not found.', 'documents.php');

        default:
            if (str_starts_with($do, 'admin_')) { require __DIR__ . '/../app/admin_actions.php'; exit; }
            done(false, 'Unknown action.', $back);
    }
} catch (RuntimeException $e) {
    done(false, $e->getMessage(), $back);
} catch (Throwable $e) {
    error_log($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    done(false, 'Something went wrong. Please try again.', $back);
}
