<?php
declare(strict_types=1);

/**
 * Offer letter generation.
 * Two-page PDF built from the employee record: page 1 = letterhead + offer letter, page 2 = Annexure A (CTC breakup,
 * TDS note, candidate acceptance declaration). Contact details come from the Support settings (site_settings).
 */

/** Indian digit grouping: 1800000 -> "18,00,000". */
function inr(float $n): string
{
    $s = (string)(int)round($n);
    if (strlen($s) <= 3) return $s;
    return preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($s, 0, -3)) . ',' . substr($s, -3);
}

/** Helvetica glyph widths (1/1000 em), regular + bold, for ASCII 32..126 - used for wrapping and right-alignment. */
function pdf_text_width(string $s, float $size, bool $bold = false): float
{
    static $reg = null, $bld = null;
    if ($reg === null) {
        $reg = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,
                667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,
                556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
        $bld = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,
                722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,
                556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];
    }
    $t = $bold ? $bld : $reg; $w = 0;
    foreach (str_split($s) as $ch) { $o = ord($ch) - 32; $w += $t[$o] ?? 556; }
    return $w * $size / 1000;
}

/** Tiny drawing surface for one A4 page; builds PDF content-stream operators. */
class LetterPage
{
    public array $cmds = [];
    private function enc(string $s): string { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string)@iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s)); }
    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, string $rgb = '0 0 0', string $align = 'L'): void
    {
        if ($s === '') return;
        $w = pdf_text_width($s, $size, $bold);
        if ($align === 'R') $x -= $w; elseif ($align === 'C') $x -= $w / 2;
        $this->cmds[] = sprintf('BT %s rg /%s %.1f Tf %.1f %.1f Td (%s) Tj ET', $rgb, $bold ? 'F2' : 'F1', $size, $x, $y, $this->enc($s));
    }
    /** Wrapped paragraph; returns the y below the last line. */
    public function para(float $x, float $y, float $width, string $s, float $size = 10, bool $bold = false, float $lead = 0, string $rgb = '0 0 0'): float
    {
        $lead = $lead ?: $size * 1.4;
        $line = '';
        foreach (preg_split('/\s+/', trim($s)) as $word) {
            $try = $line === '' ? $word : "$line $word";
            if ($line !== '' && pdf_text_width($try, $size, $bold) > $width) { $y -= $lead; $this->text($x, $y, $line, $size, $bold, $rgb); $line = $word; }
            else $line = $try;
        }
        if ($line !== '') { $y -= $lead; $this->text($x, $y, $line, $size, $bold, $rgb); }
        return $y;
    }
    public function line(float $x1, float $y1, float $x2, float $y2, float $w = 0.5, string $rgb = '0.75 0.75 0.75'): void
    { $this->cmds[] = sprintf('%s RG %.2f w %.1f %.1f m %.1f %.1f l S', $rgb, $w, $x1, $y1, $x2, $y2); }
    public function rect(float $x, float $y, float $w, float $h, string $fill): void
    { $this->cmds[] = sprintf('%s rg %.1f %.1f %.1f %.1f re f', $fill, $x, $y, $w, $h); }
    public function image(float $x, float $y, float $w, float $h): void
    { $this->cmds[] = sprintf('q %.1f 0 0 %.1f %.1f %.1f cm /Im1 Do Q', $w, $h, $x, $y); }
}

