<?php
declare(strict_types=1);

function leave_types(): array { return q_all('SELECT * FROM leave_types ORDER BY sort_order'); }

/** Working days covered by a leave request (weekends and public holidays excluded). */
function calc_leave_days(string $from, string $to, bool $half): float
{
    if ($to < $from) return 0.0;
    $n = count(working_dates($from, $to));
    return $half ? ($n >= 1 ? 0.5 : 0.0) : (float)$n;
}

/** Per-type balances for a calendar year. "used" counts approved + pending requests. */
function leave_balances(int $uid, int $year): array
{
    $used = [];
    foreach (q_all('SELECT leave_type_id, status, SUM(days) AS d FROM leave_requests
                    WHERE user_id=? AND status IN ("approved","pending") AND YEAR(from_date)=? GROUP BY leave_type_id, status', [$uid, $year]) as $r) {
        $used[$r['leave_type_id']]['all'] = ($used[$r['leave_type_id']]['all'] ?? 0) + (float)$r['d'];
        $used[$r['leave_type_id']][$r['status']] = (float)$r['d'];
    }
    $out = [];
    foreach (leave_types() as $t) {
        $u = $used[$t['id']]['all'] ?? 0.0;
        $quota = (float)$t['quota'];
        $out[$t['code']] = [
            'quota' => $quota,
            'used' => $u,
            'approved' => $used[$t['id']]['approved'] ?? 0.0,
            'pending' => $used[$t['id']]['pending'] ?? 0.0,
            'remaining' => $quota > 0 ? max(0, $quota - $u) : 0.0,
            'pct' => $quota > 0 ? round(max(0, $quota - $u) / $quota * 100, 1) : 0,
        ] + $t;
    }
    return $out;
}

/**
 * Validates a leave request. Returns an error string, or null when valid.
 */
function validate_leave(array $user, array $type, string $from, string $to, bool $half, string $reason, ?int $ignoreId = null): ?string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) return 'Please choose valid from and to dates.';
    if ($to < $from) return 'The "To" date cannot be before the "From" date.';
    if ($from < $user['joined_on']) return 'Leave cannot start before your joining date.';
    if ($from < date('Y-m-d', strtotime('-30 days'))) return 'Back-dated leave older than 30 days needs HR regularization.';
    if (strtotime($to) - strtotime($from) > 120 * 86400) return 'Leave span is too long.';
    if (mb_strlen(trim($reason)) < 3) return 'Please provide a reason for your leave.';
    if (mb_strlen($reason) > 300) return 'Reason must be 300 characters or fewer.';
    if ($half && $from !== $to) return 'Half-day leave must be a single day.';

    $days = calc_leave_days($from, $to, $half);
    if ($days <= 0) return 'The selected dates contain no working days (weekends and holidays are excluded).';

    $overlap = q_val('SELECT COUNT(*) FROM leave_requests WHERE user_id=? AND status IN ("pending","approved") AND from_date<=? AND end_date>=? AND id<>?',
        [$user['id'], $to, $from, $ignoreId ?? 0]);
    if ($overlap > 0) return 'These dates overlap with an existing leave request.';

    if ((float)$type['quota'] > 0) {
        $bal = leave_balances((int)$user['id'], (int)substr($from, 0, 4))[$type['code']];
        $already = $ignoreId ? (float)q_val('SELECT days FROM leave_requests WHERE id=? AND status="pending"', [$ignoreId]) : 0.0;
        if ($days > $bal['remaining'] + $already) return "Insufficient {$type['name']} balance ({$bal['remaining']} day(s) remaining, {$days} requested).";
    }
    if ($days > 3 && strtotime($from) < time() + 48 * 3600 && $from >= today()) {
        return 'Leaves longer than 3 days need sign-off at least 48 hours before the start date (HR policy).';
    }
    return null;
}

/** Human readable period for history tables. */
function leave_period(array $l): string
{
    if ($l['half_day']) return fmt_date($l['from_date'], 'M d') . ' (Half Day)';
    if ($l['from_date'] === $l['end_date']) return fmt_date($l['from_date'], 'M d');
    return fmt_date($l['from_date'], 'M d') . ' - ' . fmt_date($l['end_date'], 'M d');
}

/** Who can review a given employee's requests. */
function can_review(array $reviewer, array $employee): bool
{
    if ($reviewer['id'] === $employee['id']) return false;
    if ($reviewer['role'] === 'admin') return true;
    return $reviewer['role'] === 'manager' && (int)$employee['manager_id'] === (int)$reviewer['id'];
}
