<?php
/**
 * Student self-registration. New accounts start as 'Pending' until an administrator marks them as Enrolled.
 */
require_once __DIR__ . '/../config/db.php';

if (admin_user()) {
    redirect('admin/dashboard.php');
}
if (student_user()) {
    redirect('student/dashboard.php');
}

$pdo = db();
$levels = lookup_year_levels();
$strands = lookup_strands();
$errors = [];
// Form values, kept so the form can be refilled when validation fails.
$v = [
    'firstname' => '', 'middlename' => '', 'lastname' => '', 'gender' => '', 'birthdate' => '',
    'email' => '', 'contact_number' => '', 'address' => '', 'year_level_id' => '', 'strand_id' => '',
];

// Collect the submitted values, then validate every field before touching the database.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if ($v['firstname'] === '') { $errors[] = 'Enter your first name.'; }
    if ($v['lastname'] === '') { $errors[] = 'Enter your last name.'; }
    if ($v['gender'] !== '' && !in_array($v['gender'], GENDERS, true)) { $errors[] = 'Choose a valid gender option.'; }
    if ($v['birthdate'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $v['birthdate']);
        if (!$d || $d->format('Y-m-d') !== $v['birthdate'] || $d > new DateTime('today')) {
            $errors[] = 'Enter a valid birthdate.';
        }
    }
    if ($v['email'] === '' || !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid email address.'; }
    if (!isset($levels[(int)$v['year_level_id']])) { $errors[] = 'Choose your year level.'; }
    if (!isset($strands[(int)$v['strand_id']])) { $errors[] = 'Choose your strand.'; }
    if (strlen($password) < 8) { $errors[] = 'Use a password with at least 8 characters.'; }
    if ($password !== $confirm) { $errors[] = 'The two passwords do not match.'; }

    if (!$errors) {
        // Email must be unique (compared case-insensitively).
        $st = $pdo->prepare('SELECT 1 FROM students WHERE LOWER(email) = LOWER(?)');
        $st->execute([$v['email']]);
        if ($st->fetch()) {
            $errors[] = 'An account with this email already exists. Try signing in instead.';
        }
    }

    if (!$errors) {
        // Create the student. The student number is generated on the server, and the password is stored hashed.
        try {
            $number = next_student_number($pdo);
            $pdo->prepare(
                'INSERT INTO students (student_number, firstname, middlename, lastname, gender, birthdate, email, contact_number, address, year_level_id, strand_id, enrollment_status, password)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $number, $v['firstname'], nullable($v['middlename']), $v['lastname'], nullable($v['gender']),
                nullable($v['birthdate']), $v['email'], nullable($v['contact_number']), nullable($v['address']),
                (int)$v['year_level_id'], (int)$v['strand_id'], 'Pending', password_hash($password, PASSWORD_DEFAULT),
            ]);
            flash('success', 'Account created. Your student number is ' . $number . '. Sign in with it or your email. The registrar will confirm your enrollment.');
            redirect('auth/login.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'That email or student number is already registered. Please try again.';
            } else {
                throw $e;
            }
        }
    }
}

// ---- Page output (HTML below) ----
page_start('Create account', 'public');
?>
<div class="auth">
  <aside class="auth-aside">
    <a class="brand" href="<?= h(url('index.php')) ?>">
      <img src="<?= h(asset('images/logo.svg')) ?>" alt="" width="34" height="34">
      <span>Senior High<small>School portal</small></span>
    </a>
    <div>
      <h2>Create your student account.</h2>
      <p>You will get a student number right away. Your status stays Pending until the registrar confirms your enrollment.</p>
    </div>
  </aside>

  <main class="auth-main">
    <div class="auth-box wide">
      <h1>Register</h1>
      <p>Already have an account? <a href="<?= h(url('auth/login.php')) ?>">Sign in</a>.</p>

      <?php if ($errors): ?>
        <div class="alert alert-error" role="alert"><div>Fix the following and try again:<ul>
          <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
        </ul></div></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="field"><label for="firstname">First name</label><input type="text" id="firstname" name="firstname" value="<?= h($v['firstname']) ?>" required autocomplete="given-name"></div>
          <div class="field"><label for="middlename">Middle name <span class="muted">(optional)</span></label><input type="text" id="middlename" name="middlename" value="<?= h($v['middlename']) ?>" autocomplete="additional-name"></div>
          <div class="field"><label for="lastname">Last name</label><input type="text" id="lastname" name="lastname" value="<?= h($v['lastname']) ?>" required autocomplete="family-name"></div>
          <div class="field"><label for="gender">Gender</label><select id="gender" name="gender"><?= options_html(same_map(GENDERS), $v['gender'], 'Prefer not to say') ?></select></div>
          <div class="field"><label for="birthdate">Birthdate</label><input type="date" id="birthdate" name="birthdate" value="<?= h($v['birthdate']) ?>" max="<?= h(date('Y-m-d')) ?>" autocomplete="bday"></div>
          <div class="field"><label for="contact_number">Contact number</label><input type="text" id="contact_number" name="contact_number" value="<?= h($v['contact_number']) ?>" autocomplete="tel"></div>
          <div class="field span-all"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= h($v['email']) ?>" required autocomplete="email"></div>
          <div class="field span-all"><label for="address">Address</label><textarea id="address" name="address" autocomplete="street-address"><?= h($v['address']) ?></textarea></div>
          <div class="field"><label for="year_level_id">Year level</label><select id="year_level_id" name="year_level_id" required><?= options_html($levels, $v['year_level_id'], 'Choose…') ?></select></div>
          <div class="field"><label for="strand_id">Strand</label><select id="strand_id" name="strand_id" required><?= options_html($strands, $v['strand_id'], 'Choose…') ?></select></div>
          <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"><div class="hint">At least 8 characters.</div></div>
          <div class="field"><label for="password_confirm">Confirm password</label><input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password"></div>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Create account</button></div>
      </form>
    </div>
  </main>
</div>
<?php page_end('public');