/** Assembles pages of LetterPage commands (plus the letterhead logo) into a PDF file string. */
function pdf_build(array $pages, string $footer = ''): string
{
    $logo = require __DIR__ . '/letterhead_logo.php';
    $img = base64_decode($logo['data']);   // already zlib-compressed RGB -> FlateDecode
    $W = 595; $H = 842;
    $objs = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
             4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
             5 => '<< /Type /XObject /Subtype /Image /Width ' . $logo['w'] . ' /Height ' . $logo['h'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length ' . strlen($img) . " >>\nstream\n" . $img . "\nendstream"];
    $kids = []; $n = 6; $total = count($pages);
    foreach ($pages as $i => $pg) {
        $cmds = $pg->cmds;
        $enc = fn(string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string)@iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s));
        $pn = 'Page ' . ($i + 1) . ' of ' . $total;
        $cmds[] = '0.8 0.8 0.8 RG 0.5 w 52 36 m 543 36 l S';
        $cmds[] = sprintf('BT 0.45 0.45 0.45 rg /F1 7.5 Tf 52 24 Td (%s) Tj ET', $enc($footer));
        $cmds[] = sprintf('BT 0.45 0.45 0.45 rg /F1 7.5 Tf %.1f 24 Td (%s) Tj ET', 543 - pdf_text_width($pn, 7.5), $pn);
        $stream = implode("\n", $cmds);
        $objs[$n] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $W . ' ' . $H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << /Im1 5 0 R >> >> /Contents ' . ($n + 1) . ' 0 R >>';
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

/** Letterhead: logo on the left, contact number / mail id / website on the right, red rule underneath. Returns the y below it. */
function letterhead(LetterPage $p, float $mx = 52): float
{
    $logo = require __DIR__ . '/letterhead_logo.php';
    $set = support_settings();
    $phone = trim(str_replace('||', '/', preg_replace('/\s*\|\|\s*/', ' / ', (string)$set['help_phone'])));
    $lw = 150; $lh = $lw * $logo['h'] / $logo['w'];
    $top = 842 - 38;
    $p->image($mx, $top - $lh, $lw, $lh);
    $xr = 595 - $mx; $y = $top - 10; $gray = '0.25 0.25 0.25';
    foreach ([[$phone, 'Contact: '], [(string)$set['help_email'], 'Email: '], ['https://libertytraining.in', 'Web: ']] as [$val, $lab]) {
        $p->text($xr, $y, $val, 9, false, $gray, 'R');
        $p->text($xr - pdf_text_width($val, 9) - 3, $y, $lab, 9, true, '0.78 0.16 0.16', 'R');
        $y -= 13;
    }
    $ly = $top - $lh - 10;
    $p->line($mx, $ly, 595 - $mx, $ly, 1.6, '0.78 0.16 0.16');
    return $ly;
}

/** Annexure A: pay structure table + notes. Pay is Basic only, every other component nil; the Variable Performance Bonus is 10%-20% of Basic. Returns the y below. */
function annexure_a(LetterPage $p, float $y, array $emp, string $docName): float
{
    $mx = 52; $cw = 595 - 2 * $mx; $red = '0.78 0.16 0.16';
    $ctc = max(0.0, (float)$emp['base_salary']);
    $p->text($mx, $y - 14, 'Annexure A - Compensation Structure (CTC Breakup)', 13, true, $red); $y -= 22;
    $p->text($mx, $y - 10, "Employee: {$emp['name']}   |   Designation: {$emp['designation']}   |   Employee ID: {$emp['emp_code']}", 9.5); $y -= 22;

    // Pay is Basic only; every other component is nil. The Variable Performance Bonus is 10%-20% of Basic, on performance.
    $basic = round($ctc);
    $varLo = round($basic * 0.10); $varHi = round($basic * 0.20);
    $parts = [['Basic Salary', $basic, 'Fixed monthly pay'], ['House Rent Allowance (HRA)', 0, 'Not applicable'], ['Special Allowance', 0, 'Not applicable'],
              ['Employer Provident Fund (PF)', 0, 'Not applicable'], ['Gratuity', 0, 'Not applicable'], ['Variable Performance Bonus', [$varLo, $varHi], '10-20% of Basic']];
    $cx = [$mx + 8, $mx + 215, $mx + 398, $mx + $cw - 8];   // component | basis | annual (right edge) | monthly (right edge)
    $hdr = 22;
    $p->rect($mx, $y - $hdr, $cw, $hdr, '0.78 0.16 0.16');
    $p->text($cx[0], $y - 15, 'Component', 9.5, true, '1 1 1');
    $p->text($cx[1], $y - 15, 'Basis', 9.5, true, '1 1 1');
    $p->text($cx[2], $y - 15, 'Annual (INR)', 9.5, true, '1 1 1', 'R');
    $p->text($cx[3], $y - 15, 'Monthly (INR)', 9.5, true, '1 1 1', 'R');
    $y -= $hdr; $rh = 24;
    foreach ($parts as $i => [$name, $amt, $basis]) {
        if ($i % 2 === 1) $p->rect($mx, $y - $rh, $cw, $rh, '0.965 0.965 0.97');
        $p->text($cx[0], $y - 16, $name, 10);
        $p->text($cx[1], $y - 16, $basis, 8.5, false, '0.35 0.35 0.35');
        if (is_array($amt)) {
            $p->text($cx[2], $y - 16, inr($amt[0]) . '-' . inr($amt[1]), 9.5, false, '0 0 0', 'R');
            $p->text($cx[3], $y - 16, inr($amt[0] / 12) . '-' . inr($amt[1] / 12), 9.5, false, '0 0 0', 'R');
        } else {
            $p->text($cx[2], $y - 16, inr($amt), 10, false, '0 0 0', 'R');
            $p->text($cx[3], $y - 16, inr($amt / 12), 10, false, '0 0 0', 'R');
        }
        $y -= $rh;
    }
    $p->rect($mx, $y - $rh, $cw, $rh, '0.93 0.93 0.95');
    $p->text($cx[0], $y - 16, 'Total Fixed Pay (CTC)', 10.5, true);
    $p->text($cx[2], $y - 16, inr($ctc), 10.5, true, '0 0 0', 'R');
    $p->text($cx[3], $y - 16, inr($ctc / 12), 10.5, true, '0 0 0', 'R');
    $p->line($mx, $y - $rh, $mx + $cw, $y - $rh, 1, '0.5 0.5 0.5');
    $y -= $rh + 16;

    $p->text($mx, $y, 'Notes', 11, true, $red); $y -= 3;
    foreach ([
        'Tax Deducted at Source (TDS): Income tax on your salary will be deducted at source (TDS) as per the Income Tax Act and the tax regime opted by you, and deposited with the Government. Investment / declaration proofs must be submitted to HR within the dates announced each year.',
        'Your fixed pay consists of Basic Salary only. HRA, Special Allowance, Employer PF and Gratuity are not part of this ' . ($docName === 'Offer Letter' ? 'offer' : 'appointment') . ' and are shown as nil.',
        'The Variable Performance Bonus is paid in addition to the Basic Salary, at 10% to 20% of Basic depending on your individual and Company performance. It is not guaranteed, and is payable only while you are in service on the payout date.',
        'Statutory deductions, wherever applicable (such as Professional Tax and TDS), will be made from your pay. All amounts are in Indian Rupees and rounded to the nearest rupee.',
    ] as $i => $note) { $ys = $y; $y = $p->para($mx + 14, $y, $cw - 14, $note, 9, false, 12.5); $p->text($mx, $ys - 12.5, ($i + 1) . '.', 9, true); $y -= 3; }

    return $y;
}

/** Acceptance declaration + signature block: candidate signature line (labelled when a name is given), Date & Place, and the Director's signature. Returns the y below. */
function execution_block(LetterPage $p, float $y, string $heading, string $declaration, ?string $candidateName = null): float
{
    $mx = 52; $cw = 595 - 2 * $mx; $red = '0.78 0.16 0.16';
    $y -= 14;
    $p->text($mx, $y, $heading, 11.5, true, $red); $y -= 2;
    $y = $p->para($mx, $y, $cw, $declaration, 10, false, 14.5);
    $y -= 38;
    $half = ($cw - 30) / 2;
    $p->line($mx, $y, $mx + $half, $y, 0.7, '0.3 0.3 0.3'); $p->line($mx + $half + 30, $y, $mx + $cw, $y, 0.7, '0.3 0.3 0.3');
    if ($candidateName !== null) { $p->text($mx, $y - 12, $candidateName, 9.5, true); $p->text($mx, $y - 24, 'Candidate Signature', 9); }
    $p->text($mx + $half + 30, $y - 12, 'Date & Place', 9, true);
    $y -= 66;
    $p->line($mx, $y, $mx + $half, $y, 0.7, '0.3 0.3 0.3');
    $p->text($mx, $y - 12, 'Prosenjit Roy', 9.5, true); $p->text($mx, $y - 24, 'Director of Liberty Foundation', 9);
    return $y - 24;
}

/** Offer letter (2 pages: letter + Annexure A) filled from the employee record. Returns the PDF bytes. */
function offer_letter_pdf(array $emp, ?string $managerName): string
{
    $mx = 52; $cw = 595 - 2 * $mx; $red = '0.78 0.16 0.16';
    $ctc = max(0.0, (float)$emp['base_salary']);
    $first = trim(explode(' ', trim((string)$emp['name']))[0]) ?: 'Candidate';
    $join = date('F j, Y', strtotime($emp['joined_on']));
    $issued = !empty($emp['created_at']) ? strtotime((string)$emp['created_at']) : time();   // the day the admin entered the employee's details
    $today = date('F j, Y', $issued);
    $deadline = date('F j, Y', strtotime('+7 days', $issued));
    $dept = $emp['department'] . ($emp['team'] ? " ({$emp['team']})" : '');
    $set = support_settings();
    $addr = (string)$set['hr_email'] !== '' && !str_contains((string)$set['hr_email'], '@') ? preg_replace('/\s*,\s*/', ', ', trim((string)$set['hr_email'])) : '';
    $footer = 'Liberty Foundation' . ($addr ? '  |  ' . $addr : '');

    // ---------------- PAGE 1 ----------------
    $p1 = new LetterPage();
    $y = letterhead($p1, $mx) - 8;
    $p1->text($mx, $y - 12, 'Date: ' . $today, 9.5);
    $p1->text(595 - $mx, $y - 12, 'Ref: OL/' . $emp['emp_code'] . '/' . date('Y', $issued), 9.5, false, '0 0 0', 'R');
    $y -= 34;
    $p1->text($mx, $y, $emp['name'], 10.5, true); $y -= 13;
    if (trim((string)$emp['address']) !== '') { $y = $p1->para($mx, $y + 13, $cw * 0.6, (string)$emp['address'], 9.5, false, 12.5) ; }
    $p1->text($mx, $y - 12, (string)$emp['email'], 9.5); $y -= 12;
    $y -= 18;
    $y = $p1->para($mx, $y + 14, $cw, 'Subject: Offer of Employment - ' . $emp['designation'], 11.5, true, 15) - 21;
    $p1->text($mx, $y, 'Dear ' . $first . ',', 10.5); $y -= 4;
    $y = $p1->para($mx, $y, $cw, "Welcome to Liberty Foundation! We are delighted to offer you the position of {$emp['designation']} in our {$dept} team. Your skills and the way you engaged with us through the selection process impressed everyone you met, and we are confident that you will be a valuable part of our journey.", 10.5, false, 15);
    $y -= 10;
    $p1->text($mx, $y - 12, 'Core Job Details', 11.5, true, $red); $y -= 20;
    $rows = [['Designation', $emp['designation']], ['Department', $dept], ['Date of Joining', $join],
             ['Work Location', $emp['work_location'] . ' (' . ($emp['employment_type'] ?: 'Full-time') . ', Office-based)'],
             ['Reporting Manager', $managerName ?: 'To be communicated by HR'], ['Annual Fixed Pay', $ctc > 0 ? 'INR ' . inr($ctc) . ' (Basic) + performance bonus of 10-20%' : 'As communicated by HR (see Annexure A)']];
    $rh = 21; $ty = $y;
    foreach ($rows as $i => [$k, $v]) {
        if ($i % 2 === 0) $p1->rect($mx, $ty - $rh, $cw, $rh, '0.965 0.965 0.97');
        $p1->text($mx + 8, $ty - 14, $k, 10, true);
        $p1->text($mx + 135, $ty - 14, (string)$v, 10);
        $ty -= $rh;
    }
    $p1->line($mx, $ty, $mx + $cw, $ty, 0.6, '0.8 0.8 0.8'); $p1->line($mx, $y, $mx + $cw, $y, 0.6, '0.8 0.8 0.8');
    $y = $ty - 12;
    $y = $p1->para($mx, $y, $cw, 'Your employment will be governed by the Company policies in force, including the HR Leave & Attendance Policy, as amended from time to time.', 10.5, false, 15);
    $y -= 12;
    $p1->text($mx, $y - 12, 'Background Verification', 11.5, true, $red); $y -= 14;
    $y = $p1->para($mx, $y, $cw, 'This offer is contingent upon satisfactory verification of your identity, educational qualifications, employment history and references. Any misrepresentation or suppression of facts, or an unsatisfactory verification report, will entitle the Company to withdraw this offer or terminate your employment without notice.', 10.5, false, 15);
    $y -= 12;
    $p1->text($mx, $y - 12, 'Offer Acceptance Deadline', 11.5, true, $red); $y -= 14;
    $y = $p1->para($mx, $y, $cw, "Please sign the Candidate Acceptance Declaration on Annexure A and return a copy of this letter to HR on or before $deadline. If we do not receive your acceptance by this date, this offer will stand withdrawn.", 10.5, false, 15);
    $y -= 12;
    $y = $p1->para($mx, $y, $cw, 'We look forward to welcoming you on board and to a rewarding journey together.', 10.5, false, 15);

    // ---------------- PAGE 2: Annexure A ----------------
    $p2 = new LetterPage();
    $y = letterhead($p2, $mx) - 10;
    $y = annexure_a($p2, $y, $emp, 'Offer Letter');
    execution_block($p2, $y, 'Candidate Acceptance Declaration', "I, {$emp['name']}, have read and understood the terms of this Offer Letter and Annexure A. I accept the offer of employment as {$emp['designation']} with Liberty Foundation, with a proposed date of joining of $join. I confirm that the information and documents I have given to the Company are true and complete.", $emp['name']);

    return pdf_build([$p1, $p2], $footer);
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
    $pdf = offer_letter_pdf($emp, $mgr);

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
