<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$rel = support_settings()['dir_photo'];
$base = realpath(cfg('upload_dir'));
$file = $rel ? realpath(cfg('upload_dir') . '/' . $rel) : false;
if (!$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ['image/jpeg', 'image/png'], true)) { http_response_code(404); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($file);
