<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$ym = preg_match('/^\d{4}-\d{2}$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
$days = month_overview($user, $ym);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="timesheet_' . $user['emp_code'] . '_' . $ym . '.csv"');
$o = fopen('php://output', 'w');
fwrite($o, "\xEF\xBB\xBF");
fputcsv($o, ['Date', 'Day', 'Status', 'Check In', 'Check Out', 'Worked (min)', 'Break (min)', 'Overtime (min)', 'Note']);
foreach ($days as $d => $r) {
    $a = $r['att'];
    $note = $r['holiday']['name'] ?? ($r['leave']['type_name'] ?? '');
    // Prefix cells starting with formula characters so spreadsheets don't execute them.
    $safe = fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
    fputcsv($o, [$d, date('D', strtotime($d)), ucfirst($r['type']), $a ? date('H:i', strtotime($a['first_in'])) : '', $a && $a['last_out'] ? date('H:i', strtotime($a['last_out'])) : '',
        $a['worked_min'] ?? '', $a['break_min'] ?? '', $a['overtime_min'] ?? '', $safe($note)]);
}
fclose($o);
