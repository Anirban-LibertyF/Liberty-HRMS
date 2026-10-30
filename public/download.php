<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$t = $_GET['t'] ?? '';
$row = match ($t) {
    'leave' => q_one('SELECT user_id, doc_name AS name, doc_path AS path FROM leave_requests WHERE id=?', [$id]),
    'worklog' => q_one('SELECT user_id, attach_name AS name, attach_path AS path FROM work_logs WHERE id=?', [$id]),
    'doc' => q_one('SELECT user_id, file_name AS name, file_path AS path FROM documents WHERE id=?', [$id]),
    default => null,
};
if (!$row || !$row['path']) { http_response_code(404); exit('File not found.'); }

// Access: owner, admin, the owner's manager, or any employee for company-wide documents.
$allowed = $row['user_id'] === null || (int)$row['user_id'] === (int)$user['id'] || $user['role'] === 'admin';
if (!$allowed && $user['role'] === 'manager') {
    $allowed = (int)q_val('SELECT manager_id FROM users WHERE id=?', [$row['user_id']]) === (int)$user['id'];
}
if (!$allowed) { http_response_code(403); exit('Access denied.'); }

$base = realpath(cfg('upload_dir'));
$file = realpath(cfg('upload_dir') . '/' . $row['path']);
if (!$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit('File missing.'); }

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', $row['name']) . '"');
readfile($file);
