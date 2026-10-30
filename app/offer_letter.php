<?php
declare(strict_types=1);

/**
 * Offer letter generation.
 * DUMMY TEMPLATE: replace offer_letter_blocks() (or swap create_offer_letter() to copy a supplied PDF)
 * once the real offer letter document is provided.
 */

/** Content of the letter as blocks: [text, size, bold, space-after, indent]. */
function offer_letter_blocks(array $emp, ?string $managerName): array
{
    $ctc = 'INR ' . number_format((float)$emp['base_salary'], 0);
    $join = date('F j, Y', strtotime($emp['joined_on']));
    $B = fn(string $t, int $size = 11, bool $bold = false, int $after = 8, int $indent = 0) => [$t, $size, $bold, $after, $indent];
    $out = [
        $B('Liberty Office', 20, true, 2),
        $B('Human Resources Department', 10, false, 4),
        $B('SAMPLE OFFER LETTER - TEMPLATE TO BE REPLACED', 9, true, 18),
        $B('Date: ' . date('F j, Y'), 11, false, 2),
        $B('Ref: OL/' . $emp['emp_code'] . '/' . date('Y'), 11, false, 16),
        $B($emp['name'], 11, true, 2),
    ];
    if (trim((string)$emp['address']) !== '') $out[] = $B($emp['address'], 11, false, 2);
    $out[] = $B($emp['email'], 11, false, 16);
    $out[] = $B('Subject: Offer of Employment - ' . $emp['designation'], 12, true, 14);
    $out[] = $B('Dear ' . $emp['name'] . ',', 11, false, 10);
    $out[] = $B("We are pleased to offer you the position of {$emp['designation']} in the {$emp['department']} department" . ($emp['team'] ? " ({$emp['team']})" : '') . ' at Liberty Office. We were impressed by your profile and look forward to you joining the team.', 11, false, 10);
    $out[] = $B('The key terms of your employment are as follows:', 11, false, 6);
    foreach ([
        "Employee ID: {$emp['emp_code']}",
        "Date of joining: $join",
        "Employment type: {$emp['employment_type']}",
        "Work location: {$emp['work_location']}",
        'Reporting manager: ' . ($managerName ?: 'To be communicated by HR'),
        "Annual compensation (gross): $ctc per annum, subject to statutory deductions",
        'Working hours and leave: as per the HR Leave & Attendance Policy in force',
    ] as $line) $out[] = $B('-  ' . $line, 11, false, 3, 14);
    $out[] = $B('', 11, false, 6);
    $out[] = $B('This offer is subject to satisfactory verification of your documents and references. Your employment will be governed by the company policies as amended from time to time. Please keep this letter for your records; it is also available under Documents in your Liberty Office account.', 11, false, 10);
    $out[] = $B('We welcome you and wish you a successful career with us.', 11, false, 14);
    $out[] = $B('Yours sincerely,', 11, false, 18);
    $out[] = $B('Authorized Signatory', 11, true, 2);
    $out[] = $B('HR Operations, Liberty Office', 11, false, 14);
    $out[] = $B('Acceptance', 11, true, 6);
    $out[] = $B('I accept the above terms of employment.', 11, false, 18);
    $out[] = $B('Signature: ______________________        Date: ____________________', 11, false, 0);
    return $out;
}

/** Minimal dependency-free PDF writer (A4, Helvetica / Helvetica-Bold, text only, automatic page breaks). */
function simple_pdf(array $blocks, string $footer = ''): string
{
    $W = 595; $H = 842; $mx = 56; $top = 48; $bottom = 48;
    $enc = fn(string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string)@iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s));
    $pages = []; $cur = []; $y = $H - $top;
    foreach ($blocks as [$text, $size, $bold, $after, $indent]) {
        $maxChars = max(20, (int)floor(($W - 2 * $mx - $indent) / ($size * ($bold ? 0.56 : 0.5))));
        $lines = $text === '' ? [''] : explode("\n", wordwrap($text, $maxChars, "\n", true));
        foreach ($lines as $ln) {
            $lead = $size * 1.3;
            if ($y - $lead < $bottom) { $pages[] = $cur; $cur = []; $y = $H - $top; }
            $y -= $lead;
            if ($ln !== '') $cur[] = sprintf('BT /%s %d Tf %d %.1f Td (%s) Tj ET', $bold ? 'F2' : 'F1', $size, $mx + $indent, $y, $enc($ln));
        }
        $y -= $after;
    }
    $pages[] = $cur;

    $objs = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
             4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
    $kids = []; $n = 5;
    foreach ($pages as $i => $cmds) {
        if ($footer !== '') $cmds[] = sprintf('BT /F1 8 Tf %d 30 Td (%s) Tj ET', $mx, $enc($footer . '  |  Page ' . ($i + 1) . ' of ' . count($pages)));
        $stream = implode("\n", $cmds);
        $objs[$n] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $W . ' ' . $H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($n + 1) . ' 0 R >>';
        $objs[$n + 1] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        $kids[] = $n . ' 0 R'; $n += 2;
    }
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
    ksort($objs);

    $pdf = "%PDF-1.4\n"; $offsets = [];
    foreach ($objs as $num => $body) { $offsets[$num] = strlen($pdf); $pdf .= "$num 0 obj\n$body\nendobj\n"; }
    $xref = strlen($pdf);
    $pdf .= 'xref' . "\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $off) $pdf .= sprintf("%010d 00000 n \n", $off);
    return $pdf . 'trailer << /Size ' . (count($objs) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n$xref\n%%EOF";
}

/** (Re)creates the employee's offer letter and lists it under their Documents. Returns the documents row id. */
function create_offer_letter(int $userId): int
{
    $emp = q_one('SELECT * FROM users WHERE id=? AND role <> "admin"', [$userId]);
    if (!$emp) throw new RuntimeException('Employee not found.');
    $mgr = $emp['manager_id'] ? q_val('SELECT name FROM users WHERE id=?', [$emp['manager_id']]) : null;
    $pdf = simple_pdf(offer_letter_blocks($emp, $mgr), 'Offer letter - ' . $emp['emp_code']);

    $dir = cfg('upload_dir') . "/docs/$userId";
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('Could not create the documents folder.');
    $stored = 'offer_letter_' . bin2hex(random_bytes(6)) . '.pdf';
    if (file_put_contents("$dir/$stored", $pdf) === false) throw new RuntimeException('Could not save the offer letter.');

    foreach (q_all('SELECT id, file_path FROM documents WHERE user_id=? AND category="Offer Letter"', [$userId]) as $old) {   // replace, never duplicate
        q('DELETE FROM documents WHERE id=?', [$old['id']]);
        @unlink(cfg('upload_dir') . '/' . $old['file_path']);
    }
    q('INSERT INTO documents (user_id, title, category, file_name, file_path, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,NULL,NOW())',
        [$userId, 'Offer Letter', 'Offer Letter', 'Offer_Letter_' . $emp['emp_code'] . '.pdf', "docs/$userId/$stored"]);
    return (int)db()->lastInsertId();
}
