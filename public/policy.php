<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();

// Source: HR_Policy_Manual.docx (HR Leave & Attendance Policy 2026)
$sections = [
    '1. Office Hours' => 'Official working hours are 10:30 AM to 5:30 PM. Employees are expected to report on time and complete assigned duties during office hours.',
    '2. Weekly Offs' => 'Employees are entitled to 4 weekly holidays per month on a rotational basis. Only one weekly off may be taken in a week. Weekly offs should be coordinated with reporting managers based on operational requirements.',
    '3. Attendance & Salary Eligibility' => 'For a 30-day month, employees must work at least 26 days to receive full monthly salary. For a 31-day month, employees must work at least 27 days to receive full monthly salary. Unapproved absences may result in salary deductions according to company policy.',
    '4. Annual Leave' => 'Employees are entitled to 12 paid leave days per calendar year, subject to manager approval and business requirements.',
    '5. Sick Leave' => 'Employees are entitled to 8 sick leave days per calendar year. The company may request a medical certificate for extended or repeated sick leave.',
    '6. Leave Application' => 'Leave other than the scheduled weekly off must be requested at least 3 days in advance. Leave is subject to approval. Emergency situations will be considered individually.',
    '7. Attendance Expectations' => 'Employees must maintain regular attendance, inform managers promptly of unexpected absences, and follow all attendance procedures.',
    '8. Conduct' => 'Employees are expected to maintain professionalism, respect colleagues, protect company property, and comply with company rules and applicable laws.',
    '9. Policy Amendments' => 'The company reserves the right to amend this policy at any time. Employees will be informed of significant updates.',
];

render_header('HR Policy', 'documents', $user);
page_title('HR Leave & Attendance Policy 2026', 'Official policy reference. Use your browser’s print dialog to save as PDF.',
    '<button class="btn btn-dark no-print" onclick="window.print()">' . icon('print', 16) . ' Print / Save PDF</button>');
?>
<section class="card" style="max-width:820px;line-height:1.65">
  <h2 style="font-size:22px;margin-bottom:4px">HR Policy Manual</h2>
  <p class="muted" style="margin-bottom:18px">Policy year 2026</p>
  <?php foreach ($sections as $title => $text): ?>
    <h2 style="margin-top:18px"><?= e($title) ?></h2>
    <p class="muted"><?= e($text) ?></p>
  <?php endforeach; ?>

  <h2 style="margin-top:28px;padding-top:18px;border-top:1px solid var(--line)">Employee Acknowledgement</h2>
  <p class="muted">I acknowledge that I have read and understood this HR Policy and agree to comply with its provisions.</p>
  <div class="grid g-3" style="margin-top:22px">
    <div><div class="eyebrow">Employee name</div><div style="border-bottom:1px solid var(--ink-2);padding:6px 0 4px;min-height:30px"><?= e($user['name']) ?></div></div>
    <div><div class="eyebrow">Signature</div><div style="border-bottom:1px solid var(--ink-2);min-height:36px"></div></div>
    <div><div class="eyebrow">Date</div><div style="border-bottom:1px solid var(--ink-2);min-height:36px"></div></div>
  </div>
</section>
<?php render_footer(); ?>
