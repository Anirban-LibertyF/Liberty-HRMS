<?php
declare(strict_types=1);

/**
 * Experience Letter and Letter of Recommendation (one page each, same letterhead, Director's signature and seal as the other letters).
 * Both are generated on demand by the admin from the employee page; the wording avoids gendered pronouns.
 */

/** "2 years, 3 months and 5 days" between two Y-m-d dates (inclusive of the last day). */
function tenure_text(string $from, string $to): string
{
    $d = (new DateTime($from))->diff((new DateTime($to))->modify('+1 day'));
    $parts = [];
    if ($d->y) $parts[] = $d->y . ($d->y === 1 ? ' year' : ' years');
    if ($d->m) $parts[] = $d->m . ($d->m === 1 ? ' month' : ' months');
    if ($d->d) $parts[] = $d->d . ($d->d === 1 ? ' day' : ' days');
    if (!$parts) return '1 day';
    $last = array_pop($parts);
    return $parts ? implode(', ', $parts) . ' and ' . $last : $last;
}

/** Role duties as short bullet text: at most $max lines, trailing punctuation removed. */
function letter_duty_points(array $emp, int $max = 4): array
{
    $tpl = []; foreach (job_duty_templates() as [$k, , $lines]) $tpl[$k] = $lines;
    $picked = trim((string)($emp['job_duties'] ?? '')) === '' ? job_roles_of($emp) : [];
    $lines = count($picked) > 1 ? array_slice(array_map(fn($k) => $tpl[$k][0], $picked), 0, $max) : array_slice(job_role_duties($emp), 0, $max);   // several roles: one point from each
    return array_map(fn($l) => rtrim($l, ';.,: '), $lines);
}

/** "For Liberty Foundation" + Director's signature, seal, line and name. Returns the y below the block. */
function director_signoff(LetterPage $p, float $y): float
{
    $mx = 52;
    $p->text($mx, $y, 'For Liberty Foundation', 10, true); $y -= 70;
    $p->stamp('signature', $mx + 4, $y - 7, 130, 130 * 724 / 2172);
    $p->stamp('seal', $mx + 158, $y - 14, SEAL_SIZE, SEAL_SIZE); $p->sealed = true;
    $p->line($mx, $y, $mx + 200, $y, 0.7, '0.3 0.3 0.3');
    $p->text($mx, $y - 12, 'Prosenjit Roy', 9.5, true); $p->text($mx, $y - 24, 'Director of Liberty Foundation', 9);
    return $y - 24;
}

/** Shared top of both letters: letterhead, title, date / ref. Returns [page, y]. */
function letter_open(string $title, string $refPrefix, array $emp, int $ts): array
{
    $mx = 52;
    $p = new LetterPage();
    $y = letterhead($p, $mx) - 12;
    $p->text(297.5, $y - 12, $title, 14, true, '0 0 0', 'C'); $y -= 34;
    $p->text($mx, $y, 'Date: ' . date('F j, Y', $ts), 9.5);
    $p->text(595 - $mx, $y, 'Ref: ' . $refPrefix . '/' . $emp['emp_code'] . '/' . date('Y', $ts), 9.5, false, '0 0 0', 'R');
    return [$p, $y - 28];
}

function letter_footer(): string
{
    $set = support_settings();
    $addr = (string)$set['hr_email'] !== '' && !str_contains((string)$set['hr_email'], '@') ? preg_replace('/\s*,\s*/', ', ', trim((string)$set['hr_email'])) : '';
    return 'Liberty Foundation' . ($addr ? '  |  ' . $addr : '');
}

/** Bulleted list helper: returns the y below. */
function letter_bullets(LetterPage $p, float $y, array $items, float $size = 10.3): float
{
    $mx = 52; $cw = 595 - 2 * $mx;
    foreach ($items as $t) { $ys = $y; $y = $p->para($mx + 16, $y, $cw - 16, $t, $size, false, 14.5); $p->text($mx + 4, $ys - 14.5, '-', $size, true); $y -= 2; }
    return $y;
}

/** Experience letter. $lastDay = last working day (Y-m-d); $remarks = optional extra sentence(s) written by the admin. */
function experience_letter_pdf(array $emp, string $lastDay, string $remarks = '', array $duties = []): string
{
    $mx = 52; $cw = 595 - 2 * $mx; $S = 10.5; $L = 15.5;
    $name = (string)$emp['name']; $first = trim(explode(' ', trim($name))[0]) ?: $name;
    $dept = $emp['department'] . ($emp['team'] ? " ({$emp['team']})" : '');
    $join = date('F j, Y', strtotime($emp['joined_on'])); $last = date('F j, Y', strtotime($lastDay));
    $past = $lastDay <= date('Y-m-d');
    [$p, $y] = letter_open('EXPERIENCE LETTER', 'EL', $emp, time());

    $p->text($mx, $y, 'TO WHOM IT MAY CONCERN', 11, true); $y -= 8;
    $y = $p->para($mx, $y - 6, $cw, 'Subject: Experience Letter - ' . $name, 10.5, true, 14) - 12;
    $y = $p->para($mx, $y, $cw, "This is to certify that $name (Employee ID {$emp['emp_code']}) " . ($past ? 'was' : 'is') . " employed with Liberty Foundation as {$emp['designation']} in the $dept department from $join to $last, a total period of " . tenure_text($emp['joined_on'], $lastDay) . '.', $S, false, $L) - 8;
    $y = $p->para($mx, $y, $cw, "During this period, $name's responsibilities included:", $S, false, $L) - 2;
    $y = letter_bullets($p, $y, $duties ?: letter_duty_points($emp), $S) - 6;
    $y = $p->para($mx, $y, $cw, "We found $first to be sincere and hardworking, and $first's conduct and performance during the period of employment were satisfactory." . (trim($remarks) !== '' ? ' ' . trim($remarks) : ''), $S, false, $L) - 8;
    $y = $p->para($mx, $y, $cw, $past ? "$first has been relieved from their duties at the close of working hours on $last." : "$first will be relieved from their duties at the close of working hours on $last.", $S, false, $L) - 8;
    $y = $p->para($mx, $y, $cw, "We thank $first for the contribution made to the Company and wish them every success in their future endeavours.", $S, false, $L) - 24;
    director_signoff($p, $y);
    return pdf_build([$p], letter_footer());
}

