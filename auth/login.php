<?php
/**
 * Sign-in page for both students and administrators. The 'role' (student/admin) comes from the tab the visitor chose.
 */
require_once __DIR__ . '/../config/db.php';

// Already signed in? Go to the right dashboard instead of showing the form.
if (admin_user()) {
    redirect('admin/dashboard.php');
}
if (student_user()) {
    redirect('student/dashboard.php');
}

// Which tab is active. Anything other than 'admin' falls back to 'student'.
$role = (($_GET['role'] ?? $_POST['role'] ?? 'student') === 'admin') ? 'admin' : 'student';
$identity = '';
$error = '';

// Form submitted: look the account up and check the password.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $pdo = db();

    if ($identity === '' || $password === '') {
        $error = $role === 'admin' ? 'Enter your username and password.' : 'Enter your student number or email, and your password.';
    } elseif ($role === 'admin') {
        // Admins sign in with their username.
        $st = $pdo->prepare('SELECT * FROM admin WHERE username = ?');
        $st->execute([$identity]);
        $row = $st->fetch();
        if ($row && password_verify($password, $row['password'])) {
            // New session id after a successful login (prevents session fixation). Only the minimum is kept in the session.
            session_regenerate_id(true);
            $_SESSION['admin'] = ['admin_id' => (int)$row['admin_id'], 'username' => $row['username'], 'fullname' => $row['fullname']];
            redirect('admin/dashboard.php');
        }
        $error = 'Incorrect username or password.';
    } else {
        // Students can sign in with their student number or their email (case-insensitive).
        $st = $pdo->prepare('SELECT * FROM students WHERE student_number = ? OR LOWER(email) = LOWER(?)');
        $st->execute([$identity, $identity]);
        $row = $st->fetch();
        if ($row && empty($row['password'])) {
            $error = 'This account has no password yet. Ask the registrar to set one for you.';
        } elseif ($row && password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['student'] = [
                'student_id' => (int)$row['student_id'],
                'number' => $row['student_number'],
                'name' => full_name($row),
            ];
            redirect('student/dashboard.php');
        } else {
            $error = 'Incorrect student number, email or password.';
        }
    }
}

// ---- Page output (HTML below) ----
page_start($role === 'admin' ? 'Admin sign in' : 'Sign in', 'public');
?>
<div class="auth">
  <aside class="auth-aside">
    <a class="brand" href="<?= h(url('index.php')) ?>">
      <img src="<?= h(asset('images/logo.svg')) ?>" alt="" width="34" height="34">
      <span>Senior High<small>School portal</small></span>
    </a>
    <div>
      <h2><?= $role === 'admin' ? 'Manage students, subjects and grades.' : 'Your subjects and grades, in one place.' ?></h2>
      <p><?= $role === 'admin' ? 'Staff accounts are created by an existing administrator.' : 'Sign in with the student number you received when you registered.' ?></p>
    </div>
  </aside>

  <main class="auth-main">
    <div class="auth-box">
      <h1>Sign in</h1>
      <p><?= $role === 'admin' ? 'Administrator access.' : 'Welcome back.' ?></p>

      <div class="tabs">
        <a href="?role=student" class="<?= $role === 'student' ? 'on' : '' ?>">Student</a>
        <a href="?role=admin" class="<?= $role === 'admin' ? 'on' : '' ?>">Administrator</a>
      </div>

      <?php render_flashes(); ?>
      <?php if ($error): ?><div class="alert alert-error" role="alert"><span><?= h($error) ?></span></div><?php endif; ?>

      <form method="post" class="stack" autocomplete="on">
        <?= csrf_field() ?>
        <input type="hidden" name="role" value="<?= h($role) ?>">
        <div class="field">
          <label for="identity"><?= $role === 'admin' ? 'Username' : 'Student number or email' ?></label>
          <input type="text" id="identity" name="identity" value="<?= h($identity) ?>" required autofocus
                 autocomplete="<?= $role === 'admin' ? 'username' : 'username' ?>">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn" type="submit">Sign in</button>
      </form>

      <?php if ($role === 'student'): ?>
        <p class="auth-foot">New student? <a href="<?= h(url('auth/register.php')) ?>">Create an account</a>.</p>
      <?php endif; ?>
    </div>
  </main>
</div>
<?php page_end('public');
