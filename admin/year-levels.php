<?php
/**
 * Year levels (Grade 11, Grade 12 ...): add, rename and delete.
 */
require_once __DIR__ . '/../config/db.php';
$admin = require_admin();
$pdo = db();
$errors = [];
$form = null;

// ---- Form submissions: delete or save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['year_level_id'] ?? 0);

    // A year level still used by students or subjects cannot be deleted.
    if ($action === 'delete') {
        $st = $pdo->prepare('SELECT (SELECT COUNT(*) FROM students WHERE year_level_id = ?) + (SELECT COUNT(*) FROM subjects WHERE year_level_id = ?)');
        $st->execute([$id, $id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('error', 'This year level still has students or subjects, so it cannot be deleted.');
        } else {
            $pdo->prepare('DELETE FROM year_levels WHERE year_level_id = ?')->execute([$id]);
            flash('success', 'Year level deleted.');
        }
        redirect('admin/year-levels.php');
    }

    // Add (id = 0) or rename (id > 0). Names must be unique.
    if ($action === 'save') {
        $name = trim((string)($_POST['year_level_name'] ?? ''));
        if ($name === '') { $errors[] = 'Enter a year level name.'; }
        if (!$errors) {
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE year_levels SET year_level_name = ? WHERE year_level_id = ?')->execute([$name, $id]);
                    flash('success', 'Year level updated.');
                } else {
                    $pdo->prepare('INSERT INTO year_levels (year_level_name) VALUES (?)')->execute([$name]);
                    flash('success', 'Year level added.');
                }
                redirect('admin/year-levels.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') { $errors[] = 'A year level with that name already exists.'; } else { throw $e; }
            }
        }
        $form = ['year_level_id' => $id, 'year_level_name' => $name];
    }
}

// Open the form for ?edit=ID or ?new=1 when there is no failed submit to redisplay.
if ($form === null) {
    if (isset($_GET['edit'])) {
        $st = $pdo->prepare('SELECT * FROM year_levels WHERE year_level_id = ?');
        $st->execute([(int)$_GET['edit']]);
        $form = $st->fetch() ?: null;
    } elseif (isset($_GET['new'])) {
        $form = ['year_level_id' => 0, 'year_level_name' => ''];
    }
}

// Each year level with how many students and subjects use it (subqueries).
$rows = $pdo->query(
    'SELECT y.*,
            (SELECT COUNT(*) FROM students s WHERE s.year_level_id = y.year_level_id) AS students,
            (SELECT COUNT(*) FROM subjects b WHERE b.year_level_id = y.year_level_id) AS subjects
     FROM year_levels y ORDER BY y.year_level_id'
)->fetchAll();

// ---- Page output (HTML below) ----
page_start('Year levels', 'admin', 'year-levels');
?>
<div class="page-head">
  <div><h1>Year levels</h1><p>Grade levels that students and subjects belong to.</p></div>
  <div class="head-actions"><a class="btn" href="<?= h(url('admin/year-levels.php?new=1')) ?>">Add year level</a></div>
</div>

<?php if ($form !== null): $isEdit = (int)$form['year_level_id'] > 0; ?>
<section class="panel">
  <div class="panel-head"><h2><?= $isEdit ? 'Rename year level' : 'Add a year level' ?></h2></div>
  <?php if ($errors): ?><div class="alert alert-error" role="alert"><div><?= h(implode(' ', $errors)) ?></div></div><?php endif; ?>
  <form method="post" action="<?= h(url('admin/year-levels.php')) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="year_level_id" value="<?= (int)$form['year_level_id'] ?>">
    <div class="form-grid"><div class="field"><label for="year_level_name">Name</label><input type="text" id="year_level_name" name="year_level_name" value="<?= h($form['year_level_name']) ?>" required autofocus></div></div>
    <div class="form-actions">
      <button class="btn" type="submit"><?= $isEdit ? 'Save changes' : 'Add year level' ?></button>
      <a class="btn btn-quiet" href="<?= h(url('admin/year-levels.php')) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php endif; ?>

<div class="table-wrap">
<?php if (!$rows): ?>
  <div class="empty"><strong>No year levels</strong>Add Grade 11 and Grade 12 to get started.</div>
<?php else: ?>
  <table>
    <thead><tr><th>Name</th><th class="num">Students</th><th class="num">Subjects</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= h($r['year_level_name']) ?></strong></td>
        <td class="num"><?= (int)$r['students'] ?></td>
        <td class="num"><?= (int)$r['subjects'] ?></td>
        <td>
          <div class="row-actions">
            <a href="<?= h(url('admin/year-levels.php?edit=' . $r['year_level_id'])) ?>">Rename</a>
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="year_level_id" value="<?= (int)$r['year_level_id'] ?>">
              <button class="linkbtn danger" type="submit" data-confirm="Delete <?= h($r['year_level_name']) ?>?">Delete</button>
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
