<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_admin();

$fq = trim((string)($_GET['q'] ?? ''));
$femp = (string)($_GET['emp'] ?? '');                         // '' = all, 'company' = company-wide, or an employee id
$fcat = trim((string)($_GET['cat'] ?? ''));
$fsort = in_array($_GET['sort'] ?? '', ['new', 'old', 'title', 'employee'], true) ? $_GET['sort'] : 'new';

$where = []; $args = [];
if ($femp === 'company') $where[] = 'd.user_id IS NULL';
elseif ((int)$femp > 0) { $where[] = 'd.user_id=?'; $args[] = (int)$femp; }
if ($fcat !== '') { $where[] = 'd.category=?'; $args[] = $fcat; }
if ($fq !== '') {
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $fq) . '%';
    $where[] = '(d.title LIKE ? OR d.file_name LIKE ? OR u.name LIKE ? OR u.emp_code LIKE ?)'; array_push($args, $like, $like, $like, $like);
}
$orderBy = ['new' => 'd.uploaded_at DESC, d.id DESC', 'old' => 'd.uploaded_at ASC, d.id ASC', 'title' => 'd.title ASC', 'employee' => 'u.name IS NULL DESC, u.name ASC, d.title ASC'][$fsort];
$docs = q_all('SELECT d.*, u.name AS emp_name, u.emp_code, up.name AS by_name FROM documents d LEFT JOIN users u ON u.id=d.user_id LEFT JOIN users up ON up.id=d.uploaded_by'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $orderBy, $args);
$employees = q_all('SELECT id, name, emp_code FROM users WHERE role<>"admin" AND status="active" ORDER BY name');
$categories = array_column(q_all('SELECT DISTINCT category FROM documents ORDER BY category'), 'category');

render_header('Documents', 'documents', $user);
page_title('Documents', 'Share company-wide or employee-specific documents, and manage every document employees see.', '<button type="button" class="btn btn-brand" data-open="#uploadDlg">' . icon('upload', 16) . ' Upload document</button>');
?>
<section class="card">
    <div class="card-head"><h2><?= icon('folder', 20) ?> All documents</h2><span class="badge b-mint"><?= count($docs) ?></span></div>
    <form method="get" class="filter-bar" style="margin-bottom:14px">
      <input class="input" type="search" name="q" value="<?= e($fq) ?>" placeholder="Search title, file, employee…" aria-label="Search">
      <select class="input" name="emp" onchange="this.form.submit()" aria-label="Employee"><option value="">Everyone</option><option value="company" <?= $femp === 'company' ? 'selected' : '' ?>>Company-wide only</option>
        <?php foreach ($employees as $x): ?><option value="<?= $x['id'] ?>" <?= $femp === (string)$x['id'] ? 'selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?></select>
      <select class="input" name="cat" onchange="this.form.submit()" aria-label="Category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option <?= $fcat === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
      <select class="input" name="sort" onchange="this.form.submit()" aria-label="Sort"><?php foreach (['new' => 'Newest first', 'old' => 'Oldest first', 'title' => 'Title A–Z', 'employee' => 'Employee A–Z'] as $k => $l): ?><option value="<?= $k ?>" <?= $fsort === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
      <button class="btn btn-brand"><?= icon('search', 16) ?> Search</button>
      <?php if ($fq !== '' || $femp !== '' || $fcat !== '' || $fsort !== 'new'): ?><a class="btn" href="admin_documents.php"><?= icon('x', 14) ?> Reset</a><?php endif; ?>
    </form>
    <div class="hscroll"><table class="t" style="min-width:700px">
      <thead><tr><th>Document</th><th>Category</th><th>Shared with</th><th>Uploaded</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($docs as $d): ?>
        <tr><td><a href="download.php?t=doc&id=<?= $d['id'] ?>" target="_blank" rel="noopener"><b><?= e($d['title']) ?></b></a><br><small class="muted"><?= e($d['file_name']) ?></small></td>
          <td><span class="badge b-slate"><?= e($d['category']) ?></span></td>
          <td><?= $d['user_id'] === null ? '<span class="badge b-green">All employees</span>' : e($d['emp_name']) . '<br><small class="muted">' . e($d['emp_code']) . '</small>' ?></td>
          <td style="white-space:nowrap;font-size:12.5px"><?= e(fmt_date($d['uploaded_at'])) ?><br><small class="muted"><?= $d['by_name'] ? 'by ' . e($d['by_name']) : 'system' ?></small></td>
          <td style="white-space:nowrap;text-align:right"><a class="icon-btn" href="download.php?t=doc&id=<?= $d['id'] ?>" target="_blank" rel="noopener" aria-label="View" title="View"><?= icon('eye', 16) ?></a>
            <a class="icon-btn" href="download.php?t=doc&id=<?= $d['id'] ?>&dl=1" aria-label="Download" title="Download"><?= icon('download', 16) ?></a>
            <form method="post" action="actions.php" data-confirm="Delete this document? Employees will no longer see it." style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="admin_doc_delete"><input type="hidden" name="id" value="<?= $d['id'] ?>"><input type="hidden" name="back" value="admin_documents.php"><button class="icon-btn" aria-label="Delete" title="Delete"><?= icon('trash', 16) ?></button></form></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php if (!$docs): ?><p class="empty">No documents match.</p><?php endif; ?>
  </section>


<dialog id="uploadDlg" class="doc-dlg">
  <div class="modal-h"><h2><?= icon('upload', 18) ?> Upload a document</h2><button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
  <div class="modal-b">
    <form method="post" action="actions.php" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="do" value="admin_doc_upload"><input type="hidden" name="back" value="admin_documents.php">
      <div class="field"><label class="f" for="dTitle">Title <span class="req">*</span></label><input class="input" id="dTitle" name="title" required maxlength="200" placeholder="e.g. Leave policy 2026"></div>
      <div class="field"><label class="f" for="dCat">Category</label><select class="input" id="dCat" name="category"><?php foreach (['Policy', 'Notice', 'Form', 'Letter', 'Payroll', 'General'] as $c): ?><option><?= $c ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="f">Share with</label>
        <div class="aud-opts"><label class="chk"><input type="radio" name="audience" value="all" checked> All employees</label><label class="chk"><input type="radio" name="audience" value="some"> Specific employees</label></div>
        <div class="member-pick" id="audList" hidden style="margin-top:10px"><input class="input" type="search" data-filter-list="#audList" placeholder="Search employees…" aria-label="Search employees" style="grid-column:1/-1"><?php foreach ($employees as $x): ?><label class="member-opt"><input type="checkbox" name="recipients[]" value="<?= $x['id'] ?>"> <span><?= e($x['name']) ?> <small class="muted"><?= e($x['emp_code']) ?></small></span></label><?php endforeach; ?></div></div>
      <div class="field"><label class="f">File <span class="req">*</span></label>
        <div class="drop" data-for="adminDocFile"><?= icon('upload', 26) ?><b>Drag &amp; drop a file, or click to browse</b><span class="muted" style="font-size:12.5px">PDF, images, Word, Excel, PowerPoint, CSV, TXT · up to 10MB</span><div class="fname"></div></div>
        <input type="file" id="adminDocFile" name="file" hidden accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt"></div>
      <button class="btn btn-brand"><?= icon('send', 16) ?> Upload &amp; notify</button>
      <div class="help" style="margin-top:8px">Employees get a notification and find it under Documents (company-wide ones under Company Documents, personal ones under My Documents).</div>
    </form>
  </div>
</dialog>
<?php render_footer(); ?>
