<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];

$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : today();
if ($date > today()) $date = today();
$log = q_one('SELECT wl.*, rv.name AS reviewer FROM work_logs wl LEFT JOIN users rv ON rv.id=wl.reviewed_by WHERE wl.user_id=? AND wl.log_date=?', [$uid, $date]);
$editable = $date >= date('Y-m-d', strtotime('-14 days')) && (!$log || in_array($log['status'], ['draft', 'rejected'], true));
$hist = q_all('SELECT * FROM work_logs WHERE user_id=? ORDER BY log_date DESC LIMIT 30', [$uid]);
$cls = ['draft' => 'b-slate', 'submitted' => 'b-amber', 'approved' => 'b-green', 'rejected' => 'b-red'];

render_header('Daily Work Log', 'worklog', $user);
page_title('Daily Work Log', 'Record deliverables and accomplishments for each working day.', '<form method="get"><input class="input" type="date" name="date" value="' . e($date) . '" max="' . today() . '" onchange="this.form.submit()" aria-label="Log date"></form>');
?>
<div class="grid g-3-2" style="align-items:start">
  <form class="card" id="workLog" data-date="<?= e($date) ?>" method="post" action="actions.php" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="do" value="worklog_submit"><input type="hidden" name="log_date" value="<?= e($date) ?>"><input type="hidden" name="back" value="worklog.php?date=<?= e($date) ?>">
    <div class="card-head"><h2><?= icon('log', 20) ?> Log for <?= e(fmt_date($date, 'D, M j, Y')) ?></h2>
      <span class="badge badge-text <?= $cls[$log['status'] ?? 'draft'] ?>" id="wlSaved"><?= $log ? ucfirst($log['status']) : 'Not started' ?></span></div>
    <?php if ($log && $log['status'] === 'rejected'): ?><div class="alert alert-error"><?= icon('x', 18) ?><span>Returned by <?= e($log['reviewer'] ?? 'manager') ?>: <?= e($log['review_note'] ?: 'Please revise and resubmit.') ?></span></div><?php endif; ?>
    <label class="f" for="wlContent">Key Accomplishments / Deliverables</label>
    <textarea class="input" id="wlContent" name="content" rows="9" maxlength="4000" <?= $editable ? '' : 'readonly' ?> placeholder="- What did you complete today?"><?= e($log['content'] ?? '') ?></textarea>
    <div class="btn-row" style="align-items:center"><input class="input" style="max-width:300px" id="wlTag" name="tag" maxlength="100" placeholder="Tag (e.g. Sprint 34: Foundation)" value="<?= e($log['tag'] ?? '') ?>" <?= $editable ? '' : 'readonly' ?>>
      <span id="wlChips"><?php if (!empty($log['attach_name'])): ?><span class="chip"><span><?= e($log['attach_name']) ?></span><?php if ($editable): ?><button type="button" aria-label="Remove attachment">&times;</button><?php endif; ?></span> <a href="download.php?t=worklog&id=<?= $log['id'] ?>" style="font-size:12.5px">Download</a><?php endif; ?></span></div>
    <?php if ($editable): ?>
      <div class="btn-row"><button type="button" class="btn" id="wlAttach"><?= icon('clip', 16) ?> Attach file</button><input type="file" id="wlFile" accept=".pdf,.png,.jpg,.jpeg" hidden><button class="btn btn-brand">Submit Work Log</button></div>
    <?php else: ?><p class="muted" style="margin-top:14px">This log is <?= $log ? e($log['status']) . ' and locked' : 'outside the 14-day edit window' ?>.</p><?php endif; ?>
  </form>

  <section class="card">
    <div class="card-head"><h2>Log History</h2></div>
    <?php foreach ($hist as $h): ?>
      <a class="feed-item" href="worklog.php?date=<?= e($h['log_date']) ?>" style="color:inherit;border-bottom:1px solid var(--line-2)">
        <span class="feed-ic blue"><?= icon('log', 16) ?></span>
        <div style="flex:1"><div class="tt"><span><?= e(fmt_date($h['log_date'], 'D, M j')) ?> <?= $h['tag'] ? '· ' . e($h['tag']) : '' ?></span><span class="badge badge-text <?= $cls[$h['status']] ?>"><?= e(ucfirst($h['status'])) ?></span></div>
          <p><?= e(mb_strimwidth(str_replace("\n", ' ', $h['content']), 0, 90, '…')) ?></p></div></a>
    <?php endforeach; ?>
    <?php if (!$hist): ?><p class="empty">No work logs yet.</p><?php endif; ?>
  </section>
</div>
<?php render_footer(); ?>
