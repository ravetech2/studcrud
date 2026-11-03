<?php
/**
 * Administrator accounts: list, add, edit and delete.
 */
require_once __DIR__ . '/../config/db.php';
$me = require_admin();
$pdo = db();
$errors = [];
$form = null;

// ---- Form submissions: delete or save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['admin_id'] ?? 0);

    // Safety rules: never delete the account you are using, and never delete the last administrator.
    if ($action === 'delete') {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM admin')->fetchColumn();
        if ($id === (int)$me['admin_id']) {
            flash('error', 'You cannot delete the account you are signed in with.');
        } elseif ($count <= 1) {
            flash('error', 'At least one administrator must remain.');
        } else {
            $pdo->prepare('DELETE FROM admin WHERE admin_id = ?')->execute([$id]);
            flash('success', 'Administrator deleted.');
        }
        redirect('admin/manage-admin.php');
    }

    // id = 0 creates an admin (password required); id > 0 edits one (a blank password keeps the old one).
    if ($action === 'save') {
        $fullname = trim((string)($_POST['fullname'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($fullname === '') { $errors[] = 'Enter the full name.'; }
        if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username)) { $errors[] = 'Username must be 3–30 characters: letters, numbers, dot, dash or underscore.'; }
        if ($id === 0 && strlen($password) < 8) { $errors[] = 'Use a password with at least 8 characters.'; }
        if ($id > 0 && $password !== '' && strlen($password) < 8) { $errors[] = 'A new password needs at least 8 characters.'; }

        if (!$errors) {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE admin SET fullname = ?, username = ? WHERE admin_id = ?')->execute([$fullname, $username, $id]);
                    if ($password !== '') {
                        $pdo->prepare('UPDATE admin SET password = ? WHERE admin_id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                    }
                    if ($id === (int)$me['admin_id']) {
                        $_SESSION['admin']['fullname'] = $fullname;
                        $_SESSION['admin']['username'] = $username;
                    }
                    flash('success', 'Administrator updated.');
                } else {
                    $pdo->prepare('INSERT INTO admin (username, password, fullname) VALUES (?,?,?)')->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullname]);
                    flash('success', 'Administrator added.');
                }
                redirect('admin/manage-admin.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { $errors[] = 'That username is already taken.'; } else { throw $e; }
            }
        }
        $form = ['admin_id' => $id, 'fullname' => $fullname, 'username' => $username];
    }
}

// Open the form for ?edit=ID or ?new=1 when there is no failed submit to redisplay.
if ($form === null) {
    if (isset($_GET['edit'])) {
        $st = $pdo->prepare('SELECT admin_id, fullname, username FROM admin WHERE admin_id = ?');
        $st->execute([(int)$_GET['edit']]);
        $form = $st->fetch() ?: null;
    } elseif (isset($_GET['new'])) {
        $form = ['admin_id' => 0, 'fullname' => '', 'username' => ''];
    }
}

$rows = $pdo->query('SELECT admin_id, username, fullname, created_at FROM admin ORDER BY fullname')->fetchAll();

// ---- Page output (HTML below) ----
page_start('Administrators', 'admin', 'manage-admin');
?>
<div class="page-head">
  <div><h1>Administrators</h1><p>People who can sign in to this console.</p></div>
  <div class="head-actions"><a class="btn" href="<?= h(url('admin/manage-admin.php?new=1')) ?>">Add administrator</a></div>
</div>

<?php if ($form !== null): $isEdit = (int)$form['admin_id'] > 0; ?>
<section class="panel">
  <div class="panel-head"><h2><?= $isEdit ? 'Edit administrator' : 'Add an administrator' ?></h2></div>
  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><div>Fix the following and try again:<ul>
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul></div></div>
  <?php endif; ?>
  <form method="post" action="<?= h(url('admin/manage-admin.php')) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="admin_id" value="<?= (int)$form['admin_id'] ?>">
    <div class="form-grid">
      <div class="field"><label for="fullname">Full name</label><input type="text" id="fullname" name="fullname" value="<?= h($form['fullname']) ?>" required autofocus></div>
      <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" value="<?= h($form['username']) ?>" required autocomplete="off"></div>
      <div class="field">
        <label for="password"><?= $isEdit ? 'New password' : 'Password' ?></label>
        <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
        <div class="hint"><?= $isEdit ? 'Leave blank to keep the current password.' : 'At least 8 characters.' ?></div>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit"><?= $isEdit ? 'Save changes' : 'Add administrator' ?></button>
      <a class="btn btn-quiet" href="<?= h(url('admin/manage-admin.php')) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead><tr><th>Name</th><th>Username</th><th>Added</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $isMe = (int)$r['admin_id'] === (int)$me['admin_id']; ?>
      <tr>
        <td><strong><?= h($r['fullname']) ?></strong><?= $isMe ? ' <span class="badge badge-enrolled">You</span>' : '' ?></td>
        <td class="mono"><?= h($r['username']) ?></td>
        <td><?= h(substr((string)$r['created_at'], 0, 10)) ?></td>
        <td>
          <div class="row-actions">
            <a href="<?= h(url('admin/manage-admin.php?edit=' . $r['admin_id'])) ?>">Edit</a>
            <?php if (!$isMe): ?>
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="admin_id" value="<?= (int)$r['admin_id'] ?>">
              <button class="linkbtn danger" type="submit" data-confirm="Delete administrator <?= h($r['username']) ?>?">Delete</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php page_end('admin');
