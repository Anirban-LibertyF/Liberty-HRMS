<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$isAdm = $user['role'] === 'admin';

$id = (int)($_GET['id'] ?? 0);
$ex = q_one('SELECT e.*, u.name AS by_name, u.emp_code, u.department, u.designation, ed.name AS editor_name
             FROM expenses e JOIN users u ON u.id=e.created_by LEFT JOIN users ed ON ed.id=e.updated_by WHERE e.id=?', [$id]);
if (!$ex || !($isAdm || (int)$ex['created_by'] === (int)$user['id'])) { http_response_code(404); exit('Expense not found.'); }
$items = q_all('SELECT * FROM expense_items WHERE expense_id=? ORDER BY sl', [$id]);
$qty = fn($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Expense EXP-<?= str_pad((string)$id, 4, '0', STR_PAD_LEFT) ?> · <?= e($ex['title']) ?></title>
<style>
  @page{size:A4;margin:14mm}
  *{box-sizing:border-box}
  body{margin:0;background:#e5e5e5;font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#000}
  .bar{max-width:210mm;margin:16px auto 0;display:flex;justify-content:space-between;gap:10px}
  .bar a,.bar button{font:inherit;font-weight:600;padding:9px 16px;border-radius:8px;border:1px solid #888;background:#fff;color:#000;cursor:pointer;text-decoration:none}
  .bar button{background:#000;border-color:#000;color:#fff}
  .sheet{width:210mm;min-height:297mm;margin:12px auto 24px;background:#fff;padding:14mm;box-shadow:0 6px 28px rgba(0,0,0,.2);position:relative}
  .head{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #000;padding-bottom:12px}
  .brand img{height:52px;width:auto;display:block;filter:grayscale(1) contrast(1.4)}
  .doc{text-align:right}.doc h1{margin:0;font-size:20px;letter-spacing:.08em;text-transform:uppercase}.doc div{color:#333;font-size:12px}
  .meta{display:grid;grid-template-columns:1fr 1fr;gap:10px 28px;margin:18px 0}
  .meta div{border:1px solid #000;border-radius:6px;padding:9px 12px}
  .meta span{display:block;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:#333}.meta b{font-size:14px}
  .title{margin:6px 0 10px;font-size:16px;font-weight:700}
  table{width:100%;border-collapse:collapse;border:1px solid #000}
  th{border-bottom:2px solid #000;border-right:1px solid #000;font-size:11px;text-transform:uppercase;letter-spacing:.05em;text-align:left;padding:9px 10px}
  th:last-child{border-right:0}
  td{padding:9px 10px;border-bottom:1px solid #999;border-right:1px solid #ccc}
  td:last-child{border-right:0}
  .r{text-align:right}.c{text-align:center}
  .total{display:flex;justify-content:flex-end;margin-top:12px}
  .total div{min-width:260px;border:2px solid #000;border-radius:6px;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;gap:18px;font-size:15px}
  .total b{font-size:20px}
  .sign{display:grid;grid-template-columns:1fr 1fr;gap:80px;margin-top:80px;text-align:center;font-size:12px;color:#000}
  .sign div{border-top:1px solid #000;padding-top:6px;font-weight:600}
  .foot{position:absolute;left:14mm;right:14mm;bottom:10mm;display:flex;justify-content:space-between;font-size:10.5px;color:#333;border-top:1px solid #999;padding-top:6px}
  @media print{
    body{background:#fff}
    .bar{display:none}
    .sheet{width:auto;min-height:0;margin:0;padding:0;box-shadow:none}
    .foot{position:fixed;left:0;right:0;bottom:0}
    tr{page-break-inside:avoid}
  }
</style>
</head>
<body>
<div class="bar"><a href="expense.php?id=<?= $id ?>">← Back</a><button type="button" onclick="window.print()">Download / Print (A4 PDF)</button></div>
<div class="sheet">
  <div class="head">
    <div class="brand"><img src="assets/img/logo.png" alt="Liberty Foundation - since 2008"></div>
    <div class="doc"><h1>Expense Statement</h1><div>Ref: EXP-<?= str_pad((string)$id, 4, '0', STR_PAD_LEFT) ?></div><div>Date: <?= e(fmt_date($ex['expense_date'], 'd M Y')) ?></div></div>
  </div>
  <div class="meta">
    <div><span>Submitted by</span><b><?= e($ex['by_name']) ?></b> <?= e($ex['emp_code']) ?></div>
    <div><span>Department / Designation</span><b><?= e($ex['department'] ?: '—') ?></b><?= $ex['designation'] ? ' · ' . e($ex['designation']) : '' ?></div>
    <div><span>Added on</span><b><?= e(fmt_date($ex['created_at'], 'd M Y, h:i A')) ?></b></div>
    <div><span>Last updated</span><b><?= $ex['updated_at'] ? e(fmt_date($ex['updated_at'], 'd M Y, h:i A')) . '</b> by ' . e($ex['editor_name'] ?? '—') : '—</b>' ?></div>
  </div>
  <div class="title"><?= e($ex['title']) ?></div>
  <table>
    <thead><tr><th class="c" style="width:48px">Sl</th><th>Item</th><th class="r" style="width:80px">Qty</th><th class="r" style="width:110px">Price (₹)</th><th class="r" style="width:120px">Amount (₹)</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr><td class="c"><?= (int)$it['sl'] ?></td><td><?= e($it['item_name']) ?></td><td class="r"><?= $qty($it['quantity']) ?></td><td class="r"><?= number_format((float)$it['price'], 2) ?></td><td class="r"><?= number_format((float)$it['amount'], 2) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="total"><div><span>Total expense (<?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?>)</span><b>₹ <?= number_format((float)$ex['total'], 2) ?></b></div></div>
  <div class="sign"><div>Submitted by</div><div>Approved by</div></div>
  <div class="foot"><span>Liberty Foundation · Expense Statement EXP-<?= str_pad((string)$id, 4, '0', STR_PAD_LEFT) ?></span><span>Generated <?= e(date('d M Y, h:i A')) ?></span></div>
</div>
<?php if (isset($_GET['auto'])): ?><script>window.addEventListener('load',()=>setTimeout(()=>window.print(),300));</script><?php endif; ?>
</body>
</html>
