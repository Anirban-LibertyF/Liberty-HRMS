<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$t = $_GET['t'] ?? '';
$row = match ($t) {
    'leave' => q_one('SELECT user_id, doc_name AS name, doc_path AS path FROM leave_requests WHERE id=?', [$id]),
    'worklog' => q_one('SELECT user_id, attach_name AS name, attach_path AS path FROM work_logs WHERE id=?', [$id]),
    'project' => q_one('SELECT NULL AS user_id, file_name AS name, file_path AS path, project_id FROM project_files WHERE id=?', [$id]),
    'note' => q_one('SELECT NULL AS user_id, file_name AS name, file_path AS path, note_id FROM note_files WHERE id=?', [$id]),
    'doc' => q_one('SELECT user_id, file_name AS name, file_path AS path, category FROM documents WHERE id=?', [$id]),
    default => null,
};
if (!$row || !$row['path']) { http_response_code(404); exit('File not found.'); }

// Access: owner, admin, the owner's manager, or any employee for company-wide documents.
if ($t === 'note') {
    $nt = q_one('SELECT * FROM notes WHERE id=?', [(int)$row['note_id']]);
    $allowed = $nt && note_can_access($nt, $user);
} elseif ($t === 'project') {
    $pr = project_load((int)$row['project_id']);
    $allowed = $pr && project_can_access($pr, $user);
} else {
    $allowed = $row['user_id'] === null || (int)$row['user_id'] === (int)$user['id'] || $user['role'] === 'admin';
}
if (!$allowed && !in_array($t, ['project', 'note'], true) && $user['role'] === 'manager') {
    $allowed = (int)q_val('SELECT manager_id FROM users WHERE id=?', [$row['user_id']]) === (int)$user['id'];
}
if (!$allowed) { http_response_code(403); exit('Access denied.'); }

// Appointment letters stay locked for the owner (and their manager) until 90 days after joining; admins can always open it.
if (($row['category'] ?? null) === 'Appointment Letter' && $user['role'] !== 'admin') {
    $joinedOn = q_val('SELECT joined_on FROM users WHERE id=?', [$row['user_id']]);
    if ($joinedOn && joining_letter_days_left($joinedOn) > 0) { http_response_code(403); exit('Your appointment letter is not available yet. It unlocks 90 days after your date of joining.'); }
}

$base = realpath(cfg('upload_dir'));
$file = realpath(cfg('upload_dir') . '/' . $row['path']);
if (!$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit('File missing.'); }

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . (isset($_GET['dl']) || !in_array($mime, PROJECT_PREVIEW_MIME, true) ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', $row['name']) . '"');
readfile($file);
