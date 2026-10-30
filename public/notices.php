<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];

$sort = ($_GET['sort'] ?? 'new') === 'old' ? 'old' : 'new';
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') && strtotime($_GET['date']) ? $_GET['date'] : '';
$rows = q_all('SELECT * FROM announcements' . ($date ? ' WHERE posted_on=?' : '') . ' ORDER BY posted_on ' . ($sort === 'old' ? 'ASC, id ASC' : 'DESC, id DESC'), $date ? [$date] : []);
// opening the page counts as reading the announcement notifications
q("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0 AND (message LIKE 'New announcement:%' OR message LIKE 'Announcement updated:%')", [$uid]);

$qs = fn(array $over) => 'notices.php?' . http_build_query(array_filter(array_merge(['sort' => $sort, 'date' => $date], $over), fn($v) => $v !== ''));
render_header('Notices', 'notices', $user);
page_title('Notices', 'All announcements from the administration.');
?>
<section class="card">
  <div class="card-head" style="flex-wrap:wrap">
    <div class="seg" role="group" aria-label="Sort notices"><a href="<?= e($qs(['sort' => 'new'])) ?>" class="<?= $sort === 'new' ? 'on' : '' ?>">Newest first</a><a href="<?= e($qs(['sort' => 'old'])) ?>" class="<?= $sort === 'old' ? 'on' : '' ?>">Oldest first</a></div>
    <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input type="hidden" name="sort" value="<?= e($sort) ?>">
      <input class="input" type="date" name="date" value="<?= e($date) ?>" max="<?= today() ?>" onchange="this.form.submit()" aria-label="Show notices of a date" style="width:auto">
      <?php if ($date): ?><a class="btn btn-sm" href="<?= e($qs(['date' => ''])) ?>"><?= icon('x', 14) ?> Clear date</a><?php endif; ?></form>
  </div>
  <p class="muted" style="margin-bottom:14px;font-size:13px"><?= count($rows) ?> notice<?= count($rows) === 1 ? '' : 's' ?><?= $date ? ' on ' . e(fmt_date($date, 'D, M j, Y')) : '' ?> · <?= $sort === 'new' ? 'newest' : 'oldest' ?> first</p>
  <?php foreach ($rows as $a): ?>
    <article class="ann" id="notice-<?= $a['id'] ?>"><div class="meta"><span><?= e($a['dept']) ?></span><span><?= e(fmt_date($a['posted_on'], 'D, M j, Y')) ?><?= $a['updated_at'] ? ' · Updated ' . e(fmt_date($a['updated_at'], 'M j')) : '' ?></span></div>
      <h3><?= e($a['title']) ?></h3><p style="color:var(--ink-2)"><?= nl2br(e($a['body'])) ?></p></article>
  <?php endforeach; ?>
  <?php if (!$rows): ?><p class="empty"><?= $date ? 'No notices were posted on this date.' : 'No notices yet.' ?></p><?php endif; ?>
</section>
<?php render_footer(); ?>
