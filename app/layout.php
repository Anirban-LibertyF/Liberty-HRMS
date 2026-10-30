<?php
declare(strict_types=1);

function icon(string $name, int $s = 18): string
{
    static $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'leave' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M10 14l4 4M14 14l-4 4"/>',
        'log' => '<path d="M9 2h6a1 1 0 0 1 1 1v1h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2V3a1 1 0 0 1 1-1z"/><path d="M8 11h8M8 15h8"/>',
        'payslip' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'folder' => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'edit' =>'<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/>',
        'admin' => '<path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/><path d="M9 12l2 2 4-4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
        'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2.5-3 4M12 17.5h.01"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
        'finger' => '<path d="M12 11c0 3 0 6-1 9M8 8a4 4 0 0 1 8 0c0 2 0 5-.5 8M5 12a7 7 0 0 1 .5-3M19 12c0 3-.3 5-1 7M8.5 14.5c0 2-.3 3.5-1 5"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'check' => '<circle cx="12" cy="12" r="10"/><path d="M8 12l3 3 5-6"/>',
        'x' => '<path d="M18 6L6 18M6 6l12 12"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'coffee' => '<path d="M17 8h1a4 4 0 0 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4zM6 1v3M10 1v3M14 1v3"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l3 2"/>',
        'in' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>',
        'out' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M8 7l-5 5 5 5M15 12H3"/>',
        'break' => '<path d="M17 8h1a4 4 0 0 1 0 8h-1M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4z"/>',
        'clip' => '<path d="M21 12l-9 9a6 6 0 0 1-9-9l9-9a4 4 0 0 1 6 6l-9 9a2 2 0 0 1-3-3l8-8"/>',
        'shield' => '<path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'send' => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
        'bookmark' => '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
        'print' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 6h2M13 6h2M9 10h2M13 10h2M9 14h2M13 14h2M10 22v-4h4v4"/>',
        'megaphone' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1zM15 8a5 5 0 0 1 0 8"/>',
        'palm' => '<path d="M12 22V10M12 10C9 4 4 5 3 8c3-1 6 0 9 2zM12 10c3-6 8-5 9-2-3-1-6 0-9 2z"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2M2 13h20"/>',
        'rupee' => '<path d="M6 3h12M6 8h12M6 13l8.5 8M6 13h3M9 13c6.667 0 6.667-10 0-10"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    ];
    return '<svg class="ic" width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? $p['check']) . '</svg>';
}

