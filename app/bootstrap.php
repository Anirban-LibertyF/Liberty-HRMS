<?php
declare(strict_types=1);

$GLOBALS['tf_config'] = require __DIR__ . '/config.php';
date_default_timezone_set($GLOBALS['tf_config']['timezone']);

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_name('trackflow_sid');
    session_start();
}

function cfg(string $key, $default = null)
{
    return $GLOBALS['tf_config'][$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if (!$pdo) {
        $c = cfg('db');
        $pdo = new PDO(
            "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function q_all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function q_one(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r ?: null; }
function q_val(string $sql, array $params = []) { $r = q($sql, $params)->fetchColumn(); return $r === false ? null : $r; }

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function now(): DateTimeImmutable { return new DateTimeImmutable('now'); }
function today(): string { return date('Y-m-d'); }
function dt(string $s): DateTimeImmutable { return new DateTimeImmutable($s); }

// ---------- formatting ----------
function fmt_hm(int $min, bool $pad = true): string
{
    $min = max(0, $min);
    $h = intdiv($min, 60);
    $m = $min % 60;
    return $pad ? sprintf('%02dh %02dm', $h, $m) : "{$h}h {$m}m";
}
function fmt_time(?string $datetime): string { return $datetime ? date('h:i A', strtotime($datetime)) : '--'; }
function fmt_date(?string $d, string $f = 'M d, Y'): string { return $d ? date($f, strtotime($d)) : '--'; }
function money($n): string { return '₹' . number_format((float)$n, 2); }
function initials(string $name): string
{
    $p = preg_split('/\s+/', trim($name));
    return strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[count($p) - 1] ?? '', 0, 1));
}
function avatar_html(array $u, int $size = 38): string
{
    $hue = crc32($u['emp_code']) % 360;
    if (!empty($u['profile_photo'])) {
        return '<img class="avatar" src="profile_photo.php?' . (isset($u['id']) ? 'id=' . (int)$u['id'] . '&amp;' : '') . 'v=' . rawurlencode(substr(hash('sha256', $u['profile_photo']), 0, 12)) . '" alt="" width="' . (int)$size . '" height="' . (int)$size . '" style="width:' . (int)$size . 'px;height:' . (int)$size . 'px;object-fit:cover">';
    }
    return '<span class="avatar" style="width:' . $size . 'px;height:' . $size . 'px;background:hsl(' . $hue . ',45%,38%);font-size:' . (int)($size * .38) . 'px">' . e(initials($u['name'])) . '</span>';
}

// ---------- csrf / flash / redirect ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => 'Session expired. Reload the page.']); }
        else echo 'Invalid or expired form token. Go back and reload the page.';
        exit;
    }
}
function is_ajax(): bool { return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'; }

function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg]; }
function flash_pop(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function redirect(string $url): never { header('Location: ' . $url); exit; }

/** Safe local redirect target (blocks open redirects). */
function safe_next(?string $n, string $fallback = 'index.php'): string
{
    if ($n && preg_match('#^[A-Za-z0-9_\-./?=&%]+$#', $n) && !str_contains($n, '//') && !str_starts_with($n, '/')) return $n;
    return $fallback;
}

// ---------- auth ----------
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (!empty($_SESSION['uid'])) {
        $exp = $_SESSION['expires'] ?? 0;
        if ($exp < time()) { $_SESSION = []; return null; }
        if (empty($_SESSION['remember'])) $_SESSION['expires'] = time() + cfg('session_idle_minutes') * 60;
        $u = q_one('SELECT u.*, s.name AS shift_name, s.label AS shift_label, s.start_time, s.end_time, s.required_min, s.break_allow_min, s.grace_min
                    FROM users u JOIN shifts s ON s.id = u.shift_id WHERE u.id = ? AND u.status = "active"', [$_SESSION['uid']]);
        $cache = $u;
    }
    return $cache;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        if (is_ajax()) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => 'Please sign in again.']); exit; }
        redirect('login.php?next=' . urlencode(basename($_SERVER['SCRIPT_NAME']) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')));
    }
    // The administrator only uses the admin area; employee pages bounce back to it.
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($u['role'] === 'admin' && !str_starts_with($page, 'admin') && !in_array($page, ['actions.php', 'download.php', 'logout.php', 'profile_photo.php', 'support.php', 'projects.php', 'project.php', 'expenses.php', 'expense.php', 'expense_print.php', 'notes.php', 'note.php'], true)) redirect('admin.php');
    return $u;
}

function is_staff(array $u): bool { return in_array($u['role'], ['admin', 'manager'], true); }

function require_staff(): array
{
    $u = require_login();
    if (!is_staff($u)) { http_response_code(403); exit('Access denied: administrator or manager role required.'); }
    return $u;
}
function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit('Access denied: administrator role required.'); }
    return $u;
}

function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function is_onprem(string $ip): bool
{
    return $ip === '::1' || (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}
function ip_location(string $ip): string { return 'Barrackpore Office'; }

// ---------- notifications / activity ----------
function notify(int $uid, string $msg, string $link = ''): void
{
    q('INSERT INTO notifications (user_id, message, link, created_at) VALUES (?,?,?,NOW())', [$uid, $msg, $link]);
}
function log_activity(int $uid, string $icon, string $title, string $detail = ''): void
{
    q('INSERT INTO activity_log (user_id, icon, title, detail, created_at) VALUES (?,?,?,?,NOW())', [$uid, $icon, $title, $detail]);
}

// ---------- file uploads ----------
/** Validates and stores an uploaded file. Returns [original name, stored relative path] or throws RuntimeException. */
function store_upload(array $f, string $subdir): array
{
    if ($f['error'] === UPLOAD_ERR_NO_FILE) throw new RuntimeException('No file selected.');
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (error ' . $f['error'] . ').');
    if ($f['size'] > cfg('max_upload')) throw new RuntimeException('File exceeds the 10MB limit.');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, cfg('allowed_ext'), true)) throw new RuntimeException('Only PDF, PNG and JPG files are allowed.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $okMime = ['pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg']];
    if (!in_array($mime, $okMime[$ext], true)) throw new RuntimeException('File content does not match its extension.');
    $dir = cfg('upload_dir') . '/' . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $stored = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$stored")) throw new RuntimeException('Could not save the file.');
    $name = preg_replace('/[^\w.\- ()]+/u', '_', basename($f['name']));
    return [$name, "$subdir/$stored"];
}

/** Validates and stores a profile image, returning its storage-relative path. */
function store_profile_photo(array $f, int $uid): string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a photo to upload.');
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Photo upload failed (error ' . $f['error'] . ').');
    if (($f['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('Profile photos must be 5MB or smaller.');

    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    if (!isset($types[$ext])) throw new RuntimeException('Choose a JPG or PNG image.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $image = @getimagesize($f['tmp_name']);
    if ($mime !== $types[$ext] || !$image || !in_array($image[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        throw new RuntimeException('The uploaded file is not a valid JPG or PNG image.');
    }

    $subdir = "profile/$uid";
    $dir = cfg('upload_dir') . '/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Could not create the profile photo folder.');
    $stored = bin2hex(random_bytes(16)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (!move_uploaded_file($f['tmp_name'], "$dir/$stored")) throw new RuntimeException('Could not save the profile photo.');
    return "$subdir/$stored";
}

function json_out(bool $ok, string $msg = '', array $extra = []): never
{
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'msg' => $msg] + $extra);
    exit;
}