/** Letter of recommendation. $endDate = last day worked (Y-m-d) or '' if the employee is still with the Company; $note = optional strengths / remarks. */
function recommendation_letter_pdf(array $emp, string $endDate = '', string $note = '', array $duties = []): string
{
    $mx = 52; $cw = 595 - 2 * $mx; $S = 10.5; $L = 15.5;
    $name = (string)$emp['name']; $first = trim(explode(' ', trim($name))[0]) ?: $name;
    $dept = $emp['department'] . ($emp['team'] ? " ({$emp['team']})" : '');
    $join = date('F j, Y', strtotime($emp['joined_on']));
    $current = $endDate === '';
    $to = $current ? date('Y-m-d') : $endDate;
    $period = $current ? "since $join" : "from $join to " . date('F j, Y', strtotime($endDate));
    [$p, $y] = letter_open('LETTER OF RECOMMENDATION', 'RL', $emp, time());

    $p->text($mx, $y, 'TO WHOM IT MAY CONCERN', 11, true); $y -= 8;
    $y = $p->para($mx, $y - 6, $cw, 'Subject: Letter of Recommendation - ' . $name, 10.5, true, 14) - 12;
    $y = $p->para($mx, $y, $cw, "I am pleased to write this letter in recommendation of $name (Employee ID {$emp['emp_code']}), who " . ($current ? 'has been working' : 'worked') . " with Liberty Foundation as {$emp['designation']} in the $dept department $period, a period of " . tenure_text($emp['joined_on'], $to) . '.', $S, false, $L) - 8;
    $y = $p->para($mx, $y, $cw, "In this role, $first's responsibilities included:", $S, false, $L) - 2;
    $y = letter_bullets($p, $y, $duties ?: letter_duty_points($emp, 3), $S) - 6;
    $y = $p->para($mx, $y, $cw, "During this time $first has shown dedication, reliability and a professional approach to work, and has cooperated well with colleagues and the management." . (trim($note) !== '' ? ' ' . trim($note) : ''), $S, false, $L) - 8;
    $y = $p->para($mx, $y, $cw, "I recommend $first for any suitable opportunity, and I am confident that they will be a valuable addition to any organisation or institution they join.", $S, false, $L) - 8;
    $set = support_settings();
    $contact = trim(implode(' / ', array_filter([trim((string)$set['help_email']), trim(str_replace('||', '/', (string)$set['help_phone']))])));
    $y = $p->para($mx, $y, $cw, 'Please feel free to contact me' . ($contact !== '' ? " at $contact" : '') . ' for any further information.', $S, false, $L) - 24;
    director_signoff($p, $y);
    return pdf_build([$p], letter_footer());
}

/** Saves the PDF under the employee's Documents (replacing any earlier one of the same category). Returns the documents row id. */
function save_employee_letter(int $userId, string $category, string $prefix, string $pdf, ?int $by = null): int
{
    $code = (string)q_val('SELECT emp_code FROM users WHERE id=?', [$userId]);
    $dir = cfg('upload_dir') . "/docs/$userId";
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('Could not create the documents folder.');
    $stored = $prefix . '_' . bin2hex(random_bytes(6)) . '.pdf';
    if (file_put_contents("$dir/$stored", $pdf) === false) throw new RuntimeException("Could not save the $category.");
    foreach (q_all('SELECT id, file_path FROM documents WHERE user_id=? AND category=?', [$userId, $category]) as $old) {
        q('DELETE FROM documents WHERE id=?', [$old['id']]);
        @unlink(cfg('upload_dir') . '/' . $old['file_path']);
    }
    q('INSERT INTO documents (user_id, title, category, file_name, file_path, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,?,NOW())',
        [$userId, $category, $category, str_replace(' ', '_', $category) . "_$code.pdf", "docs/$userId/$stored", $by]);
    return (int)db()->lastInsertId();
}

function create_experience_letter(int $userId, string $lastDay, string $remarks = '', ?int $by = null, array $duties = []): int
{
    $emp = q_one('SELECT * FROM users WHERE id=? AND role <> "admin"', [$userId]);
    if (!$emp) throw new RuntimeException('Employee not found.');
    return save_employee_letter($userId, 'Experience Letter', 'experience_letter', experience_letter_pdf($emp, $lastDay, $remarks, $duties), $by);
}

function create_recommendation_letter(int $userId, string $endDate = '', string $note = '', ?int $by = null, array $duties = []): int
{
    $emp = q_one('SELECT * FROM users WHERE id=? AND role <> "admin"', [$userId]);
    if (!$emp) throw new RuntimeException('Employee not found.');
    return save_employee_letter($userId, 'Recommendation Letter', 'recommendation_letter', recommendation_letter_pdf($emp, $endDate, $note, $duties), $by);
}
