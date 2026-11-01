<?php
declare(strict_types=1);

/**
 * Appointment letter generation: a 5-page PDF filled from the employee record, on the same letterhead as the offer letter.
 * Page 1 appointment / probation / hours, 2 duties / review / termination / notice, 3 IP + NDA + confidentiality,
 * 4 anti-moonlighting / non-compete / non-solicitation / disputes, 5 Annexure A + execution block.
 * Only made available to the employee 90 days after their date of joining (see JOINING_LETTER_WAIT_DAYS in init.php / download.php).
 * The clause wording is a standard template - have it reviewed by the company's counsel before relying on it.
 */
function appointment_letter_pdf(array $emp, ?string $managerName, ?int $issuedAt = null, string $remarks = ''): string
{
    $mx = 52; $cw = 595 - 2 * $mx; $red = '0.78 0.16 0.16';
    $issued = $issuedAt ?? time();   // the letter is dated the day it is generated
    $set = support_settings();
    $addr = (string)$set['hr_email'] !== '' && !str_contains((string)$set['hr_email'], '@') ? preg_replace('/\s*,\s*/', ', ', trim((string)$set['hr_email'])) : '';
    $footer = 'Liberty Foundation' . ($addr ? '  |  ' . $addr : '');
    $shift = q_one('SELECT * FROM shifts WHERE id=?', [$emp['shift_id']]) ?: ['start_time' => '10:30:00', 'end_time' => '17:30:00', 'required_min' => 420, 'break_allow_min' => 60, 'grace_min' => 15];
    $plQuota = (int)(q_val('SELECT quota FROM leave_types WHERE code="PL"') ?: 18);

    $name = (string)$emp['name']; $desig = (string)$emp['designation'];
    $dept = $emp['department'] . ($emp['team'] ? " ({$emp['team']})" : '');
    $join = date('F j, Y', strtotime($emp['joined_on']));
    $probEnd = date('F j, Y', strtotime($emp['joined_on'] . ' +3 months -1 day'));
    $mgr = $managerName ?: DIRECTOR_REPORTING;
    $hrs = fn(string $t) => date('g:i A', strtotime($t));
    $shiftH = rtrim(rtrim(number_format($shift['required_min'] / 60, 1), '0'), '.');

    $pages = [];
    $new = function (bool $top = false) use (&$pages, $mx) { $p = new LetterPage(); $y = letterhead($p, $mx) - 12; $pages[] = $p; return [$p, $y]; };
    // heading / paragraph / lettered list helpers; each returns the new y
    $H = function (LetterPage $p, float $y, string $t) use ($mx, $red) { $y -= 7; $p->text($mx, $y - 11, $t, 10.5, true, $red); return $y - 15; };
    $P = fn(LetterPage $p, float $y, string $t) => $p->para($mx, $y, $cw, $t, 9.3, false, 12.4) - 4;
    $L = function (LetterPage $p, float $y, array $items) use ($mx, $cw) {
        foreach ($items as $i => $t) { $ys = $y; $y = $p->para($mx + 18, $y, $cw - 18, $t, 9.3, false, 12.4); $p->text($mx, $ys - 12.4, '(' . chr(97 + $i) . ')', 9.3, true); $y -= 2; }
        return $y - 2;
    };

    // ============ PAGE 1 ============
    [$p, $y] = $new();
    $p->text(297.5, $y - 12, 'APPOINTMENT LETTER', 14, true, '0 0 0', 'C'); $y -= 30;
    $p->text($mx, $y, 'Date: ' . date('F j, Y', $issued), 9.5);
    $p->text(595 - $mx, $y, 'Ref: AL/' . $emp['emp_code'] . '/' . date('Y', $issued), 9.5, false, '0 0 0', 'R'); $y -= 16;
    $p->text($mx, $y, $name, 10, true); $y -= 12;
    if (trim((string)$emp['address']) !== '') { $y = $p->para($mx, $y + 12, $cw * 0.6, (string)$emp['address'], 9.3, false, 12) ; }
    $p->text($mx, $y - 12, (string)$emp['email'], 9.3); $y -= 36;
    $y = $p->para($mx, $y + 14, $cw, 'Subject: Appointment as ' . $desig . ' - Liberty Foundation', 10.5, true, 14) - 20;
    $p->text($mx, $y, 'Dear ' . (trim(explode(' ', trim($name))[0]) ?: 'Employee') . ',', 9.8); $y -= 3;
    $y = $P($p, $y, 'With reference to your application, the interviews you attended and our offer letter, we are pleased to appoint you with Liberty Foundation on the terms and conditions set out in this letter and its Annexure A (together, the "Appointment Letter").');

    $y = $H($p, $y, '1. The Company and Official Appointment');
    $y = $P($p, $y, 'Liberty Foundation (the "Company")' . ($addr ? ", having its office at $addr," : '') . " hereby appoints you, $name (Employee ID {$emp['emp_code']}), as {$desig} in the {$dept} department, with effect from $join (the \"Date of Joining\"). You will be based at {$emp['work_location']} and will report to $mgr, or to such other person as the Company may designate from time to time. Your employment type is " . ($emp['employment_type'] ?: 'Full-time') . '. The Company may, in its business interest, transfer you or assign you to a different role, team or location, with reasonable notice.');

    $y = $H($p, $y, '2. Probation Period');
    $y = $P($p, $y, "You will be on probation for a period of three (3) months from the Date of Joining, i.e. up to $probEnd. During probation your performance, conduct, attendance and suitability for the role will be assessed. On satisfactory completion you will be confirmed in writing; unless you receive written confirmation, you will continue on probation. During probation you are eligible only for the benefits expressly stated in this letter.");

    $y = $H($p, $y, '3. Extension of Probation - Criteria');
    $y = $P($p, $y, 'The Company may extend your probation by up to a further three (3) months (total probation not to exceed six (6) months), by written notice, where in its reasonable opinion:');
    $y = $L($p, $y, ['your performance, quality of work or delivery against assigned targets has not met the expected standard;', 'your attendance, punctuality, daily work-log submission or conduct has been unsatisfactory;', 'your background verification, documents or references are incomplete or pending; or', 'you have been absent for a significant part of the probation period (including unpaid leave), so that a fair assessment is not possible.']);
    $y = $P($p, $y, 'The Company may confirm, extend or, where performance remains unsatisfactory, terminate your employment at the end of the probation period.');

    $y = $H($p, $y, '4. Standard Work Hours and Attendance Policy');
    $y = $P($p, $y, 'Your standard shift is ' . $hrs($shift['start_time']) . ' to ' . $hrs($shift['end_time']) . " ({$shiftH} working hours a day, with a break allowance of {$shift['break_allow_min']} minutes and a grace period of {$shift['grace_min']} minutes for check-in). You must record your attendance (check-in, break and check-out) on the Liberty Office portal every working day, and submit your Daily Work Log. Weekly offs, holidays and leave are governed by the HR Leave & Attendance Policy in force; you are entitled to {$plQuota} paid leave days per calendar year, subject to approval, and leave other than the scheduled weekly off must be requested at least three (3) days in advance except in an emergency. Work beyond your shift is recorded as overtime and is compensated as per the Company's policy. Unauthorised absence may be treated as loss of pay and may attract disciplinary action.");

    // ============ PAGE 2 ============
    [$p, $y] = $new();
    $y = $H($p, $y, '5. Core Job Responsibilities');
    $y = $P($p, $y, "As {$desig}, you shall perform the duties of the role and such other lawful duties as are assigned by $mgr or the management from time to time. Your responsibilities include, without limitation:");
    $y = $L($p, $y, job_duties_for($emp));

    $y = $H($p, $y, '6. Performance Review');
    $y = $P($p, $y, 'Your performance will be reviewed at the end of probation and thereafter at least once every year (the Company may also hold quarterly check-ins). Review metrics may include: (i) quality, accuracy and completeness of work; (ii) timely delivery against goals and project milestones; (iii) attendance, punctuality and Daily Work Log discipline; (iv) ownership, initiative and problem solving; (v) teamwork, communication and client feedback; and (vi) adherence to Company policies, security and confidentiality norms. Review outcomes may inform confirmation, role changes, the Variable Performance Bonus described in Annexure A and any revision of pay, none of which is guaranteed.');

    $y = $H($p, $y, '7. Termination of Employment');
    $y = $P($p, $y, 'Termination without cause. Either party may end the employment, without assigning any reason, by giving the notice period stated in clause 8, or by the Company paying (and the employee forfeiting, as the case may be) basic salary in lieu of the notice period, in whole or part.');
    $y = $P($p, $y, 'Termination with cause. The Company may terminate your employment immediately, without notice or pay in lieu, after giving you a reasonable opportunity to explain, where you are found to have committed, among others: (a) wilful breach of this letter or Company policy; (b) fraud, dishonesty, theft or falsification of records or documents; (c) breach of confidentiality, or misuse or unauthorised transfer of source code, client data or Company property; (d) harassment, discrimination, violence or any act of sexual harassment contrary to the Company\'s POSH policy; (e) habitual or wilful absence, insubordination or neglect of duty; (f) conviction for, or involvement in, an offence involving moral turpitude; (g) moonlighting in breach of clause 12; or (h) any conduct that brings the Company into disrepute. Any dues will be settled subject to recovery of amounts owed to the Company and any losses caused by the breach.');

    $y = $H($p, $y, '8. Notice Period');
    $y = $P($p, $y, 'During probation: fifteen (15) days\' written notice by either party. After confirmation: thirty (30) days\' written notice by either party. Notice must be in writing. The Company may waive all or part of the notice period or pay basic salary in lieu, and may ask you not to attend work (garden leave) during notice while you remain bound by this letter. You must serve the notice diligently, complete a proper handover, and return all Company property, accounts, devices, documents and data on or before your last working day. Absence without approval during notice may be treated as loss of pay and the unserved notice period recovered from your final settlement. Your full and final settlement will be made within thirty (30) days of your last working day, after clearance. Experience / relieving documents are issued only after a complete handover and clearance.');

    // ============ PAGE 3 ============
    [$p, $y] = $new();
    $p->text($mx, $y - 4, 'CRUCIAL IT CLAUSES', 11, true); $y -= 12;
    $y = $H($p, $y, '9. Intellectual Property (IP) Assignment');
    $y = $P($p, $y, 'All work product - including source code, object code, scripts, algorithms, models, APIs, databases, designs, user interfaces, documentation, test cases, inventions, improvements, know-how, data compilations and products, in any form or medium - that you conceive, create, develop or contribute to, alone or with others, (i) in the course of your employment, (ii) within the scope of the Company\'s or its clients\' business, or (iii) using the Company\'s time, equipment, systems, funds or confidential information (together, "Work Product") shall be the sole and exclusive property of the Company from the moment of creation, and is a work made in the course of employment under Section 17(c) of the Copyright Act, 1957.');
    $y = $L($p, $y, ['To the fullest extent that any right in the Work Product does not vest in the Company automatically, you irrevocably assign to the Company, absolutely and worldwide and for the full term of protection, all copyright, patent, design, trade-secret, database and other intellectual-property rights in it, and waive all moral rights (to the extent permitted by law).', 'You will promptly disclose all Work Product to the Company, keep it in the Company\'s repositories and systems, and sign any document and do anything reasonably required to record, register, perfect or enforce the Company\'s rights, during and after employment.', 'You shall not use, copy, publish, upload to any personal or public repository, or train any external AI / code-generation tool with, Work Product or Company or client data without written approval. Open-source or third-party code may be used only in accordance with the Company\'s approval and licence-compliance rules.', 'You confirm that you will not bring or use any confidential information or code belonging to a previous employer or any third party. Any prior inventions you wish to exclude must be listed in writing to HR on or before the Date of Joining; failing which none is excluded.']);

    $y = $H($p, $y, '10. Non-Disclosure and Confidentiality');
    $y = $P($p, $y, '"Confidential Information" means all non-public information of the Company, its affiliates and clients, in any form, including source code, architecture, product roadmaps, pricing, business plans, financials, client lists and contracts, employee data, credentials, security details, trade secrets and anything marked or reasonably understood to be confidential. You shall: (i) hold it in strict confidence and use it only to perform your duties; (ii) not disclose it to anyone except authorised persons who need to know; (iii) protect it with the security measures required by the Company; and (iv) return or permanently delete it on request and on leaving. These obligations continue during employment and for as long as the information remains confidential after it ends (and for trade secrets, indefinitely). They do not apply to information that is public through no fault of yours, or whose disclosure is required by law (in which case you will, where permitted, notify the Company first).');

    $y = $H($p, $y, '11. Client Data and Information Security');
    $y = $P($p, $y, 'You shall handle client data and personal data only as permitted by the client contract, the Company\'s policies and applicable law, including the Information Technology Act, 2000 and the Digital Personal Data Protection Act, 2023. Specifically you shall not: copy client data to personal devices, email, cloud or messaging accounts; share credentials or access; access data you have no business need for; or remove data from approved systems. You must report any actual or suspected data breach, loss of device or security incident to the Company immediately. The Company may monitor use of its systems, accounts and devices to the extent permitted by law. A breach of this clause is gross misconduct and may result in termination without notice, and in civil and criminal action, with the Company entitled to injunctive relief and to recover damages in addition to any other remedy.');

    // ============ PAGE 4 ============
    [$p, $y] = $new();
    $y = $H($p, $y, '12. Anti-Moonlighting Policy');
    $y = $P($p, $y, 'During your employment you shall devote your full working time, attention and skill to the Company. Without the prior written approval of the Company\'s management you shall not, whether paid or unpaid, directly or indirectly: (a) take up any other employment, consultancy, freelance, contract or gig work; (b) run, join, advise or hold an executive role in any other business, startup or practice; (c) provide services to, or develop software or products for, any client, competitor or third party; or (d) use Company time, equipment, code, data or contacts for any other activity. Passive investments, and personal learning / open-source activity that does not use Confidential Information, does not compete with the Company and does not interfere with your duties, are permitted with prior intimation to HR. Any breach is misconduct that may lead to termination under clause 7, and the Company may recover any income or benefit you derived from it to the extent permitted by law.');

    $y = $H($p, $y, '13. Non-Compete');
    $y = $P($p, $y, 'During your employment (including any notice period) you shall not engage in, assist or hold any interest in any business that competes with the Company or its clients\' business in which you are involved. After your employment ends, you shall not use or disclose Confidential Information or Work Product to compete with the Company. Nothing in this letter is intended to restrain you from practising your profession or earning a livelihood after your employment where such a restraint would be void under Section 27 of the Indian Contract Act, 1872. The restrictions in clauses 9, 10, 11 and 14 continue after employment and apply to the maximum extent that the law permits.');

    $y = $H($p, $y, '14. Non-Solicitation');
    $y = $P($p, $y, 'For twelve (12) months after your employment ends, for any reason, you shall not, directly or indirectly, (a) solicit or induce any employee, consultant or contractor of the Company to leave it, or (b) solicit, or use Confidential Information to approach, any client or prospective client of the Company with whom you dealt, or about whom you obtained Confidential Information, during the last twelve (12) months of your employment, in order to divert business from the Company. You acknowledge that these restrictions are reasonable and necessary to protect the Company\'s legitimate business interests and goodwill.');

    $y = $H($p, $y, '15. Governing Law and Dispute Resolution');
    $y = $P($p, $y, 'This letter is governed by the laws of India. The parties shall first try to settle any dispute arising out of or in connection with it through good-faith discussion between you and HR / the management for thirty (30) days from written notice of the dispute. Failing settlement, the dispute shall be finally resolved by arbitration by a sole arbitrator appointed by the Company with your consent (or, failing agreement, as per the Arbitration and Conciliation Act, 1996), conducted in English, with the seat and venue at Kolkata, West Bengal. Subject to the foregoing, the courts at Kolkata, West Bengal shall have exclusive jurisdiction, and either party may approach those courts for urgent interim or injunctive relief (including to protect IP and Confidential Information).');

    $y = $H($p, $y, '16. General');
    $y = $P($p, $y, 'This letter, with Annexure A and the Company policies in force, is the entire agreement about your employment and replaces earlier discussions and offers. The Company may amend its policies from time to time, and may amend this letter by written notice. If any clause is held unenforceable, the rest remains in force and that clause is limited to the minimum extent necessary. Please sign and return a copy of this letter to HR as acceptance. Your appointment is conditional on satisfactory background verification and submission of the documents requested by HR.');
    if (trim($remarks) !== '') { $y = $H($p, $y, '17. Additional Remarks'); $y = $P($p, $y, trim($remarks)); }
    $p->text($mx, $y - 16, 'We welcome you to Liberty Foundation and look forward to a long and rewarding association.', 9.3);

    // ============ PAGE 5: Annexure A + execution ============
    [$p, $y] = $new();
    $y = annexure_a($p, $y + 2, $emp, 'Appointment Letter');
    execution_block($p, $y, 'Employee Acceptance and Execution', "I, $name, have read and understood this Appointment Letter and Annexure A in full, and I accept the appointment as {$desig} with Liberty Foundation on the terms stated, with effect from $join. I agree to be bound by the intellectual property, confidentiality, anti-moonlighting, non-compete and non-solicitation obligations. I confirm that the information and documents I have given to the Company are true and complete.", $name);

    return pdf_build($pages, $footer);
}

