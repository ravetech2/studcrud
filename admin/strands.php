<?php
/**
 * Strands (STEM, ABM ...): add, edit and delete.
 */
require_once __DIR__ . '/../config/db.php';
$admin = require_admin();
$pdo = db();
$errors = [];
$form = null;

// ---- Form submissions: delete or save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['strand_id'] ?? 0);

    // A strand still used by students or subjects cannot be deleted.
    if ($action === 'delete') {
        $st = $pdo->prepare('SELECT (SELECT COUNT(*) FROM students WHERE strand_id = ?) + (SELECT COUNT(*) FROM subjects WHERE strand_id = ?)');
        $st->execute([$id, $id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('error', 'This strand still has students or subjects, so it cannot be deleted.');
        } else {
            $pdo->prepare('DELETE FROM strands WHERE strand_id = ?')->execute([$id]);
            flash('success', 'Strand deleted.');
        }
        redirect('admin/strands.php');
    }

    // Add (id = 0) or edit (id > 0). Codes are stored in capitals and must be unique.
    if ($action === 'save') {
        $code = strtoupper(trim((string)($_POST['strand_code'] ?? '')));
        $name = trim((string)($_POST['strand_name'] ?? ''));
        if ($code === '') { $errors[] = 'Enter a strand code.'; }
        if ($name === '') { $errors[] = 'Enter a strand name.'; }
        if (!$errors) {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE strands SET strand_code = ?, strand_name = ? WHERE strand_id = ?')->execute([$code, $name, $id]);
                    flash('success', 'Strand updated.');
                } else {
                    $pdo->prepare('INSERT INTO strands (strand_code, strand_name) VALUES (?, ?)')->execute([$code, $name]);
                    flash('success', 'Strand added.');
                }
                redirect('admin/strands.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { $errors[] = 'A strand with that code already exists.'; } else { throw $e; }
            }
        }
        $form = ['strand_id' => $id, 'strand_code' => $code, 'strand_name' => $name];
    }
}

// Open the form for ?edit=ID or ?new=1 when there is no failed submit to redisplay.
if ($form === null) {
    if (isset($_GET['edit'])) {
        $st = $pdo->prepare('SELECT * FROM strands WHERE strand_id = ?');
        $st->execute([(int)$_GET['edit']]);
        $form = $st->fetch() ?: null;
    } elseif (isset($_GET['new'])) {
        $form = ['strand_id' => 0, 'strand_code' => '', 'strand_name' => ''];
    }
}

// Each strand with how many students and subjects use it (subqueries).
$rows = $pdo->query(
    'SELECT g.*,
            (SELECT COUNT(*) FROM students s WHERE s.strand_id = g.strand_id) AS students,
            (SELECT COUNT(*) FROM subjects b WHERE b.strand_id = g.strand_id) AS subjects
     FROM strands g ORDER BY g.strand_code'
)->fetchAll();

// ---- Page output (HTML below) ----
page_start('Strands', 'admin', 'strands');
?>
<div class="page-head">
  <div><h1>Strands</h1><p>Academic tracks offered in senior high school.</p></div>
  <div class="head-actions"><a class="btn" href="<?= h(url('admin/strands.php?new=1')) ?>">Add strand</a></div>
</div>

<?php if ($form !== null): $isEdit = (int)$form['strand_id'] > 0; ?>
<section class="panel">
  <div class="panel-head"><h2><?= $isEdit ? 'Edit strand' : 'Add a strand' ?></h2></div>
  <?php if ($errors): ?><div class="alert alert-error" role="alert"><div><?= h(implode(' ', $errors)) ?></div></div><?php endif; ?>
  <form method="post" action="<?= h(url('admin/strands.php')) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="strand_id" value="<?= (int)$form['strand_id'] ?>">
    <div class="form-grid">
      <div class="field"><label for="strand_code">Code</label><input type="text" id="strand_code" name="strand_code" value="<?= h($form['strand_code']) ?>" required autofocus></div>
      <div class="field span-2"><label for="strand_name">Name</label><input type="text" id="strand_name" name="strand_name" value="<?= h($form['strand_name']) ?>" required></div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit"><?= $isEdit ? 'Save changes' : 'Add strand' ?></button>
      <a class="btn btn-quiet" href="<?= h(url('admin/strands.php')) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php endif; ?>

<div class="table-wrap">
<?php if (!$rows): ?>
  <div class="empty"><strong>No strands</strong>Add a strand such as STEM or ABM.</div>
<?php else: ?>
  <table>
    <thead><tr><th>Code</th><th>Name</th><th class="num">Students</th><th class="num">Subjects</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= h($r['strand_code']) ?></strong></td>
        <td><?= h($r['strand_name']) ?></td>
        <td class="num"><?= (int)$r['students'] ?></td>
        <td class="num"><?= (int)$r['subjects'] ?></td>
        <td>
          <div class="row-actions">
            <a href="<?= h(url('admin/strands.php?edit=' . $r['strand_id'])) ?>">Edit</a>
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="strand_id" value="<?= (int)$r['strand_id'] ?>">
              <button class="linkbtn danger" type="submit" data-confirm="Delete <?= h($r['strand_code']) ?>?">Delete</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php page_end('admin');
