<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/attendance_lib.php';
require_once __DIR__ . '/leave_lib.php';
require_once __DIR__ . '/payroll_lib.php';
require_once __DIR__ . '/admin_lib.php';
require_once __DIR__ . '/offer_letter.php';
require_once __DIR__ . '/appointment_letter.php';
require_once __DIR__ . '/job_duties.php';
require_once __DIR__ . '/experience_letter.php';
require_once __DIR__ . '/joining_letter.php';
require_once __DIR__ . '/projects_lib.php';
require_once __DIR__ . '/notes_lib.php';
require_once __DIR__ . '/support_lib.php';
require_once __DIR__ . '/layout.php';

const JOINING_LETTER_WAIT_DAYS = 90;

/** Days remaining (0 if already unlocked) until an employee's joining letter unlocks. */
function joining_letter_days_left(string $joinedOn): int
{
    $elapsed = intdiv(strtotime(today()) - strtotime($joinedOn), 86400);
    return max(0, JOINING_LETTER_WAIT_DAYS - $elapsed);
}
