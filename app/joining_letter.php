<?php
declare(strict_types=1);

/**
 * Joining / confirmation letter generation.
 * Generated the same way as the offer letter, but only made available to the employee
 * 90 days after their date of joining (see JOINING_LETTER_WAIT_DAYS in documents.php / download.php).
 * DUMMY TEMPLATE: replace joining_letter_blocks() once the real letter is provided.
 */

/** Content of the letter as blocks: [text, size, bold, space-after, indent]. */
function joining_letter_blocks(array $emp, ?string $managerName): array
{
    $join = date('F j, Y', strtotime($emp['joined_on']));
    $B = fn(string $t, int $size = 11, bool $bold = false, int $after = 8, int $indent = 0) => [$t, $size, $bold, $after, $indent];
    $out = [
        $B('Liberty Office', 20, true, 2),
        $B('Human Resources Department', 10, false, 4),
        $B('SAMPLE JOINING LETTER - TEMPLATE TO BE REPLACED', 9, true, 18),
        $B('Date: ' . date('F j, Y'), 11, false, 2),
        $B('Ref: JL/' . $emp['emp_code'] . '/' . date('Y'), 11, false, 16),
        $B($emp['name'], 11, true, 2),
    ];
    if (trim((string)$emp['address']) !== '') $out[] = $B($emp['address'], 11, false, 2);
    $out[] = $B($emp['email'], 11, false, 16);
    $out[] = $B('Subject: Confirmation of Employment - ' . $emp['designation'], 12, true, 14);
    $out[] = $B('Dear ' . $emp['name'] . ',', 11, false, 10);
    $out[] = $B('We are pleased to confirm that you have successfully completed your probation period and your employment with Liberty Office now stands confirmed, effective from the date below.', 11, false, 10);
    $out[] = $B('Your employment details are as follows:', 11, false, 6);
    foreach ([
        "Employee ID: {$emp['emp_code']}",
        "Date of joining: $join",
        "Designation: {$emp['designation']}",
        "Department: {$emp['department']}" . ($emp['team'] ? " ({$emp['team']})" : ''),
        'Reporting manager: ' . ($managerName ?: 'To be communicated by HR'),
        'Work location: ' . $emp['work_location'],
    ] as $line) $out[] = $B('-  ' . $line, 11, false, 3, 14);
    $out[] = $B('', 11, false, 6);
    $out[] = $B('All other terms and conditions of your employment, as communicated in your offer letter, remain unchanged. Please keep this letter for your records; it is also available under Documents in your Liberty Office account.', 11, false, 10);
    $out[] = $B('We look forward to your continued contribution and wish you a successful career with us.', 11, false, 14);
    $out[] = $B('Yours sincerely,', 11, false, 18);
    $out[] = $B('Authorized Signatory', 11, true, 2);
    $out[] = $B('HR Operations, Liberty Office', 11, false, 14);
    return $out;
}

/** (Re)creates the employee's joining letter and lists it under their Documents. Returns the documents row id. */
function create_joining_letter(int $userId): int
{
    $emp = q_one('SELECT * FROM users WHERE id=? AND role <> "admin"', [$userId]);
    if (!$emp) throw new RuntimeException('Employee not found.');
    $mgr = $emp['manager_id'] ? q_val('SELECT name FROM users WHERE id=?', [$emp['manager_id']]) : null;
    $pdf = simple_pdf(joining_letter_blocks($emp, $mgr), 'Joining letter - ' . $emp['emp_code']);

    $dir = cfg('upload_dir') . "/docs/$userId";
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('Could not create the documents folder.');
    $stored = 'joining_letter_' . bin2hex(random_bytes(6)) . '.pdf';
    if (file_put_contents("$dir/$stored", $pdf) === false) throw new RuntimeException('Could not save the joining letter.');

    foreach (q_all('SELECT id, file_path FROM documents WHERE user_id=? AND category="Joining Letter"', [$userId]) as $old) {   // replace, never duplicate
        q('DELETE FROM documents WHERE id=?', [$old['id']]);
        @unlink(cfg('upload_dir') . '/' . $old['file_path']);
    }
    q('INSERT INTO documents (user_id, title, category, file_name, file_path, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,NULL,NOW())',
        [$userId, 'Joining Letter', 'Joining Letter', 'Joining_Letter_' . $emp['emp_code'] . '.pdf', "docs/$userId/$stored"]);
    return (int)db()->lastInsertId();
}
