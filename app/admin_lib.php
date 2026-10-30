<?php
declare(strict_types=1);

/** Random unused employee ID: LOE- followed by 3 random digits. */
function next_emp_code(): string
{
    $used = array_flip(array_column(q_all('SELECT emp_code FROM users WHERE emp_code REGEXP "^LOE-[0-9]{3}$"'), 'emp_code'));
    $free = [];
    for ($i = 100; $i <= 999; $i++) if (!isset($used["LOE-$i"])) $free[] = $i;
    if (!$free) throw new RuntimeException('No free employee IDs left (LOE-100 to LOE-999).');
    return 'LOE-' . $free[random_int(0, count($free) - 1)];
}

/**
 * Validates the shared employee form (create + edit). Returns [clean fields, error|null].
 * The administrator role is never assignable: there is exactly one admin account.
 */
function employee_fields_from_post(array $p, ?int $editingId = null): array
{
    $f = [
        'name' => mb_substr(trim((string)($p['name'] ?? '')), 0, 120),
        'email' => strtolower(trim((string)($p['email'] ?? ''))),
        'role' => in_array($p['role'] ?? '', ['employee', 'manager'], true) ? $p['role'] : 'employee',
        'department' => mb_substr(trim((string)($p['department'] ?? '')), 0, 80),
        'team' => mb_substr(trim((string)($p['team'] ?? '')), 0, 80),
        'designation' => mb_substr(trim((string)($p['designation'] ?? '')), 0, 120),
        'manager_id' => (int)($p['manager_id'] ?? 0) ?: null,
        'shift_id' => (int)($p['shift_id'] ?? 1) ?: 1,
        'phone' => mb_substr(preg_replace('/[^\d+\-\s()]/', '', (string)($p['phone'] ?? '')), 0, 30),
        'dob' => trim((string)($p['dob'] ?? '')) ?: null,
        'gender' => in_array($p['gender'] ?? '', ['Male', 'Female', 'Other'], true) ? $p['gender'] : '',
        'blood_group' => in_array($p['blood_group'] ?? '', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], true) ? $p['blood_group'] : '',
        'address' => mb_substr(trim((string)($p['address'] ?? '')), 0, 300),
        'emergency_name' => mb_substr(trim((string)($p['emergency_name'] ?? '')), 0, 120),
        'emergency_phone' => mb_substr(preg_replace('/[^\d+\-\s()]/', '', (string)($p['emergency_phone'] ?? '')), 0, 30),
        'employment_type' => in_array($p['employment_type'] ?? '', ['Full-time', 'Part-time', 'Contract', 'Intern'], true) ? $p['employment_type'] : 'Full-time',
        'work_location' => mb_substr(trim((string)($p['work_location'] ?? 'Headquarters')), 0, 80) ?: 'Headquarters',
        'joined_on' => trim((string)($p['joined_on'] ?? '')),
        'base_salary' => max(0, (float)($p['base_salary'] ?? 0)),
    ];
    $date = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
    if ($f['name'] === '') return [$f, 'Full name is required.'];
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) return [$f, 'Enter a valid email address.'];
    if (q_val('SELECT COUNT(*) FROM users WHERE email=? AND id<>?', [$f['email'], $editingId ?? 0])) return [$f, 'That email is already used by another account.'];
    if ($f['department'] === '' || $f['designation'] === '') return [$f, 'Department and designation are required.'];
    if (!$date($f['joined_on'])) return [$f, 'Enter a valid joining date.'];
    if ($f['dob'] !== null && (!$date($f['dob']) || $f['dob'] >= today())) return [$f, 'Enter a valid date of birth.'];
    if ($editingId && $f['manager_id'] === $editingId) return [$f, 'An employee cannot be their own manager.'];
    return [$f, null];
}

