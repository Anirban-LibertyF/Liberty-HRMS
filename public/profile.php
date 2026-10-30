<?php
declare(strict_types=1);
require __DIR__ . '/../app/init.php';
$user = require_login();
$mgr = $user['manager_id'] ? q_one('SELECT name, designation FROM users WHERE id=?', [$user['manager_id']]) : null;

render_header('Profile', 'profile', $user);
page_title('My Profile', 'Your employment details and account security.');
?>
<div class="grid g-2" style="align-items:start">
  <section class="card">
    <div class="profile-summary">
      <div class="profile-summary-info"><h2 style="font-size:20px"><?= e($user['name']) ?></h2><p class="muted"><?= e($user['designation']) ?></p></div>
      <form class="profile-photo-form" method="post" action="actions.php" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="do" value="profile_photo_upload"><input type="hidden" name="back" value="profile.php">
        <div class="profile-photo-control">
          <button class="profile-photo-preview" type="button" data-open="profilePhotoDialog" aria-label="Enlarge profile photo"><?= avatar_html($user, 96) ?></button>
          <button class="profile-photo-edit" type="button" aria-label="Upload profile photo" title="Upload profile photo"><?= icon('edit', 16) ?></button>
        </div>
        <input class="profile-photo-input" type="file" id="profilePhoto" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" aria-label="Choose a JPG or PNG profile photo">
      </form>
    </div>
    <dialog id="profilePhotoDialog" class="profile-photo-dialog" aria-label="Profile photo preview">
      <button type="button" class="icon-btn profile-photo-close" data-close aria-label="Close preview"><?= icon('x', 18) ?></button>
      <?= avatar_html($user, 280) ?>
    </dialog>
    <div class="box">
      <div class="kv"><span>Employee ID</span><b class="mono"><?= e($user['emp_code']) ?></b></div>
      <div class="kv"><span>Corporate Email</span><span><?= e($user['email']) ?></span></div>
      <div class="kv"><span>Department / Team</span><span><?= e($user['department']) ?> · <?= e($user['team']) ?></span></div>
      <div class="kv"><span>Reporting Manager</span><span><?= $mgr ? e($mgr['name']) . ' (' . e($mgr['designation']) . ')' : '—' ?></span></div>
      <div class="kv"><span>Shift</span><span><?= e($user['shift_name']) ?> (<?= e(substr($user['start_time'], 0, 5)) ?> – <?= e(substr($user['end_time'], 0, 5)) ?>)</span></div>
      <div class="kv"><span>Date of Joining</span><span><?= e(fmt_date($user['joined_on'])) ?></span></div>
      <div class="kv"><span>Date of Birth</span><span><?= e($user['dob'] ? fmt_date($user['dob']) : '—') ?></span></div>
      <div class="kv"><span>Gender / Blood Group</span><span><?= e(($user['gender'] ?: '—') . ' / ' . ($user['blood_group'] ?: '—')) ?></span></div>
      <div class="kv"><span>Employment</span><span><?= e($user['employment_type']) ?> · <?= e($user['work_location']) ?></span></div>
      <div class="kv"><span>Last Sign-in</span><span><?= e(fmt_date($user['last_login'], 'M d, Y h:i A')) ?></span></div>
    </div>
    <form method="post" action="actions.php" style="margin-top:18px"><?= csrf_field() ?><input type="hidden" name="do" value="profile_update">
      <div class="field"><label class="f" for="ph">Phone</label><input class="input" id="ph" name="phone" value="<?= e($user['phone']) ?>" maxlength="30"></div>
      <div class="field"><label class="f" for="ad">Address</label><input class="input" id="ad" name="address" value="<?= e($user['address']) ?>" maxlength="300"></div>
      <div class="field-row field"><div><label class="f" for="en">Emergency contact</label><input class="input" id="en" name="emergency_name" value="<?= e($user['emergency_name']) ?>" maxlength="120"></div><div><label class="f" for="ep">Emergency phone</label><input class="input" id="ep" name="emergency_phone" value="<?= e($user['emergency_phone']) ?>" maxlength="30"></div></div>
      <button class="btn btn-brand">Save Profile</button></form>
  </section>

  <section class="card" id="security">
    <div class="card-head"><h2><?= icon('shield', 20) ?> Security — Change Password</h2></div>
    <form method="post" action="actions.php"><?= csrf_field() ?><input type="hidden" name="do" value="password_change">
      <div class="field"><label class="f" for="cur">Current password</label><input class="input" type="password" id="cur" name="current" required autocomplete="current-password"></div>
      <div class="field"><label class="f" for="np">New password</label><input class="input" type="password" id="np" name="new" required minlength="10" autocomplete="new-password"><div class="help">At least 10 characters with letters and numbers.</div></div>
      <div class="field"><label class="f" for="np2">Confirm new password</label><input class="input" type="password" id="np2" name="new2" required autocomplete="new-password"></div>
      <button class="btn btn-dark">Update Password</button>
    </form>
  </section>
</div>
<?php render_footer(); ?>
