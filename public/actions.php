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
        case 'notif_clear':
            q('DELETE FROM notifications WHERE user_id=?', [$uid]);
            json_out(true);

        case 'notif_read':
            q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$uid]);
            json_out(true);

        case 'announcement_read':
            q("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0 AND (message LIKE 'New announcement:%' OR message LIKE 'Announcement updated:%')", [$uid]);
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
        case 'nda_upload':
            if (!q_val('SELECT COUNT(*) FROM documents WHERE user_id=? AND category="NDA Letter"', [$uid])) done(false, 'Your NDA is not available yet.', 'documents.php');
            [$name, $path] = store_upload($_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE], "docs/$uid");
            foreach (q_all('SELECT id, file_path FROM documents WHERE user_id=? AND category="NDA Signed"', [$uid]) as $old) {   // newest upload replaces the previous one
                q('DELETE FROM documents WHERE id=?', [$old['id']]);
                @unlink(cfg('upload_dir') . '/' . $old['file_path']);
            }
            q('INSERT INTO documents (user_id, title, category, file_name, file_path, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,?,NOW())', [$uid, 'Signed NDA', 'NDA Signed', $name, $path, $uid]);
            foreach (q_all('SELECT id FROM users WHERE role="admin" AND status="active"') as $adm) notify((int)$adm['id'], "{$user['name']} uploaded the signed NDA.", 'admin_employee.php?id=' . $uid);
            done(true, 'Signed NDA uploaded.', 'documents.php');
        case 'doc_delete':
            $d = q_one('SELECT * FROM documents WHERE id=? AND user_id=? AND uploaded_by=?', [(int)($_POST['id'] ?? 0), $uid, $uid]);
            if ($d) { q('DELETE FROM documents WHERE id=?', [$d['id']]); @unlink(cfg('upload_dir') . '/' . $d['file_path']); }
            done((bool)$d, $d ? 'Document deleted.' : 'Document not found.', 'documents.php');

        // ---------------- projects ----------------
        case 'project_save':
            $pid = (int)($_POST['id'] ?? 0);
            $p = $pid ? project_load($pid) : null;
            $to = $pid ? "project.php?id=$pid" : 'project.php';
            if ($pid && (!$p || !project_can_access($p, $user))) done(false, 'Project not found.', 'projects.php');
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') done(false, 'Give the project a name.', $to);
            $txt = fn(string $k, int $n) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $n);
            $url = function (string $k, string $label) use ($txt, $to): string {
                $u = $txt($k, 500);
                if ($u !== '' && (!filter_var($u, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $u))) done(false, "$label link must be a full http(s) URL.", $to);
                return $u;
            };
            $vals = [mb_substr($name, 0, 160), $txt('short_details', 300), $txt('tech_stack', 300), $txt('description', 5000),
                     max(0, min(100, (int)($_POST['progress'] ?? 0))), $txt('notes', 5000), secret_encrypt(mb_substr((string)($_POST['secrets'] ?? ''), 0, 8000)),
                     $url('github_url', 'GitHub'), $url('figma_url', 'Figma')];
            if ($user['role'] === 'admin' && isset($_POST['members_present']) && ($_POST['project_type'] ?? 'group') === 'single' && count(array_unique(array_map('intval', (array)($_POST['members'] ?? [])))) > 1) done(false, 'A single project can have only one employee. Pick one, or switch the type to Group.', $to);
            $uploads = project_collect_uploads();
            foreach ($uploads as $up) project_check_file($up);
            $pdo = db(); $pdo->beginTransaction(); $moved = []; $gone = []; $invited = [];
            try {
                if ($p) {
                    q('UPDATE projects SET name=?, short_details=?, tech_stack=?, description=?, progress=?, notes=?, secrets=?, github_url=?, figma_url=?, updated_by=?, updated_at=NOW() WHERE id=?', [...$vals, $uid, $pid]);
                } else {
                    q('INSERT INTO projects (name, short_details, tech_stack, description, progress, notes, secrets, github_url, figma_url, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())', [...$vals, $uid]);
                    $pid = (int)$pdo->lastInsertId();
                }
                foreach ((array)($_POST['remove_files'] ?? []) as $rid) {
                    $fr = q_one('SELECT * FROM project_files WHERE id=? AND project_id=?', [(int)$rid, $pid]);
                    if ($fr) { q('DELETE FROM project_files WHERE id=?', [$fr['id']]); $gone[] = $fr['file_path']; }
                }
                foreach ($uploads as $up) {
                    [$fname, $fpath] = project_store_file($up, $pid); $moved[] = $fpath;
                    q('INSERT INTO project_files (project_id, file_name, file_path, uploaded_by, uploaded_at) VALUES (?,?,?,?,NOW())', [$pid, $fname, $fpath, $uid]);
                }
                if ($user['role'] === 'admin' && isset($_POST['members_present'])) $invited = project_sync_members($pid, (array)($_POST['members'] ?? []), $uid);
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                foreach ($moved as $m) @unlink(cfg('upload_dir') . '/' . $m);
                throw $ex;
            }
            foreach ($gone as $g) @unlink(cfg('upload_dir') . '/' . $g);
            foreach ($invited as $mid) notify($mid, "{$user['name']} added you to the project \"" . mb_substr($vals[0], 0, 80) . '". Accept or reject the invitation.', "projects.php?invite=$pid#invites");
            done(true, $p ? 'Project updated.' : 'Project created.', "project.php?id=$pid");
        case 'project_delete':
            $p = project_load((int)($_POST['id'] ?? 0));
            if (!$p || !project_can_delete($p, $user)) done(false, 'You cannot delete this project.', 'projects.php');
            $paths = array_column(q_all('SELECT file_path FROM project_files WHERE project_id=?', [$p['id']]), 'file_path');
            q('DELETE FROM projects WHERE id=?', [$p['id']]);
            foreach ($paths as $fp) @unlink(cfg('upload_dir') . '/' . $fp);
            done(true, 'Project deleted.', 'projects.php');
        case 'project_respond':
            $pid = (int)($_POST['id'] ?? 0);
            $accept = ($_POST['choice'] ?? '') === 'accept';
            $m = q_one('SELECT pm.*, p.name FROM project_members pm JOIN projects p ON p.id=pm.project_id WHERE pm.project_id=? AND pm.user_id=? AND pm.status="pending"', [$pid, $uid]);
            if (!$m) done(false, 'Invitation not found or already answered.', 'projects.php');
            q('UPDATE project_members SET status=?, responded_at=NOW() WHERE project_id=? AND user_id=?', [$accept ? 'accepted' : 'rejected', $pid, $uid]);
            q('UPDATE notifications SET is_read=1 WHERE user_id=? AND link LIKE ?', [$uid, "projects.php?invite=$pid#%"]);
            if ($m['invited_by']) notify((int)$m['invited_by'], "{$user['name']} " . ($accept ? 'accepted' : 'rejected') . " the invitation to \"{$m['name']}\".", 'project.php?id=' . $pid);
            done(true, $accept ? 'You joined the project.' : 'Invitation rejected.', $accept ? "project.php?id=$pid" : 'projects.php');

        // ---------------- expenses ----------------
        case 'expense_save':
            $eid = (int)($_POST['id'] ?? 0);
            $ex = $eid ? q_one('SELECT * FROM expenses WHERE id=?', [$eid]) : null;
            if ($eid && (!$ex || !($user['role'] === 'admin' || (int)$ex['created_by'] === $uid))) done(false, 'Expense not found.', 'expenses.php');
            $to = $eid ? "expense.php?id=$eid" : 'expense.php';
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200);
            $date = (string)($_POST['expense_date'] ?? '');
            if ($title === '') done(false, 'Give the expense a heading.', $to);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) done(false, 'Choose a valid date.', $to);
            $names = (array)($_POST['item_name'] ?? []); $qtys = (array)($_POST['qty'] ?? []); $prices = (array)($_POST['price'] ?? []);
            $rows = []; $total = 0.0;
            foreach ($names as $i => $nm) {
                $nm = mb_substr(trim((string)$nm), 0, 200);
                $qty = (float)($qtys[$i] ?? 0); $price = (float)($prices[$i] ?? 0);
                if ($nm === '' && $qty == 0 && $price == 0) continue;
                if ($nm === '') done(false, 'Every row needs an item name.', $to);
                if ($qty <= 0 || $qty > 1e7 || $price < 0 || $price > 1e9) done(false, "Check quantity and price for \"$nm\".", $to);
                $amt = round($qty * $price, 2); $total += $amt;
                $rows[] = [$nm, round($qty, 2), round($price, 2), $amt];
            }
            if (!$rows) done(false, 'Add at least one item.', $to);
            $pdo = db(); $pdo->beginTransaction();
            try {
                if ($ex) {
                    q('UPDATE expenses SET title=?, expense_date=?, total=?, updated_by=?, updated_at=NOW() WHERE id=?', [$title, $date, round($total, 2), $uid, $eid]);
                    q('DELETE FROM expense_items WHERE expense_id=?', [$eid]);
                } else {
                    q('INSERT INTO expenses (title, expense_date, total, created_by, created_at) VALUES (?,?,?,?,NOW())', [$title, $date, round($total, 2), $uid]);
                    $eid = (int)$pdo->lastInsertId();
                }
                foreach ($rows as $n => [$nm, $qty, $price, $amt]) q('INSERT INTO expense_items (expense_id, sl, item_name, quantity, price, amount) VALUES (?,?,?,?,?,?)', [$eid, $n + 1, $nm, $qty, $price, $amt]);
                $pdo->commit();
            } catch (Throwable $ex2) { $pdo->rollBack(); throw $ex2; }
            done(true, 'Expense saved. Total ' . money($total) . '.', "expense.php?id=$eid");
        case 'expense_delete':
            $ex = q_one('SELECT * FROM expenses WHERE id=?', [(int)($_POST['id'] ?? 0)]);
            if (!$ex || !($user['role'] === 'admin' || (int)$ex['created_by'] === $uid)) done(false, 'You cannot delete this expense.', 'expenses.php');
            q('DELETE FROM expenses WHERE id=?', [$ex['id']]);
            done(true, 'Expense deleted.', 'expenses.php');

        // ---------------- personal notebook ----------------
        case 'note_save':
            $nid = (int)($_POST['id'] ?? 0);
            $nt = $nid ? q_one('SELECT * FROM notes WHERE id=?', [$nid]) : null;
            if ($nid && (!$nt || !note_can_edit($nt, $user))) done(false, 'Note not found or read-only.', 'notes.php');
            $to = $nid ? "note.php?id=$nid" : 'note.php';
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 200);
            $date = (string)($_POST['note_date'] ?? '');
            if ($title === '') done(false, 'Give the note a heading.', $to);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) done(false, 'Choose a valid date.', $to);
            $body = note_sanitize(mb_substr((string)($_POST['body'] ?? ''), 0, 200000));
            $adminNote = $user['role'] === 'admin' && (!$nt || (int)$nt['user_id'] === $uid);   // authored by the administrator, shared out to employees
            $share = $adminNote ? 0 : (($nt && (int)$nt['user_id'] !== $uid) ? (int)$nt['admin_access'] : (isset($_POST['admin_access']) ? 1 : 0));
            $shareAll = $adminNote && ($_POST['audience'] ?? 'all') === 'all' ? 1 : 0;
            $recipients = [];
            if ($adminNote && !$shareAll) {
                $rid = array_values(array_unique(array_map('intval', (array)($_POST['recipients'] ?? []))));
                if ($rid) $recipients = array_map('intval', array_column(q_all('SELECT id FROM users WHERE role<>"admin" AND status="active" AND id IN (' . implode(',', array_fill(0, count($rid), '?')) . ')', $rid), 'id'));
                if (!$recipients) done(false, 'Choose at least one employee, or share with all employees.', $to);
            }
            $uploads = project_collect_uploads();
            foreach ($uploads as $up) project_check_file($up);
            $pdo = db(); $pdo->beginTransaction(); $moved = []; $gone = [];
            try {
                if ($nt) {
                    q('UPDATE notes SET title=?, note_date=?, body=?, admin_access=?, share_all=?, updated_by=?, updated_at=NOW() WHERE id=?', [$title, $date, $body, $share, $shareAll, $uid, $nid]);
                } else {
                    q('INSERT INTO notes (user_id, title, note_date, body, admin_access, share_all, created_at) VALUES (?,?,?,?,?,?,NOW())', [$uid, $title, $date, $body, $share, $shareAll]);
                    $nid = (int)$pdo->lastInsertId();
                }
                foreach ((array)($_POST['remove_files'] ?? []) as $rid) {
                    $fr = q_one('SELECT * FROM note_files WHERE id=? AND note_id=?', [(int)$rid, $nid]);
                    if ($fr) { q('DELETE FROM note_files WHERE id=?', [$fr['id']]); $gone[] = $fr['file_path']; }
                }
                foreach ($uploads as $up) {
                    [$fname, $fpath] = project_store_file($up, $nid, 'notes'); $moved[] = $fpath;
                    q('INSERT INTO note_files (note_id, file_name, file_path, uploaded_at) VALUES (?,?,?,NOW())', [$nid, $fname, $fpath]);
                }
                $oldShares = $adminNote ? array_map('intval', array_column(q_all('SELECT user_id FROM note_shares WHERE note_id=?', [$nid]), 'user_id')) : [];
                if ($adminNote) {
                    q('DELETE FROM note_shares WHERE note_id=?', [$nid]);
                    foreach ($recipients as $r) q('INSERT INTO note_shares (note_id, user_id) VALUES (?,?)', [$nid, $r]);
                }
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                foreach ($moved as $m) @unlink(cfg('upload_dir') . '/' . $m);
                throw $ex;
            }
            foreach ($gone as $g) @unlink(cfg('upload_dir') . '/' . $g);
            if ($adminNote) {   // tell the people who just got access (everyone when it is shared with all and the note is new)
                $who = $shareAll ? ($nt && (int)$nt['share_all'] === 1 ? [] : array_diff(array_map('intval', array_column(q_all('SELECT id FROM users WHERE role<>"admin" AND status="active"'), 'id')), $oldShares)) : array_diff($recipients, $oldShares);
                foreach ($who as $r) notify((int)$r, "{$user['name']} shared a note with you: " . mb_substr($title, 0, 80), "note.php?id=$nid");
            }
            done(true, $nt ? 'Note updated.' : 'Note saved.', "note.php?id=$nid");
        case 'note_delete':
            $nt = q_one('SELECT * FROM notes WHERE id=?', [(int)($_POST['id'] ?? 0)]);
            if (!$nt || !note_can_edit($nt, $user)) done(false, 'Note not found or read-only.', 'notes.php');
            $paths = array_column(q_all('SELECT file_path FROM note_files WHERE note_id=?', [$nt['id']]), 'file_path');
            q('DELETE FROM notes WHERE id=?', [$nt['id']]);
            foreach ($paths as $fp) @unlink(cfg('upload_dir') . '/' . $fp);
            done(true, 'Note deleted.', 'notes.php');

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
