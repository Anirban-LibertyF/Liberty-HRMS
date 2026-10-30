<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$uid = (int)$user['id'];
$isAdm = $user['role'] === 'admin';

$eid = (int)($_GET['id'] ?? 0);
$ex = $eid ? q_one('SELECT e.*, u.name AS by_name, ed.name AS editor_name FROM expenses e JOIN users u ON u.id=e.created_by LEFT JOIN users ed ON ed.id=e.updated_by WHERE e.id=?', [$eid]) : null;
if ($eid && (!$ex || !($isAdm || (int)$ex['created_by'] === $uid))) {
    http_response_code(404); render_header('Expense', 'expenses', $user); page_title('Expense not found'); echo '<p class="muted">This expense does not exist or you do not have access to it.</p>'; render_footer(); exit;
}
$items = $ex ? q_all('SELECT * FROM expense_items WHERE expense_id=? ORDER BY sl', [$eid]) : [];
if (!$items) $items = [['item_name' => '', 'quantity' => '', 'price' => '', 'amount' => 0]];
$num = fn($v) => $v === '' ? '' : rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');

render_header($ex ? $ex['title'] : 'Add expense', 'expenses', $user);
page_title($ex ? 'Expense details' : 'Add expense', $ex ? 'Added by ' . e($ex['by_name']) . ' on ' . e(fmt_date($ex['created_at'])) . ($ex['editor_name'] ? ' · last edited by ' . e($ex['editor_name']) . ', ' . e(fmt_date($ex['updated_at'], 'M d, h:i A')) : '') : 'Amounts are calculated automatically.', '<a class="btn" href="expenses.php">All expenses</a>' . ($ex ? '<a class="btn btn-brand" href="expense_print.php?id=' . $eid . '&auto=1" target="_blank" rel="noopener">' . icon('download', 16) . ' Download</a>' : ''));
?>
<form class="card" id="expForm" method="post" action="actions.php" style="max-width:900px">
  <?= csrf_field() ?><input type="hidden" name="do" value="expense_save"><input type="hidden" name="id" value="<?= $eid ?>"><input type="hidden" name="back" value="<?= $eid ? 'expense.php?id=' . $eid : 'expense.php' ?>">
  <div class="field-row field"><div><label class="f" for="eTitle">Expenses heading <span class="req">*</span></label><input class="input" id="eTitle" name="title" required maxlength="200" value="<?= e($ex['title'] ?? '') ?>" placeholder="e.g. Office pantry – October"></div>
    <div><label class="f" for="eDate">Date <span class="req">*</span></label><input class="input" type="date" id="eDate" name="expense_date" required value="<?= e($ex['expense_date'] ?? today()) ?>"></div></div>

  <div class="hscroll"><table class="t exp-table" style="min-width:560px">
    <thead><tr><th style="width:56px">Sl no.</th><th>Item name</th><th style="width:110px">Quantity</th><th style="width:130px">Price</th><th style="width:140px;text-align:right">Amount</th><th style="width:44px"></th></tr></thead>
    <tbody id="expRows">
    <?php foreach ($items as $it): ?>
      <tr><td class="sl"></td>
        <td><input class="input" name="item_name[]" maxlength="200" value="<?= e($it['item_name']) ?>" placeholder="Item"></td>
        <td><input class="input" name="qty[]" type="number" min="0" step="any" inputmode="decimal" value="<?= $num($it['quantity']) ?>"></td>
        <td><input class="input" name="price[]" type="number" min="0" step="any" inputmode="decimal" value="<?= $num($it['price']) ?>"></td>
        <td class="amt" style="text-align:right"><?= number_format((float)$it['amount'], 2) ?></td>
        <td><button type="button" class="icon-btn" data-exp-del aria-label="Remove row"><?= icon('trash', 16) ?></button></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="4" style="text-align:right"><b>Total expense</b></td><td style="text-align:right"><b>₹ <span id="expTotal"><?= number_format((float)($ex['total'] ?? 0), 2) ?></span></b></td><td></td></tr></tfoot>
  </table></div>
  <div class="btn-row"><button type="button" class="btn" id="expAdd"><?= icon('plus', 16) ?> Add item</button></div>

  <div class="btn-row" style="margin-top:18px"><button class="btn btn-brand"><?= icon('save', 16) ?> <?= $ex ? 'Save changes' : 'Save expense' ?></button>
    <?php if ($ex): ?><button class="btn btn-danger" type="submit" form="delExpense"><?= icon('trash', 16) ?> Delete</button><?php endif; ?></div>
</form>
<?php if ($ex): ?><form id="delExpense" method="post" action="actions.php" data-confirm="Delete this expense?"><?= csrf_field() ?><input type="hidden" name="do" value="expense_delete"><input type="hidden" name="id" value="<?= $eid ?>"></form><?php endif; ?>
<?php render_footer(); ?>
