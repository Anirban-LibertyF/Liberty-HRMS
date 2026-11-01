<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$mgr = $user['manager_id'] ? q_val('SELECT name FROM users WHERE id=?', [$user['manager_id']]) : null;
$pdf = joining_letter_pdf($user, $mgr);
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline') . '; filename="Joining_Letter_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)$user['emp_code']) . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('X-Content-Type-Options: nosniff');
echo $pdf;
