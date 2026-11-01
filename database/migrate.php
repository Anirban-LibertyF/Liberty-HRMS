<?php
declare(strict_types=1);
/**
 * CLI: php -c php.ini database/migrate.php
 * Brings an EXISTING database up to date without losing data (database/setup.php, by contrast, wipes everything).
 * Safe to run repeatedly. It
 *   1. creates tables added after the first release (payout_paid),
 *   2. fixes leave types (only Paid Leave, 18 days; no Sick Leave) and whole-day leave,
 *   3. renames document categories (generated "Joining Letter" -> "Appointment Letter", "NDA Signed" -> "Joining Letter"),
 *   4. creates any MISSING Offer Letter / Appointment Letter PDFs into storage/uploads (existing ones are kept with their date and remarks;
 *      the PDFs are files, not DB rows, so they do not travel with git - this is how they get created on a fresh checkout).
 */
if (PHP_SAPI !== 'cli') exit('CLI only.');
require __DIR__ . '/../app/init.php';

// 1. tables
db()->exec('CREATE TABLE IF NOT EXISTS payout_paid (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  pay_month CHAR(7) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  paid_by INT NULL,
  paid_at DATETIME NOT NULL,
  UNIQUE KEY uq_payout (user_id, pay_month),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
if (!q_val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users" AND COLUMN_NAME="job_duties"'))
    db()->exec('ALTER TABLE users ADD COLUMN job_duties TEXT NULL AFTER designation');
if (!q_val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users" AND COLUMN_NAME="job_roles"'))
    db()->exec('ALTER TABLE users ADD COLUMN job_roles VARCHAR(200) NULL AFTER job_duties');
foreach (['bank_name' => 'VARCHAR(100)', 'bank_account_no' => 'VARCHAR(30)', 'bank_ifsc' => 'VARCHAR(15)', 'bank_branch' => 'VARCHAR(100)'] as $col => $type)
    if (!q_val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="users" AND COLUMN_NAME=?', [$col]))
        db()->exec("ALTER TABLE users ADD COLUMN $col $type NOT NULL DEFAULT ''");
echo "Tables OK.\n";

// 2. leave types: Paid Leave (18 days) + Unpaid / LOP only
$pl = q_one('SELECT id FROM leave_types WHERE code="PL"');
if (!$pl) {
    q('INSERT INTO leave_types (code, name, badge, quota, is_paid, color, sort_order) VALUES ("PL","Paid Leave","Calendar Year",18,1,"#8e1b1b",1)');
    $pl = ['id' => (int)db()->lastInsertId()];
}
foreach (q_all('SELECT id FROM leave_types WHERE code NOT IN ("PL","LOP")') as $old) {   // e.g. the retired Sick Leave: its requests become Paid Leave
    q('UPDATE leave_requests SET leave_type_id=? WHERE leave_type_id=?', [$pl['id'], $old['id']]);
    q('DELETE FROM leave_types WHERE id=?', [$old['id']]);
}
q('UPDATE leave_types SET quota=18, sort_order=1 WHERE code="PL"');
q('UPDATE leave_types SET sort_order=2 WHERE code="LOP"');
q('UPDATE leave_requests SET days=1, half_day=0 WHERE half_day=1');   // no half-day leave
echo "Leave types OK (Paid Leave 18 days).\n";

// 3. document categories
q('UPDATE documents SET category="Appointment Letter", title="Appointment Letter", file_name=REPLACE(file_name,"Joining_Letter_","Appointment_Letter_") WHERE category="Joining Letter" AND uploaded_by IS NULL');
q('UPDATE documents SET category="Joining Letter", title="Joining Letter" WHERE category="NDA Signed"');
echo "Document categories OK.\n";

// 4. letters
$n = 0;
foreach (q_all('SELECT id, emp_code FROM users WHERE role <> "admin" AND status="active" ORDER BY id') as $u) {
    try {
        // only missing letters: an existing one keeps its date and the remarks the admin typed (use Generate on the employee page to redo it)
        if (!q_val('SELECT COUNT(*) FROM documents WHERE user_id=? AND category="Offer Letter"', [$u['id']])) create_offer_letter((int)$u['id']);
        if (!q_val('SELECT COUNT(*) FROM documents WHERE user_id=? AND category="Appointment Letter"', [$u['id']])) create_appointment_letter((int)$u['id']);
        $n++;
    } catch (Throwable $ex) { echo "  {$u['emp_code']}: " . $ex->getMessage() . "\n"; }
}
echo "Offer + Appointment letters checked for $n employee(s) (missing ones created).\nDone.\n";