function render_header(string $title, string $active, array $user, array $opts = []): void
{
    $isAdm = $user['role'] === 'admin';
    $state = $isAdm ? 'none' : today_status($user)['state'];
    $unread = (int)q_val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$user['id']]);
    $nav = $isAdm ? [
        'overview' => ['admin.php', 'Dashboard', 'dashboard'],
        'employees' => ['admin.php?tab=employees', 'Employees', 'user'],
        'attendance' => ['admin.php?tab=attendance', 'Attendance', 'calendar'],
        'leaves' => ['admin.php?tab=leaves', 'Leave Requests', 'leave'],
        'logs' => ['admin.php?tab=logs', 'Daily Work Logs', 'log'],
        'holidays' => ['admin.php?tab=holidays', 'Holidays', 'palm'],
        'announce' => ['admin.php?tab=announce', 'Announcements', 'megaphone'],
        'payroll' => ['admin.php?tab=payroll', 'Payroll', 'payslip'],
        'projects' => ['projects.php', 'Projects', 'briefcase'],
        'expenses' => ['expenses.php', 'Expenses', 'rupee'],
        'documents' => ['admin_documents.php', 'Documents', 'folder'],
        'notes' => ['notes.php', 'Notes', 'edit'],
    ] : [
        'dashboard' => ['index.php', 'Dashboard', 'dashboard'],
        'attendance' => ['attendance.php', 'Attendance Calendar', 'calendar'],
        'leave' => ['leave.php', 'Leave Application', 'leave'],
        'worklog' => ['worklog.php', 'Daily Work Log', 'log'],
        'projects' => ['projects.php', 'Projects', 'briefcase'],
        'expenses' => ['expenses.php', 'Expenses', 'rupee'],
        'payslips' => ['payslips.php', 'Payslips', 'payslip'],
        'documents' => ['documents.php', 'Documents', 'folder'],
        'notes' => ['notes.php', 'Personal Notebook', 'edit'],
        'notices' => ['notices.php', 'Notices', 'megaphone'],
        'profile' => ['profile.php', 'Profile', 'user'],
    ];
    if (!$isAdm && is_staff($user)) $nav['admin'] = ['admin.php', 'Admin Portal', 'admin'];
    $punchLabel = $state === 'in' || $state === 'break' ? 'Punch Out' : 'Punch In';
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<script>try{if(localStorage.getItem('lo-mode')==='dark')document.documentElement.setAttribute('data-mode','dark')}catch(e){}</script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Liberty Office</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
</head>
<body class="theme-<?= $isAdm ? 'admin' : 'emp' ?>">
<div class="shell">
  <div class="nav-overlay" id="navOverlay"></div>
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= $isAdm ? 'admin.php' : 'index.php' ?>">
      <img class="brand-icon" src="assets/img/logo-icon.png" alt="" width="44" height="44">
      <span><b>Liberty Office</b><small><?= $isAdm ? 'Administrator Console' : 'Enterprise Workforce Suite' ?></small></span>
    </a>
    <nav>
      <?php foreach ($nav as $key => [$href, $label, $ic]): ?>
        <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <?php if (!$isAdm && is_staff($user)): ?>
        <label class="toggle-row"><span><?= icon('shield', 16) ?> Admin View</span>
          <input type="checkbox" class="switch" id="adminSwitch" <?= $active === 'admin' ? 'checked' : '' ?> aria-label="Toggle admin view"></label>
      <?php endif; ?>
      <a href="support.php"><?= icon('help', 16) ?> Support</a>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn" id="menuBtn" aria-label="Menu"><svg width="20" height="20" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
      <a class="top-logo" href="<?= $isAdm ? 'admin.php' : 'index.php' ?>" aria-label="Liberty Office home"><img src="assets/img/logo.png" alt="Liberty Foundation - since 2008" width="120" height="38"></a>
      <?php if ($isAdm): ?><div class="search" style="font-weight:600;color:var(--brand-ink)">Administrator Console</div><?php else: ?>
      <form class="search<?= !empty($_GET['q']) ? ' open' : '' ?>" id="topSearch" action="search.php" method="get" role="search">
        <button type="button" class="icon-btn search-toggle" id="searchToggle" aria-label="Search" aria-expanded="false"><?= icon('search', 20) ?></button>
        <input type="search" name="q" id="searchInput" placeholder="Search logs, policies, shifts, coworkers..." value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search" minlength="2" autocomplete="off">
      </form>
      <?php endif; ?>
      <div class="top-right">
        <?php if (!$isAdm): ?>
        <span class="pill pill-status <?= $state === 'in' ? 'is-on' : ($state === 'break' ? 'is-break' : '') ?>" id="topStatus">
          <i></i><?= $state === 'in' ? 'On Shift' : ($state === 'break' ? 'On Break' : ($state === 'out' ? 'Shift Completed' : 'Not Checked In')) ?>
        </span>
        <?php endif; ?>
        <button type="button" class="icon-btn mode-btn" id="modeBtn" aria-label="Switch dark / light mode" title="Dark / light mode"><span class="i-moon"><?= icon('moon', 20) ?></span><span class="i-sun"><?= icon('sun', 20) ?></span></button>
        <div class="dropdown" id="notifWrap">
          <button class="icon-btn" id="notifBtn" aria-label="Notifications"><?= icon('bell', 20) ?><?php if ($unread): ?><span class="dot-badge"><?= $unread ?></span><?php endif; ?></button>
          <div class="dropdown-panel" id="notifPanel" hidden>
            <div class="dd-head"><b>Notifications</b><span><button class="link-btn" id="notifReadAll">Mark all read</button> · <button class="link-btn" id="notifClear">Clear all</button></span></div>
            <?php $ns = q_all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 8', [$user['id']]); ?>
            <?php if (!$ns): ?><p class="muted pad">You're all caught up.</p><?php endif; ?>
            <?php foreach ($ns as $n): ?>
              <?php
              $leaveStatusClass = '';
              if (preg_match('/\brequest\b.*\bwas\s+(approved|rejected)\b/i', $n['message'], $match)) {
                  $leaveStatusClass = $match[1] === 'approved' ? 'leave-approved' : 'leave-rejected';
              }
              $announcementClass = (str_starts_with($n['message'], 'New announcement:') || str_starts_with($n['message'], 'Announcement updated:')) ? 'announcement-new' : '';
              ?>
              <?php if (preg_match('/^projects\.php\?invite=(\d+)/', (string)$n['link'], $im)):
                  $inv = (int)$im[1]; $invSt = q_val('SELECT status FROM project_members WHERE project_id=? AND user_id=?', [$inv, $user['id']]); ?>
              <div class="dd-item <?= $n['is_read'] ? '' : 'unread' ?>"><?= e($n['message']) ?><small><?= e(date('M d, h:i A', strtotime($n['created_at']))) ?></small>
                <div class="dd-actions" data-invite="<?= $inv ?>">
                  <?php if ($invSt === 'pending'): ?><button type="button" class="btn btn-sm btn-brand" data-choice="accept">Accept</button><button type="button" class="btn btn-sm btn-danger" data-choice="reject">Reject</button>
                  <?php elseif ($invSt): ?><span class="badge <?= $invSt === 'accepted' ? 'b-green' : 'b-red' ?>"><?= e(ucfirst($invSt)) ?></span><?php if ($invSt === 'accepted'): ?> <a href="project.php?id=<?= $inv ?>">Open project</a><?php endif; ?>
                  <?php else: ?><span class="muted">No longer available</span><?php endif; ?></div></div>
              <?php else: ?>
              <a class="dd-item <?= $n['is_read'] ? '' : 'unread' ?> <?= $leaveStatusClass ?> <?= $announcementClass ?>" href="<?= e($n['link'] ?: '#') ?>"><?= e($n['message']) ?><small><?= e(date('M d, h:i A', strtotime($n['created_at']))) ?></small></a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <span class="vsep"></span>
        <div class="dropdown" id="accountWrap">
          <button type="button" class="user-chip" id="accountBtn" aria-haspopup="true" aria-expanded="false">
            <?= avatar_html($user, 38) ?>
            <span><b><?= e($user['name']) ?></b><small><?= e($user['emp_code']) ?></small></span>
          </button>
          <div class="dropdown-panel account-panel" id="accountPanel" hidden>
            <div class="acc-info"><b><?= e($user['name']) ?></b><small><?= e($user['emp_code']) ?></small></div>
            <a class="dd-item" href="logout.php"><?= icon('logout', 16) ?> Log out</a>
          </div>
        </div>
      </div>
    </header>
    <main class="content">
      <?php foreach (flash_pop() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= icon($f['type'] === 'error' ? 'x' : 'check', 20) ?><span><?= e($f['msg']) ?></span><button class="alert-x" aria-label="Dismiss"><?= icon('x', 16) ?></button></div>
      <?php endforeach; ?>
<?php
}

function render_footer(): void
{
    ?>
    </main>
    <footer class="site-foot no-print">
      <span class="sf-logo"><img src="assets/img/logo.png" alt="Liberty Foundation" height="34"></span>
      <div class="sf-text">
        <p>&copy; <?= date('Y') ?> <b>Liberty Foundation</b>. All rights reserved.</p>
        <p>Developed and Maintained by <b>Liberty Innovation</b></p>
      </div>
    </footer>
  </div>
</div>
<div class="toast-wrap" id="toasts" aria-live="polite"></div>
<script src="assets/js/app.js"></script>
</body>
</html>
<?php
}

/** Shared "page title + description" block. */
function page_title(string $title, string $sub = '', string $actions = ''): void
{
    echo '<div class="page-head"><div><h1>' . e($title) . '</h1>' . ($sub ? '<p class="muted">' . $sub . '</p>' : '') . '</div>' . ($actions ? '<div class="head-actions">' . $actions . '</div>' : '') . '</div>';
}
