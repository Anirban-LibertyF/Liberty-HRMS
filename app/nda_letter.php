<?php
declare(strict_types=1);

/**
 * Non-disclosure agreement (NDA) letter generation, same approach as the offer / joining letters.
 * DUMMY TEMPLATE: replace nda_letter_blocks() once the real NDA is provided.
 * The employee signs it and uploads the signed copy from Documents (category "NDA Signed").
 */

function nda_letter_blocks(array $emp): array
{
    $join = date('F j, Y', strtotime($emp['joined_on']));
    $B = fn(string $t, int $size = 11, bool $bold = false, int $after = 8, int $indent = 0) => [$t, $size, $bold, $after, $indent];
    $out = [
        $B('Liberty Office', 20, true, 2),
        $B('Human Resources Department', 10, false, 4),
        $B('SAMPLE NDA - TEMPLATE TO BE REPLACED', 9, true, 18),
        $B('Date: ' . date('F j, Y'), 11, false, 2),
        $B('Ref: NDA/' . $emp['emp_code'] . '/' . date('Y'), 11, false, 16),
        $B('NON-DISCLOSURE AGREEMENT', 15, true, 14),
        $B("This Non-Disclosure Agreement is made between Liberty Office (the \"Company\") and {$emp['name']}, Employee ID {$emp['emp_code']} (the \"Employee\"), who joined the Company on $join as {$emp['designation']}.", 11, false, 10),
    ];
    foreach ([
        ['1. Confidential Information', 'The Employee shall treat as confidential all non-public information of the Company and its clients, including business plans, source code, designs, credentials, financial data, employee and customer records, and any project material shared in the course of employment.'],
        ['2. Obligations', 'The Employee shall use Confidential Information only for the Company\'s business, shall not disclose it to any third party, and shall protect it with at least the same care used for the Employee\'s own sensitive information.'],
        ['3. Exclusions', 'These obligations do not apply to information that is public through no fault of the Employee, was lawfully known to the Employee before joining, or must be disclosed by law (with prior notice to the Company where permitted).'],
        ['4. Return of Material', 'On request or on leaving the Company, the Employee shall return or permanently delete all Confidential Information, including copies held on personal devices or accounts.'],
        ['5. Duration', 'These obligations continue during employment and for a period of three (3) years after it ends, and indefinitely for trade secrets.'],
        ['6. Remedies', 'The Employee acknowledges that a breach may cause serious harm to the Company, which may seek injunctive relief and any other remedy available under applicable law.'],
    ] as [$h, $t]) { $out[] = $B($h, 11, true, 3); $out[] = $B($t, 11, false, 9); }
    $out[] = $B('Please sign below and upload the signed copy under Documents in your Liberty Office account.', 11, false, 26);
    $out[] = $B('For Liberty Office                                                                          Employee', 11, true, 34);
    $out[] = $B('Authorized Signatory: ______________________            Signature: ______________________', 11, false, 10);
    $out[] = $B('Date: ____________________                                           Date: ____________________', 11, false, 0);
    return $out;
}

/** (Re)creates the employee's NDA letter and lists it under their Documents. Returns the documents row id. */
function create_nda_letter(int $userId): int
{
    $emp = q_one('SELECT * FROM users WHERE id=? AND role <> "admin"', [$userId]);
    if (!$emp) throw new RuntimeException('Employee not found.');
    $pdf = simple_pdf(nda_letter_blocks($emp), 'NDA - ' . $emp['emp_code']);

    $dir = cfg('upload_dir') . "/docs/$userId";
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new RuntimeException('Could not create the documents folder.');
    $stored = 'nda_letter_' . bin2hex(random_bytes(6)) . '.pdf';
    if (file_put_contents("$dir/$stored", $pdf) === false) throw new RuntimeException('Could not save the NDA letter.');

    foreach (q_all('SELECT id, file_path FROM documents WHERE user_id=? AND category="NDA Letter"', [$userId]) as $old) {
        q('DELETE FROM documents WHERE id=?', [$old['id']]);
        @unlink(cfg('upload_dir') . '/' . $old['file_path']);
    }
    q('INSERT INTO documents (user_id, title, category, file_name, file_path, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,NULL,NOW())',
        [$userId, 'NDA Letter', 'NDA Letter', 'NDA_' . $emp['emp_code'] . '.pdf', "docs/$userId/$stored"]);
    return (int)db()->lastInsertId();
}
