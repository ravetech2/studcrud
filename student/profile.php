<?php
/**
 * Student profile: read-only school record, editable contact details, and password change.
 */
require_once __DIR__ . '/../config/db.php';
$me = require_student();
$pdo = db();
$sid = (int)$me['student_id'];
$errors = [];
$pwErrors = [];

// Two forms post here; the hidden 'action' field says which one.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Students can change only email, contact number and address. Name, year level and strand stay with the registrar.
    if ($action === 'contact') {
        $email = trim((string)($_POST['email'] ?? ''));
        $contact = trim((string)($_POST['contact_number'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid email address.'; }
        if (!$errors) {
            try {
                $pdo->prepare('UPDATE students SET email = ?, contact_number = ?, address = ? WHERE student_id = ?')
                    ->execute([$email, nullable($contact), nullable($address), $sid]);
                flash('success', 'Contact details saved.');
                redirect('student/profile.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { $errors[] = 'Another student already uses that email.'; } else { throw $e; }
            }
        }
        $me['email'] = $email; $me['contact_number'] = $contact; $me['address'] = $address;
    }

    // Change password: verify the current one, then store the new one hashed.
    if ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        // Accounts created by an admin without a password can set one without a current password.
        if (!empty($me['password']) && !password_verify($current, $me['password'])) { $pwErrors[] = 'Your current password is incorrect.'; }
        if (strlen($new) < 8) { $pwErrors[] = 'Use a new password with at least 8 characters.'; }
        if ($new !== $confirm) { $pwErrors[] = 'The two new passwords do not match.'; }
        if (!$pwErrors) {
            $pdo->prepare('UPDATE students SET password = ? WHERE student_id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $sid]);
            flash('success', 'Password changed.');
            redirect('student/profile.php');
        }
    }
}

// ---- Page output (HTML below) ----
page_start('My profile', 'student', 'profile');
?>
<div class="page-head"><div><h1>My profile</h1><p>Your name, year level and strand are managed by the registrar.</p></div></div>

<section class="panel">
  <div class="panel-head"><h2>School record</h2></div>
  <dl class="info-list">
    <div><dt>Name</dt><dd><?= h(full_name($me)) ?></dd></div>
    <div><dt>Student number</dt><dd class="mono"><?= h($me['student_number']) ?></dd></div>
    <div><dt>Year level</dt><dd><?= h($me['year_level_name']) ?></dd></div>
    <div><dt>Strand</dt><dd><?= h($me['strand_code']) ?> – <?= h($me['strand_name']) ?></dd></div>
    <div><dt>Status</dt><dd><?= status_badge($me['enrollment_status']) ?></dd></div>
    <div><dt>Gender</dt><dd><?= h($me['gender'] ?: '—') ?></dd></div>
    <div><dt>Birthdate</dt><dd><?= h($me['birthdate'] ?: '—') ?></dd></div>
  </dl>
</section>

<section class="panel">
  <div class="panel-head"><h2>Contact details</h2></div>
  <?php if ($errors): ?><div class="alert alert-error" role="alert"><span><?= h(implode(' ', $errors)) ?></span></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="contact">
    <div class="form-grid">
      <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= h($me['email']) ?>" required></div>
      <div class="field"><label for="contact_number">Contact number</label><input type="text" id="contact_number" name="contact_number" value="<?= h($me['contact_number']) ?>"></div>
      <div class="field span-all"><label for="address">Address</label><textarea id="address" name="address"><?= h($me['address']) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="btn" type="submit">Save contact details</button></div>
  </form>
</section>

<section class="panel">
  <div class="panel-head"><h2>Change password</h2></div>
  <?php if ($pwErrors): ?>
    <div class="alert alert-error" role="alert"><div><ul><?php foreach ($pwErrors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div></div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <div class="form-grid">
      <?php if (!empty($me['password'])): ?>
      <div class="field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
      <?php endif; ?>
      <div class="field"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password"><div class="hint">At least 8 characters.</div></div>
      <div class="field"><label for="confirm_password">Confirm new password</label><input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
    </div>
    <div class="form-actions"><button class="btn" type="submit">Change password</button></div>
  </form>
</section>
<?php page_end('student');
