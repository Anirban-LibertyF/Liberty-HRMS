<?php
declare(strict_types=1);

const PROJECT_FILE_EXT = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt'];
const PROJECT_PREVIEW_MIME = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'];

// ---------- secrets at rest (AES-256-GCM) ----------
function app_key(): string
{
    $env = getenv('TF_APP_KEY');
    if ($env) return hash('sha256', $env, true);
    $f = dirname(__DIR__) . '/storage/.app_key';
    if (!is_file($f)) {
        if (!is_dir(dirname($f))) mkdir(dirname($f), 0775, true);
        file_put_contents($f, bin2hex(random_bytes(32)));
        @chmod($f, 0600);
    }
    return hash('sha256', trim((string)file_get_contents($f)), true);
}
function secret_encrypt(string $plain): string
{
    if ($plain === '') return '';
    $iv = random_bytes(12);
    $ct = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $ct);
}
function secret_decrypt(?string $blob): string
{
    if (!$blob) return '';
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 28) return '';
    $pt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $pt === false ? '' : $pt;
}

// ---------- access ----------
function project_load(int $id): ?array
{
    return q_one('SELECT p.*, c.name AS creator_name, e.name AS editor_name FROM projects p
                  JOIN users c ON c.id=p.created_by LEFT JOIN users e ON e.id=p.updated_by WHERE p.id=?', [$id]);
}
function project_can_access(array $p, array $u): bool
{
    if ($u['role'] === 'admin' || (int)$p['created_by'] === (int)$u['id']) return true;
    return (bool)q_val('SELECT COUNT(*) FROM project_members WHERE project_id=? AND user_id=? AND status="accepted"', [$p['id'], $u['id']]);
}
function project_can_delete(array $p, array $u): bool
{
    return $u['role'] === 'admin' || (int)$p['created_by'] === (int)$u['id'];
}

// ---------- members ----------
/** Makes the member list equal to $ids. New people become "pending" invitations. Returns the newly invited user ids. */
function project_sync_members(int $pid, array $ids, int $invitedBy): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $valid = $ids ? array_map('intval', array_column(q_all('SELECT id FROM users WHERE role<>"admin" AND status="active" AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids), 'id')) : [];
    $cur = array_column(q_all('SELECT user_id, status FROM project_members WHERE project_id=?', [$pid]), 'status', 'user_id');
    $added = [];
    foreach ($valid as $uid) {
        if (!isset($cur[$uid])) { q('INSERT INTO project_members (project_id, user_id, status, invited_by) VALUES (?,?,"pending",?)', [$pid, $uid, $invitedBy]); $added[] = $uid; }
    }
    foreach (array_keys($cur) as $uid) {
        if (!in_array((int)$uid, $valid, true)) q('DELETE FROM project_members WHERE project_id=? AND user_id=?', [$pid, $uid]);
    }
    return $added;
}

// ---------- files ----------
function project_collect_uploads(): array
{
    $f = $_FILES['files'] ?? null;
    if (!$f || !is_array($f['name'])) return [];
    $out = [];
    foreach ($f['name'] as $i => $n) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $n, 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}
function project_check_file(array $f): string
{
    $label = basename((string)$f['name']);
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException("Upload failed for $label (error {$f['error']}).");
    if ($f['size'] > cfg('max_upload')) throw new RuntimeException("$label exceeds the 10MB limit.");
    $ext = strtolower(pathinfo($label, PATHINFO_EXTENSION));
    if (!in_array($ext, PROJECT_FILE_EXT, true)) throw new RuntimeException("$label: unsupported file type. Allowed: " . implode(', ', PROJECT_FILE_EXT) . '.');
    $strict = ['pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'gif' => ['image/gif'], 'webp' => ['image/webp']];
    if (isset($strict[$ext]) && !in_array((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']), $strict[$ext], true)) throw new RuntimeException("$label: file content does not match its extension.");
    return $ext;
}
/** Moves a checked upload into storage. Returns [original name, stored relative path]. */
function project_store_file(array $f, int $pid, string $folder = 'projects'): array
{
    $ext = project_check_file($f);
    $dir = cfg('upload_dir') . "/$folder/$pid";
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('Could not create the project folder.');
    $stored = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$stored")) throw new RuntimeException('Could not save the file.');
    return [preg_replace('/[^\w.\- ()]+/u', '_', basename((string)$f['name'])), "$folder/$pid/$stored"];
}
