<?php
/**
 * One-time setup: creates the first administrator account.
 * It refuses to run once any administrator exists. Delete this file after setup.
 */
require_once __DIR__ . '/config/db.php';

$pdo = db();
// Setup is locked as soon as one administrator exists, so strangers cannot create admins later.
$locked = (int)$pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn() > 0;
$errors = [];
$v = ['fullname' => '', 'username' => ''];

// Validate the form; only insert when there are no errors. Passwords are stored hashed, never as plain text.
if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['fullname'] = trim((string)($_POST['fullname'] ?? ''));
    $v['username'] = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if ($v['fullname'] === '') { $errors[] = 'Enter the full name.'; }
    if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $v['username'])) { $errors[] = 'Username must be 3–30 characters: letters, numbers, dot, dash or underscore.'; }
    if (strlen($password) < 8) { $errors[] = 'Use a password with at least 8 characters.'; }
    if ($password !== $confirm) { $errors[] = 'The two passwords do not match.'; }

    if (!$errors) {
        $pdo->prepare('INSERT INTO admin (username, password, fullname) VALUES (?, ?, ?)')
            ->execute([$v['username'], password_hash($password, PASSWORD_DEFAULT), $v['fullname']]);
        flash('success', 'Administrator created. Sign in below, then delete create_admin.php from the server.');
        redirect('auth/login.php?role=admin');
    }
}

// ---- Page output (HTML below) ----
page_start('Create first administrator', 'public');
?>
<div class="auth">
  <aside class="auth-aside">
    <a class="brand" href="<?= h(url('index.php')) ?>">
      <img src="<?= h(asset('images/logo.svg')) ?>" alt="" width="34" height="34">
      <span>Senior High<small>School portal</small></span>
    </a>
    <div>
      <h2>Set up the first administrator.</h2>
      <p>This page works only while no administrator exists.</p>
    </div>
  </aside>
  <main class="auth-main">
    <div class="auth-box">
      <?php if ($locked): ?>
        <h1>Setup is finished</h1>
        <p>An administrator account already exists. Sign in to add more from the Administrators page, then delete <code>create_admin.php</code> from the server.</p>
        <a class="btn" href="<?= h(url('auth/login.php?role=admin')) ?>">Go to admin sign in</a>
      <?php else: ?>
        <h1>Create administrator</h1>
        <p>You will use these details to sign in to the admin console.</p>
        <?php if ($errors): ?>
          <div class="alert alert-error" role="alert"><div>Fix the following and try again:<ul>
            <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
          </ul></div></div>
        <?php endif; ?>
        <form method="post" class="stack">
          <?= csrf_field() ?>
          <div class="field"><label for="fullname">Full name</label><input type="text" id="fullname" name="fullname" value="<?= h($v['fullname']) ?>" required autofocus></div>
          <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" value="<?= h($v['username']) ?>" required autocomplete="username"></div>
          <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"><div class="hint">At least 8 characters.</div></div>
          <div class="field"><label for="password_confirm">Confirm password</label><input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password"></div>
          <button class="btn" type="submit">Create administrator</button>
        </form>
      <?php endif; ?>
    </div>
  </main>
</div>
<?php page_end('public');
