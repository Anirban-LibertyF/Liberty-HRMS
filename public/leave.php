<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];
$year = (int)date('Y');

$ok = $_SESSION['leave_ok'] ?? null; unset($_SESSION['leave_ok']);
$bal = leave_balances($uid, $year);
$types = leave_types();
$wb = weekoff_balance($user, date('Y-m'));

$draft = null;
if (!empty($_GET['draft'])) $draft = q_one('SELECT * FROM leave_requests WHERE id=? AND user_id=? AND status="draft"', [(int)$_GET['draft'], $uid]);

$perPage = 5;
$page = max(1, (int)($_GET['p'] ?? 1));
$total = (int)q_val('SELECT COUNT(*) FROM leave_requests WHERE user_id=?', [$uid]);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$hist = q_all('SELECT lr.*, lt.name AS type_name, lt.code, rv.name AS reviewer FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id
               LEFT JOIN users rv ON rv.id=lr.reviewed_by WHERE lr.user_id=? ORDER BY lr.applied_at DESC, lr.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), [$uid]);
$cnt = ['all' => $total, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach (q_all('SELECT status, COUNT(*) c FROM leave_requests WHERE user_id=? GROUP BY status', [$uid]) as $r) if (isset($cnt[$r['status']])) $cnt[$r['status']] = (int)$r['c'];
$hols = array_column(q_all('SELECT holiday_date FROM holidays'), 'holiday_date');

$cardMeta = [
    'PL' => ['Calendar Year', 'palm', 'b-blue'], 'LOP' => ['Zero Impact', 'x', 'b-blue'],
];
$statusBadge = ['approved' => 'b-green', 'pending' => 'b-amber', 'rejected' => 'b-red', 'cancelled' => 'b-slate', 'draft' => 'b-slate'];

render_header('Leave Application', 'leave', $user);
?>
<div class="page-head">
  <div><p class="muted" style="margin-bottom:6px">Workforce Management &nbsp;›&nbsp; <b style="color:var(--ink);font-weight:500">Leave &amp; Time Off</b></p>
    <h1>Leave Application &amp; Management</h1><p class="muted">Manage leave balances, submit paid leave requests, and track approval workflows.</p></div>
  <?php if ($ok): ?><div class="alert alert-ok" style="max-width:420px;margin:0"><?= icon('check', 22) ?><span><b>Request Submitted</b><br><small><?= e($ok) ?></small></span><button class="alert-x" aria-label="Dismiss"><?= icon('x', 16) ?></button></div><?php endif; ?>
</div>

<div class="grid g-4">
  <?php foreach ($bal as $code => $b): [$tag, $ic, $cls] = $cardMeta[$code] ?? ['', 'check', 'b-slate']; $isLop = (float)$b['quota'] == 0.0; ?>
    <div class="card lt-card" style="border-top-color:<?= $isLop ? '#94a3b8' : e($b['color']) ?>">
      <div class="lt-top"><h3><?= icon($ic, 20) ?> <span><?= e($b['name']) ?></span></h3><span class="badge <?= $cls ?>"><?= e($tag) ?></span></div>
      <?php if ($isLop): ?>
        <div class="big"><?= (int)ceil($b['used']) ?> <small>days taken</small></div>
        <p class="muted" style="font-size:12.5px">Loss of Pay accumulator (YTD)</p>
        <div class="meter" style="margin-top:14px"><i style="width:<?= min(100, $b['used'] * 10) ?>%"></i></div>
        <div class="lt-foot"><span>Salary deduction: <?= $b['used'] > 0 ? 'Applicable' : 'Nil' ?></span><b><?= $b['used'] > 0 ? 'Review' : 'Optimal' ?></b></div>
      <?php else: ?>
        <div class="big"><?= (int)floor($b['remaining']) ?> <small>/ <?= rtrim(rtrim(number_format($b['quota'], 1), '0'), '.') ?> days</small></div>
        <p class="muted" style="font-size:12.5px">Remaining balance (<?= (int)ceil($b['used']) ?> days utilized)</p>
        <div class="meter" style="margin-top:14px"><i style="width:<?= $b['pct'] ?>%;background:<?= e($b['color']) ?>"></i></div>
        <div class="lt-foot"><span>Accrued: <?= number_format((float)$b['quota'], 2) ?></span><b><?= $b['pct'] ?>% Available</b></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <div class="card lt-card" style="border-top-color:var(--blue)">
    <div class="lt-top"><h3><?= icon('palm', 20) ?> <span>Week Offs</span></h3><span class="badge b-blue"><?= e(date('F Y')) ?></span></div>
    <div class="big"><?= $wb['left'] ?> <small>/ <?= $wb['total'] ?> days left</small></div>
    <p class="muted" style="font-size:12.5px"><?= $wb['used'] ?> used this month<?= $wb['carry'] ? ' · ' . $wb['carry'] . ' carried over' : '' ?></p>
    <div class="meter" style="margin-top:14px"><i style="width:<?= $wb['total'] ? round($wb['left'] / $wb['total'] * 100) : 0 ?>%;background:var(--blue)"></i></div>
    <div class="lt-foot"><span><?= $wb['per_month'] ?> new each month · max 1 per week</span><b>Resets <?= e(date('M j', strtotime('first day of next month'))) ?></b></div>
  </div>
  <div class="card lt-card apply-card" style="border-top-color:var(--brand)">
    <div class="lt-top"><h3><?= icon('calendar', 20) ?> <span>Time Off</span></h3></div>
    <p class="muted" style="font-size:12.5px;margin:6px 0 16px">Submit an approval request to your reporting line manager.</p>
    <button type="button" class="btn btn-brand btn-block" data-open="#leaveDlg"><?= icon('send', 16) ?> Apply for Leave</button>
  </div>
</div>

<dialog id="leaveDlg" class="leave-dlg">
  <form class="card" id="leaveForm" method="post" action="actions.php" enctype="multipart/form-data" data-holidays='<?= e(json_encode($hols)) ?>'>
    <?= csrf_field() ?><input type="hidden" name="do" value="leave_apply" id="leaveDo"><input type="hidden" name="draft_id" value="<?= (int)($draft['id'] ?? 0) ?>">
    <div class="card-head" style="margin-bottom:6px"><h2><?= icon('calendar', 22) ?> Apply for Leave</h2><button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
    <p class="muted" style="margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid var(--line)">Submit an approval request to your reporting line manager.</p>
    <div class="field"><label class="f" for="lt">LEAVE TYPE <span class="req">*</span></label>
      <select class="input" id="lt" name="leave_type_id" required>
        <?php foreach ($types as $t): $b = $bal[$t['code']]; ?>
          <option value="<?= $t['id'] ?>" <?= ($draft['leave_type_id'] ?? 0) == $t['id'] ? 'selected' : '' ?> <?= $t['code'] === 'PL' && !$draft ? 'selected' : '' ?>><?= e($t['name']) ?> <?= (float)$t['quota'] > 0 ? '(' . (int)floor($b['remaining']) . ' days remaining)' : '(no balance limit)' ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="field-row field">
      <div><label class="f" for="fd">FROM DATE <span class="req">*</span></label><input class="input" type="date" id="fd" name="from_date" required value="<?= e($draft['from_date'] ?? '') ?>" min="<?= date('Y-m-d', strtotime('-30 days')) ?>"></div>
      <div><label class="f" for="td">TO DATE <span class="req">*</span></label><input class="input" type="date" id="td" name="end_date" required value="<?= e($draft['end_date'] ?? '') ?>"></div>
    </div>
    <div class="inline-note"><span class="badge b-mint" id="duration" style="margin-left:auto">Duration: --</span></div>
    <div class="field"><div style="display:flex;justify-content:space-between"><label class="f" for="rs">REASON <span class="req">*</span></label><span class="muted" style="font-size:12px">Max 300 characters</span></div>
      <textarea class="input" id="rs" name="reason" maxlength="300" required placeholder="Provide detailed context for your leave request..."><?= e($draft['reason'] ?? '') ?></textarea></div>
    <div class="field"><label class="f">ATTACH SUPPORTING DOCUMENT (OPTIONAL)</label>
      <div class="drop" data-for="leaveDoc"><?= icon('upload', 26) ?><b>Drag &amp; drop medical certificate or approval PDF</b><span class="muted" style="font-size:12.5px">Maximum file size: 10MB (.PDF, .PNG, .JPG)</span><div class="fname"><?= e($draft['doc_name'] ?? '') ?></div></div>
      <input type="file" id="leaveDoc" name="doc" accept=".pdf,.png,.jpg,.jpeg" hidden></div>
    <div class="btn-row"><button class="btn btn-brand" style="flex:1"><?= icon('send', 16) ?> Submit Leave Request</button>
      <button class="btn" style="flex:1" formnovalidate onclick="document.getElementById('leaveDo').value='leave_draft'"><?= icon('bookmark', 16) ?> Save as Draft</button></div>
  </form>
</dialog>
<?php if ($draft): ?><script>document.getElementById('leaveDlg').showModal();</script><?php endif; ?>

<div class="mt">
  <section class="card leave-history-panel" style="padding:0">
    <div class="leave-history-head">
      <div><h2 class="leave-history-title"><?= icon('history', 22) ?> Past Leave Applications &amp; History</h2><p class="muted" style="margin-top:6px;max-width:420px">Log of previous requests, manager review notes, and current statuses.</p></div>
      <div class="seg" role="tablist">
        <button class="on" data-filter="all">All (<?= $cnt['all'] ?>)</button><button data-filter="pending">Pending (<?= $cnt['pending'] ?>)</button>
        <button data-filter="approved">Approved (<?= $cnt['approved'] ?>)</button><button data-filter="rejected">Rejected (<?= $cnt['rejected'] ?>)</button></div>
    </div>
    <div class="table-wrap leave-history-wrap" style="margin-top:18px"><table class="t leave-history-table">
      <thead><tr><th>Leave Type</th><th>Applied On</th><th>Period</th><th>Days</th><th>Reason</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($hist as $l): ?>
        <tr data-status="<?= e($l['status']) ?>">
          <td><b><?= e($l['type_name']) ?></b></td>
          <td><?= e(fmt_date($l['applied_at'])) ?></td>
          <td><?= e(leave_period($l)) ?></td>
          <td><?= number_format((float)$l['days'], 1) ?></td>
          <td class="leave-history-reason"><span class="leave-history-reason-text" title="<?= e($l['reason'] . ($l['review_note'] ? ' · ' . ($l['reviewer'] ?? 'Manager') . ': ' . $l['review_note'] : '')) ?>"><?= e($l['reason']) ?><?php if ($l['review_note']): ?> · <?= e($l['reviewer'] ?? 'Manager') ?>: <?= e($l['review_note']) ?><?php endif; ?></span>
            <?php if ($l['doc_path']): ?> <a href="download.php?t=leave&id=<?= $l['id'] ?>" title="<?= e($l['doc_name']) ?>"><?= icon('clip', 14) ?></a><?php endif; ?></td>
          <td class="leave-history-status"><span class="badge <?= $statusBadge[$l['status']] ?>"><?= e(ucfirst($l['status'])) ?></span></td>
          <td class="leave-history-actions">
            <?php if ($l['status'] === 'draft'): ?><a class="btn btn-sm" href="leave.php?draft=<?= $l['id'] ?>">Edit</a><?php endif; ?>
            <?php if (in_array($l['status'], ['pending', 'draft'], true)): ?>
              <form method="post" action="actions.php" class="inline" data-confirm="Cancel this leave request?"><?= csrf_field() ?><input type="hidden" name="do" value="leave_cancel"><input type="hidden" name="id" value="<?= $l['id'] ?>"><button class="btn btn-sm btn-danger">Cancel</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$hist): ?><tr><td colspan="7" class="empty">No leave applications yet.</td></tr><?php endif; ?>
      </tbody></table></div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 22px;border-top:1px solid var(--line)">
      <span class="muted" style="font-size:13px">Showing <?= count($hist) ?> of <?= $total ?> recorded applications</span>
      <div class="btn-row" style="margin:0">
        <a class="btn btn-sm <?= $page <= 1 ? 'disabled' : '' ?>" <?= $page <= 1 ? 'aria-disabled="true" style="opacity:.5;pointer-events:none"' : '' ?> href="leave.php?p=<?= $page - 1 ?>">Previous</a>
        <a class="btn btn-sm" <?= $page >= $pages ? 'aria-disabled="true" style="opacity:.5;pointer-events:none"' : '' ?> href="leave.php?p=<?= $page + 1 ?>">Next</a></div>
    </div>
  </section>
</div>

<section class="card mt" style="background:var(--surface-2);display:flex;align-items:center;gap:16px;flex-wrap:wrap">
  <span class="feed-ic" style="background:var(--surface)"><?= icon('shield', 20) ?></span>
  <div style="flex:1;min-width:240px"><b style="font-size:17px">HR Leave Policy Reference <?= $year ?></b><p class="muted">Leave other than the scheduled weekly off must be requested at least 3 days in advance. Leave is subject to approval; emergencies are considered individually.</p></div>
  <a class="btn" href="policy.php">Privacy Policy</a>
</section>
<?php render_footer(); ?>