/** Status of every active non-admin employee on a date: present / absent / leave / holiday / weekend / upcoming. */
function roster_for_date(string $date, string $search = ''): array
{
    $isToday = $date === today();
    $params = [$date, $date, $date, $date];
    $sql = 'SELECT u.id, u.manager_id, u.emp_code, u.name, u.department, u.designation, u.joined_on, a.first_in, a.last_out, a.worked_min, a.break_min, a.is_late,
                   (SELECT event_type FROM punch_events p WHERE p.user_id=u.id AND p.work_date=? ORDER BY event_time DESC, id DESC LIMIT 1) AS last_ev,
                   (SELECT lt.name FROM leave_requests lr JOIN leave_types lt ON lt.id=lr.leave_type_id WHERE lr.user_id=u.id AND lr.status="approved" AND ? BETWEEN lr.from_date AND lr.end_date LIMIT 1) AS leave_name,
                   (SELECT COUNT(*) FROM week_offs w WHERE w.user_id=u.id AND w.off_date=?) AS is_weekoff
            FROM users u LEFT JOIN attendance a ON a.user_id=u.id AND a.work_date=?
            WHERE u.status="active" AND u.role<>"admin"';
    if ($search !== '') {
        $sql .= ' AND (u.name LIKE ? OR u.emp_code LIKE ? OR u.department LIKE ?)';
        $like = '%' . addcslashes($search, '%_\\') . '%';
        array_push($params, $like, $like, $like);
    }
    $rows = q_all($sql . ' ORDER BY u.emp_code', $params);
    $off = is_weekend($date) ? 'weekend' : (holidays_between($date, $date) ? 'holiday' : null);
    foreach ($rows as &$r) {
        $worked = (int)$r['worked_min'];
        if ($isToday && $r['first_in'] && in_array($r['last_ev'], ['in', 'break_end'], true)) {
            // still on the clock: add the currently open interval
            $s = summarize_events(day_events((int)$r['id'], $date));
            $worked = intdiv($s['work_sec'] + ($s['work_start'] ? time() - $s['work_start'] : 0), 60);
        }
        $r['worked_live'] = $worked;
        if ($r['first_in']) $r['state'] = $r['last_ev'] === 'break_start' ? 'break' : ($r['last_ev'] === 'out' || !$isToday ? 'present_done' : 'present_live');
        elseif ($r['leave_name']) $r['state'] = 'leave';
        elseif ($off) $r['state'] = $off;
        elseif ($r['is_weekoff']) $r['state'] = 'weekoff';
        elseif ($date < $r['joined_on']) $r['state'] = 'notjoined';
        elseif ($date > today() || $isToday) $r['state'] = $isToday ? 'absent_so_far' : 'upcoming';
        else $r['state'] = 'absent';
    }
    return $rows;
}

/** Button markup that opens the shared attendance editor (see attendance_editor_dialog). */
function att_edit_button(array $emp, string $date, ?array $att, string $label = 'Edit'): string
{
    $data = [
        'uid' => (int)$emp['id'], 'name' => $emp['name'] . ' (' . $emp['emp_code'] . ')', 'date' => $date,
        'status' => ($att || str_starts_with($label, '+')) ? 'present' : 'absent',
        'in' => !empty($att['first_in']) ? date('H:i', strtotime($att['first_in'])) : '10:30',
        'out' => !empty($att['last_out']) ? date('H:i', strtotime($att['last_out'])) : ($att ? '' : '17:30'),
        'brk' => (int)($att['break_min'] ?? 45),
    ];
    return '<button type="button" class="btn btn-sm" data-att-edit="' . e(json_encode($data)) . '">' . e($label) . '</button>';
}

/** One shared dialog + script; every att_edit_button on the page fills and opens it. */
function attendance_editor_dialog(string $back): void
{ ?>
<dialog id="attDlg">
  <form method="post" action="actions.php">
    <?= csrf_field() ?><input type="hidden" name="do" value="admin_attendance_save"><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="user_id" id="attUid">
    <div class="modal-h"><h2>Edit attendance</h2><button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
    <div class="modal-b">
      <p class="muted" style="margin-bottom:14px" id="attWho"></p>
      <div class="field-row field"><div><label class="f">Date</label><input class="input" type="date" name="work_date" id="attDate" max="<?= today() ?>" required></div>
        <div><label class="f">Status</label><select class="input" name="status" id="attStatus"><option value="present">Present</option><option value="absent">Absent (remove punches)</option></select></div></div>
      <div id="attTimes">
        <div class="field-row field"><div><label class="f">Check in</label><input class="input" type="time" name="time_in" id="attIn"></div>
          <div><label class="f">Check out</label><input class="input" type="time" name="time_out" id="attOut"></div></div>
        <div class="field"><label class="f">Break (minutes)</label><input class="input" type="number" name="break_min" id="attBrk" min="0" max="240" value="45"><div class="help">Leave check out empty only for today if the employee is still working.</div></div>
      </div>
      <div class="field"><label class="f">Reason for edit <span class="req">*</span></label><input class="input" name="note" id="attNote" maxlength="200" required placeholder="e.g. Badge reader offline; verified with manager"></div>
      <button class="btn btn-brand btn-block">Save attendance</button>
    </div>
  </form>
</dialog>
<script>
(function () {
  var d = document.getElementById('attDlg'), st = document.getElementById('attStatus');
  function sync() { document.getElementById('attTimes').style.display = st.value === 'present' ? '' : 'none'; }
  st.addEventListener('change', sync);
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-att-edit]'); if (!b) return;
    var x = JSON.parse(b.getAttribute('data-att-edit'));
    document.getElementById('attUid').value = x.uid; document.getElementById('attWho').textContent = x.name;
    document.getElementById('attDate').value = x.date; st.value = x.status;
    document.getElementById('attIn').value = x.in; document.getElementById('attOut').value = x.out; document.getElementById('attBrk').value = x.brk;
    document.getElementById('attNote').value = ''; sync(); d.showModal();
  });
})();
</script>
<?php }