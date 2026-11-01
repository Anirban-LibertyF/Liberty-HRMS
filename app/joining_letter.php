<?php
declare(strict_types=1);

/**
 * Joining Letter pro-forma: a one-page PDF filled from the employee's record. The employee downloads it, signs it and
 * uploads the signed copy under Documents > Joining Letter (the date and place are left blank for them to write in).
 */
function joining_letter_pdf(array $emp, ?string $managerName): string
{
    $mx = 52; $cw = 595 - 2 * $mx; $S = 10.5; $L = 16;
    $name = (string)$emp['name'];
    $dept = $emp['department'] . ($emp['team'] ? " ({$emp['team']})" : '');
    $join = date('F j, Y', strtotime((string)$emp['joined_on']));
    $appt = q_val('SELECT uploaded_at FROM documents WHERE user_id=? AND category="Appointment Letter" ORDER BY id DESC LIMIT 1', [$emp['id']]);
    $apptTxt = $appt ? ', dated ' . date('F j, Y', strtotime((string)$appt)) : '';

    $p = new LetterPage();
    $y = letterhead($p, $mx) - 12;
    $p->text(297.5, $y - 12, 'JOINING LETTER', 14, true, '0 0 0', 'C'); $y -= 34;
    $p->text($mx, $y, 'Date: ____________________', 9.5);
    $p->text(595 - $mx, $y, 'Employee ID: ' . $emp['emp_code'], 9.5, false, '0 0 0', 'R'); $y -= 28;

    foreach (['To,', 'The Director / HR Department,', 'Liberty Foundation'] as $t) { $p->text($mx, $y, $t, $S, $t === 'Liberty Foundation'); $y -= 15; }
    $y -= 8;
    $y = $p->para($mx, $y, $cw, "Subject: Joining report as {$emp['designation']}", 10.5, true, 14) - 22;
    $p->text($mx, $y, 'Dear Sir / Madam,', $S); $y -= 4;
    $y = $p->para($mx, $y, $cw, "With reference to the Offer Letter and the Appointment Letter{$apptTxt} issued to me by Liberty Foundation (the \"Company\"), I, $name (Employee ID {$emp['emp_code']}), hereby report for duty and join the Company as {$emp['designation']} in the $dept department with effect from $join.", $S, false, $L) - 8;
    $y = $p->para($mx, $y, $cw, 'I confirm that:', $S, false, $L) - 2;
    $items = [
        'I have read and understood the Appointment Letter, its Annexure A and the Company policies, and I accept the terms and conditions of my employment;',
        'the information, documents and certificates I have given to the Company are true, complete and genuine, and I will inform the Company of any change;',
        'I am not bound by any prior employment or agreement that prevents me from joining and working for the Company;',
        'I will keep the Company\'s confidential information and data secure, and follow the rules on attendance, working hours, Daily Work Log and conduct; and',
        'I will report to ' . ($managerName ?: DIRECTOR_REPORTING) . ' or any other authority named by the Company.',
    ];
    foreach ($items as $i => $t) { $ys = $y; $y = $p->para($mx + 22, $y, $cw - 22, $t, $S, false, $L); $p->text($mx + 4, $ys - $L, ($i + 1) . '.', $S, true); $y -= 2; }
    $y -= 6;
    $y = $p->para($mx, $y, $cw, 'I look forward to contributing to the Company and request you to kindly accept this joining report.', $S, false, $L) - 24;
    $p->text($mx, $y, 'Yours faithfully,', $S); $y -= 54;

    $half = ($cw - 30) / 2;
    $p->line($mx, $y, $mx + $half, $y, 0.7, '0.3 0.3 0.3'); $p->line($mx + $half + 30, $y, $mx + $cw, $y, 0.7, '0.3 0.3 0.3');
    $p->text($mx, $y - 12, 'Employee Signature', 9, true); $p->text($mx, $y - 24, $name, 9.5);
    $p->text($mx + $half + 30, $y - 12, 'Date & Place', 9, true);
    $y -= 52;
    $p->rect($mx, $y - 82, $cw, 94, '0.965 0.965 0.97');
    $p->text($mx + 10, $y - 4, 'For office use - Received by the Director', 9.5, true);
    $p->text($mx + 10, $y - 26, 'Received on: ____________________', 9.5);
    $p->text($mx + 10, $y - 50, 'Date of joining accepted: ____________________', 9.5);
    $p->stamp('signature', $mx + 292, $y - 46, 110, 110 * 724 / 2172);
    $p->line($mx + 285, $y - 50, $mx + $cw - 12, $y - 50, 0.7, '0.3 0.3 0.3');
    $p->text($mx + 285, $y - 62, 'Prosenjit Roy', 9.5, true); $p->text($mx + 285, $y - 74, 'Director of Liberty Foundation', 9);
    return pdf_build([$p], 'Liberty Foundation  |  Joining Letter - sign and upload under Documents');
}
