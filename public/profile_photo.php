<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();

$tid = (int)($_GET['id'] ?? 0);
if ($tid && $tid !== (int)$user['id']) {
    $target = q_one('SELECT id, manager_id, profile_photo FROM users WHERE id=?', [$tid]);
    $allowed = $target && ($user['role'] === 'admin' || ($user['role'] === 'manager' && (int)$target['manager_id'] === (int)$user['id']));
    if (!$allowed) { http_response_code(403); exit; }
    $user = $target;
}
if (empty($user['profile_photo'])) { http_response_code(404); exit; }
$base = realpath(cfg('upload_dir'));
$file = realpath(cfg('upload_dir') . '/' . $user['profile_photo']);
if (!$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit;
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['image/jpeg', 'image/png'], true)) { http_response_code(404); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($file);
