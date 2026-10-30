<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];
$isAdm = $user['role'] === 'admin';

$month = preg_match('/^\d{4}-\d{2}$/', $_GET['m'] ?? '') ? $_GET['m'] : '';
$emp = $isAdm ? (int)($_GET['emp'] ?? 0) : $uid;
$where = []; $args = [];
if (!$isAdm || $emp) { $where[] = 'e.created_by=?'; $args[] = $emp; }
if ($month) { $where[] = 'DATE_FORMAT(e.expense_date,"%Y-%m")=?'; $args[] = $month; }
$rows = q_all('SELECT e.*, u.name AS by_name, ed.name AS editor_name, (SELECT COUNT(*) FROM expense_items i WHERE i.expense_id=e.id) AS n_items
               FROM expenses e JOIN users u ON u.id=e.created_by LEFT JOIN users ed ON ed.id=e.updated_by'
              . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY e.expense_date DESC, e.id DESC', $args);
$grand = array_sum(array_map(fn($r) => (float)$r['total'], $rows));
$employees = $isAdm ? q_all('SELECT id, name FROM users WHERE role<>"admin" ORDER BY name') : [];

render_header('Expenses', 'expenses', $user);
$filters = '<form method="get" style="display:flex;gap:8px;flex-wrap:wrap">'
    . ($isAdm ? '<select class="input" name="emp" onchange="this.form.submit()" aria-label="Employee"><option value="0">All employees</option>' . implode('', array_map(fn($x) => '<option value="' . $x['id'] . '"' . ($emp === (int)$x['id'] ? ' selected' : '') . '>' . e($x['name']) . '</option>', $employees)) . '</select>' : '')
    . '<input class="input" type="month" name="m" value="' . e($month) . '" onchange="this.form.submit()" aria-label="Month"></form>'
    . '<a class="btn btn-brand" href="expense.php">' . icon('plus', 16) . ' Add expense</a>';
page_title('Expenses', $isAdm ? 'All office expenses added by employees.' : 'Office expenses you have added.', $filters);
?>
<section class="card">
  <div class="card-head"><h2><?= icon('rupee', 20) ?> Expense list</h2><span class="badge b-mint">Total: <?= money($grand) ?></span></div>
  <div class="hscroll"><table class="t">
    <thead><tr><th>#</th><th>Heading</th><th>Date</th><th>Items</th><?php if ($isAdm): ?><th>Added by</th><?php endif; ?><th style="text-align:right">Total</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td><td><a href="expense.php?id=<?= $r['id'] ?>"><?= e($r['title']) ?></a></td><td><?= e(fmt_date($r['expense_date'])) ?></td><td><?= (int)$r['n_items'] ?></td>
        <?php if ($isAdm): ?><td><?= e($r['by_name']) ?><?= $r['editor_name'] && $r['editor_name'] !== $r['by_name'] ? '<br><small class="muted">edited by ' . e($r['editor_name']) . '</small>' : '' ?></td><?php endif; ?>
        <td style="text-align:right"><b><?= money($r['total']) ?></b></td>
        <td style="white-space:nowrap;text-align:right"><a class="btn btn-sm" href="expense_print.php?id=<?= $r['id'] ?>&auto=1" target="_blank" rel="noopener" title="Download A4 PDF"><?= icon('download', 15) ?> Download</a> <a class="icon-btn" href="expense.php?id=<?= $r['id'] ?>" aria-label="View / edit"><?= icon('edit', 16) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (!$rows): ?><p class="empty">No expenses found.</p><?php endif; ?>
</section>
<?php render_footer(); ?>