/** (Re)creates the employee's appointment letter and lists it under their Documents. Returns the documents row id. */
function create_appointment_letter(int $userId, ?string $joinDate = null, string $remarks = '', ?int $issuedAt = null, array $duties = []): int
{
    $emp = q_one('SELECT * FROM users WHERE id=? AND role <> "admin"', [$userId]);
    if (!$emp) throw new RuntimeException('Employee not found.');
    if ($joinDate) $emp['joined_on'] = $joinDate;   // the date typed on the admin form, used in this letter only
    if ($duties) $emp['job_duties'] = implode("
", $duties);   // responsibilities edited on the admin form, used in this letter only
    $mgr = $emp['manager_id'] ? q_val('SELECT name FROM users WHERE id=?', [$emp['manager_id']]) : null;
    $pdf = appointment_letter_pdf($emp, $mgr, $issuedAt, $remarks);

    $dir = cfg('upload_dir') . "/docs/$userId";
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('Could not create the documents folder.');
    $stored = 'appointment_letter_' . bin2hex(random_bytes(6)) . '.pdf';
    if (file_put_contents("$dir/$stored", $pdf) === false) throw new RuntimeException('Could not save the appointment letter.');

    foreach (q_all('SELECT id, file_path FROM documents WHERE user_id=? AND category="Appointment Letter"', [$userId]) as $old) {   // replace, never duplicate
        q('DELETE FROM documents WHERE id=?', [$old['id']]);
        @unlink(cfg('upload_dir') . '/' . $old['file_path']);
    }
    q('INSERT INTO documents (user_id, title, category, file_name, file_path, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,NULL,NOW())',
        [$userId, 'Appointment Letter', 'Appointment Letter', 'Appointment_Letter_' . $emp['emp_code'] . '.pdf', "docs/$userId/$stored"]);
    return (int)db()->lastInsertId();
}
