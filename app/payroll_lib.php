<?php
declare(strict_types=1);

/**
 * Builds (or rebuilds) the payslip for one employee/month from salary, attendance and approved overtime.
 * Returns false when the employee had not joined yet by the end of that month (nothing is created).
 */
function generate_payslip(array $user, int $year, int $month): bool
{
    $ym = sprintf('%04d-%02d', $year, $month);
    $first = "$ym-01";
    $last = date('Y-m-t', strtotime($first));
    if ($last < $user['joined_on']) return false;

    $gross = (float)$user['base_salary'] / 12;
    $basic = round($gross * 0.50, 2);
    $hra = round($gross * 0.20, 2);
    $allow = round($gross - $basic - $hra, 2);

    $days = month_overview($user, $ym);
    $sum = month_summary($days);
    $workdays = max(1, count(working_dates($first, $last)));

    // Loss of pay = unexcused absences + approved unpaid leave + working days before the joining date.
    $unpaid = (float)q_val('SELECT COALESCE(SUM(lr.days),0) FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id
                            WHERE lr.user_id=? AND lt.is_paid=0 AND lr.status="approved" AND lr.from_date BETWEEN ? AND ?', [$user['id'], $first, $last]);
    $beforeJoin = $user['joined_on'] > $first ? count(working_dates($first, date('Y-m-d', strtotime($user['joined_on'] . ' -1 day')))) : 0;
    $lop = min((float)$workdays, $sum['absent'] + $unpaid + $beforeJoin);
    $lopAmt = round($gross / $workdays * $lop, 2);
    $factor = max(0.0, 1 - $lop / $workdays);          // statutory deductions apply to the pay actually earned

    $hourly = $gross / $workdays / 7;
    $otPay = round($sum['ot_min'] / 60 * $hourly * 1.5, 2);

    $pf = round($basic * $factor * 0.12, 2);
    $tax = round(max(0, $gross - $lopAmt) * 0.05, 2);
    $pt = $factor > 0 ? 200.00 : 0.00;
    $net = max(0, round($gross - $lopAmt + $otPay - $pf - $tax - $pt, 2));

    q('INSERT INTO payslips (user_id, pay_year, pay_month, basic, hra, allowances, overtime_pay, pf, tax, prof_tax, lop_days, lop_amount, net_pay, generated_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
       ON DUPLICATE KEY UPDATE basic=VALUES(basic), hra=VALUES(hra), allowances=VALUES(allowances), overtime_pay=VALUES(overtime_pay), pf=VALUES(pf),
         tax=VALUES(tax), prof_tax=VALUES(prof_tax), lop_days=VALUES(lop_days), lop_amount=VALUES(lop_amount), net_pay=VALUES(net_pay), generated_at=NOW()',
        [$user['id'], $year, $month, $basic, $hra, $allow, $otPay, $pf, $tax, $pt, $lop, $lopAmt, $net]);
    return true;
}
