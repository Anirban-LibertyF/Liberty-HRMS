<?php
declare(strict_types=1);

/**
 * Month pay for one employee: base (annual / 12), loss-of-pay deduction and the overtime payout.
 * Every full shift length (7h) of overtime earns one extra day's basic pay. Returns all the components.
 */
function payroll_calc(array $user, string $ym): array
{
    $first = "$ym-01";
    $last = date('Y-m-t', strtotime($first));
    $monthDays = (int)date('t', strtotime($first));
    $monthly = round((float)$user['base_salary'] / 12, 2);
    $daily = round($monthly / $monthDays, 2);

    $days = month_overview($user, $ym);
    $sum = month_summary($days);
    $unpaid = (float)q_val('SELECT COALESCE(SUM(lr.days),0) FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id
                            WHERE lr.user_id=? AND lt.is_paid=0 AND lr.status="approved" AND lr.from_date BETWEEN ? AND ?', [$user['id'], $first, $last]);
    $beforeJoin = $user['joined_on'] > $first ? (int)round((strtotime($user['joined_on']) - strtotime($first)) / 86400) : 0;
    $lop = min((float)$monthDays, $sum['absent'] + $unpaid + $beforeJoin);
    $lopAmt = round($monthly / $monthDays * $lop, 2);
    $ot = month_overtime($days, (int)($user['required_min'] ?? q_val('SELECT required_min FROM shifts WHERE id=?', [$user['shift_id']])));
    $otPay = round($monthly / $monthDays * $ot['payouts'], 2);
    $attPay = max(0, round($monthly - $lopAmt, 2));
    return ['monthly' => $monthly, 'daily' => $daily, 'lop' => $lop, 'lop_amount' => $lopAmt, 'att_pay' => $attPay,
            'ot_min' => $ot['total_min'], 'ot_payouts' => $ot['payouts'], 'ot_pay' => $otPay, 'net' => round($attPay + $otPay, 2), 'summary' => $sum];
}

/**
 * Builds (or rebuilds) the payslip for one employee/month.
 * Pay = monthly salary (annual / 12) shown as the base salary; every other component is 0.00.
 * One day's pay = monthly salary / calendar days of the month (30 or 31). Present days, paid leave, company holidays and
 * week offs are all paid, so full pay needs 26 working days + 4 week offs (30-day month) or 27 + 4 (31-day month).
 * Overtime: every 7h (one shift) of overtime accumulated in the month adds one extra day's pay (overtime_pay).
 * Every other day (absent, approved unpaid leave, days before joining) is loss of pay.
 * Returns false when the employee had not joined yet by the end of that month (nothing is created).
 */
function generate_payslip(array $user, int $year, int $month): bool
{
    $ym = sprintf('%04d-%02d', $year, $month);
    $first = "$ym-01";
    $last = date('Y-m-t', strtotime($first));
    if ($last < $user['joined_on']) return false;

    $c = payroll_calc($user, $ym);
    $monthly = $c['monthly']; $lop = $c['lop']; $lopAmt = $c['lop_amount']; $otPay = $c['ot_pay']; $net = $c['net'];

    q('INSERT INTO payslips (user_id, pay_year, pay_month, basic, hra, allowances, overtime_pay, pf, tax, prof_tax, lop_days, lop_amount, net_pay, generated_at)
       VALUES (?,?,?,?,0,0,?,0,0,0,?,?,?,NOW())
       ON DUPLICATE KEY UPDATE basic=VALUES(basic), hra=0, allowances=0, overtime_pay=VALUES(overtime_pay), pf=0, tax=0, prof_tax=0, lop_days=VALUES(lop_days), lop_amount=VALUES(lop_amount), net_pay=VALUES(net_pay), generated_at=NOW()',
        [$user['id'], $year, $month, $monthly, $otPay, $lop, $lopAmt, $net]);
    return true;
}

/** Amount in words for payslips, Indian numbering: "Indian Rupee Seventy-Seven Thousand Only". */
function amount_in_words(float $amount): string
{
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $below100 = fn(int $n): string => $n < 20 ? $ones[$n] : $tens[intdiv($n, 10)] . ($n % 10 ? '-' . $ones[$n % 10] : '');
    $below1000 = fn(int $n): string => trim(($n >= 100 ? $ones[intdiv($n, 100)] . ' Hundred ' : '') . ($n % 100 ? $below100($n % 100) : ''));
    $total = (int)round($amount * 100);
    $rupees = intdiv($total, 100);
    $paise = $total % 100;
    if ($rupees === 0 && $paise === 0) return 'Indian Rupee Zero Only';
    $parts = [];
    foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$unit, $name]) {
        if ($rupees >= $unit) { $parts[] = $below100(intdiv($rupees, $unit)) . ' ' . $name; $rupees %= $unit; }
    }
    if ($rupees > 0) $parts[] = $below1000($rupees);
    $words = implode(' ', $parts);
    if ($paise) $words = ($words ? $words . ' and ' : '') . $below100($paise) . ' Paise';
    return 'Indian Rupee ' . $words . ' Only';
}
