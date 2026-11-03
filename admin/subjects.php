<?php
/**
 * Subject catalog: list with filters, plus the add / edit form and delete. A subject with no strand is open to every strand.
 */
require_once __DIR__ . '/../config/db.php';
$admin = require_admin();
$pdo = db();
$levels = lookup_year_levels();
$strands = lookup_strands();
$errors = [];
$form = null;

// Default values for the 'Add subject' form.
$blank = ['subject_id' => 0, 'subject_code' => '', 'subject_name' => '', 'description' => '', 'units' => '3', 'year_level_id' => '', 'strand_id' => '', 'semester' => '1st Semester'];

// ---- Form submissions: delete or save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Refuse to delete a subject that students are enrolled in.
    if ($action === 'delete') {
        $id = (int)($_POST['subject_id'] ?? 0);
        $st = $pdo->prepare('SELECT COUNT(*) FROM student_subjects WHERE subject_id = ?');
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('error', 'This subject has enrolled students, so it cannot be deleted. Remove those enrollments first.');
        } else {
            $pdo->prepare('DELETE FROM subjects WHERE subject_id = ?')->execute([$id]);
            flash('success', 'Subject deleted.');
        }
        redirect('admin/subjects.php');
    }

    // Validate first; write to the database only when there are no errors.
    if ($action === 'save') {
        $id = (int)($_POST['subject_id'] ?? 0);
        $v = $blank;
        foreach (['subject_code', 'subject_name', 'description', 'units', 'year_level_id', 'strand_id', 'semester'] as $k) {
            $v[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $v['subject_id'] = $id;

        if ($v['subject_code'] === '') { $errors[] = 'Enter a subject code.'; }
        if ($v['subject_name'] === '') { $errors[] = 'Enter a subject name.'; }
        if (!is_numeric($v['units']) || (float)$v['units'] < 0 || (float)$v['units'] > 20) { $errors[] = 'Units must be a number between 0 and 20.'; }
        if (!isset($levels[(int)$v['year_level_id']])) { $errors[] = 'Choose a year level.'; }
        if ($v['strand_id'] !== '' && !isset($strands[(int)$v['strand_id']])) { $errors[] = 'Choose a valid strand.'; }
        if (!in_array($v['semester'], SEMESTERS, true)) { $errors[] = 'Choose a semester.'; }

        if (!$errors) {
            $args = [$v['subject_code'], $v['subject_name'], nullable($v['description']), (float)$v['units'], (int)$v['year_level_id'], $v['strand_id'] === '' ? null : (int)$v['strand_id'], $v['semester']];
            try {
                if ($id > 0) {
                    $pdo->prepare('UPDATE subjects SET subject_code=?, subject_name=?, description=?, units=?, year_level_id=?, strand_id=?, semester=? WHERE subject_id=?')->execute(array_merge($args, [$id]));
                    flash('success', 'Subject updated.');
                } else {
                    $pdo->prepare('INSERT INTO subjects (subject_code, subject_name, description, units, year_level_id, strand_id, semester) VALUES (?,?,?,?,?,?,?)')->execute($args);
                    flash('success', 'Subject added.');
                }
                redirect('admin/subjects.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Another subject already uses that code.';
                } else {
                    throw $e;
                }
            }
        }
        $form = $v;
    }
}

// Open the form for ?edit=ID or ?new=1 when there is no failed submit to redisplay.
if ($form === null) {
    if (isset($_GET['edit'])) {
        $st = $pdo->prepare('SELECT * FROM subjects WHERE subject_id = ?');
        $st->execute([(int)$_GET['edit']]);
        $row = $st->fetch();
        if ($row) { $form = $row; }
    } elseif (isset($_GET['new'])) {
        $form = $blank;
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$fYear = (int)($_GET['year'] ?? 0);
$fStrand = (string)($_GET['strand'] ?? '');
$fSem = (string)($_GET['sem'] ?? '');
// Filters for the list. Values are bound as parameters.
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(sub.subject_code LIKE :q1 OR sub.subject_name LIKE :q2)';
    $params[':q1'] = $params[':q2'] = '%' . $q . '%';
}
if ($fYear) { $where[] = 'sub.year_level_id = :year'; $params[':year'] = $fYear; }
if ($fStrand === 'none') { $where[] = 'sub.strand_id IS NULL'; }
elseif ((int)$fStrand > 0) { $where[] = 'sub.strand_id = :strand'; $params[':strand'] = (int)$fStrand; }
if (in_array($fSem, SEMESTERS, true)) { $where[] = 'sub.semester = :sem'; $params[':sem'] = $fSem; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = $pdo->prepare(
    "SELECT sub.*, y.year_level_name, g.strand_code,
            (SELECT COUNT(*) FROM student_subjects ss WHERE ss.subject_id = sub.subject_id) AS enrolled
     FROM subjects sub
     JOIN year_levels y ON y.year_level_id = sub.year_level_id
     LEFT JOIN strands g ON g.strand_id = sub.strand_id
     $whereSql
     ORDER BY sub.year_level_id, sub.semester, sub.subject_code"
);
$st->execute($params);
$subjects = $st->fetchAll();

// Strand codes for the filter dropdown.
$codes = [];
foreach ($pdo->query('SELECT strand_id, strand_code FROM strands ORDER BY strand_code') as $r) { $codes[$r['strand_id']] = $r['strand_code']; }

// ---- Page output (HTML below) ----
page_start('Subjects', 'admin', 'subjects');
?>
<div class="page-head">
  <div><h1>Subjects</h1><p><?= count($subjects) ?> <?= count($subjects) === 1 ? 'subject' : 'subjects' ?><?= $where ? ' match your filters' : ' offered' ?>.</p></div>
  <div class="head-actions"><a class="btn" href="<?= h(url('admin/subjects.php?new=1')) ?>">Add subject</a></div>
</div>

<?php if ($form !== null): $isEdit = (int)$form['subject_id'] > 0; ?>
<section class="panel">
  <div class="panel-head"><h2><?= $isEdit ? 'Edit subject' : 'Add a subject' ?></h2></div>
  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><div>Fix the following and try again:<ul>
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul></div></div>
  <?php endif; ?>
  <form method="post" action="<?= h(url('admin/subjects.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="subject_id" value="<?= (int)$form['subject_id'] ?>">
    <div class="form-grid">
      <div class="field"><label for="subject_code">Subject code</label><input type="text" id="subject_code" name="subject_code" value="<?= h($form['subject_code']) ?>" required></div>
      <div class="field span-2"><label for="subject_name">Subject name</label><input type="text" id="subject_name" name="subject_name" value="<?= h($form['subject_name']) ?>" required></div>
      <div class="field"><label for="units">Units</label><input type="number" id="units" name="units" value="<?= h($form['units']) ?>" step="0.5" min="0" max="20" required></div>
      <div class="field"><label for="year_level_id">Year level</label><select id="year_level_id" name="year_level_id" required><?= options_html($levels, $form['year_level_id'], 'Choose…') ?></select></div>
      <div class="field"><label for="strand_id">Strand</label><select id="strand_id" name="strand_id"><?= options_html($strands, $form['strand_id'], 'All strands') ?></select></div>
      <div class="field"><label for="semester">Semester</label><select id="semester" name="semester"><?= options_html(same_map(SEMESTERS), $form['semester']) ?></select></div>
      <div class="field span-all"><label for="description">Description</label><textarea id="description" name="description"><?= h($form['description']) ?></textarea></div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit"><?= $isEdit ? 'Save changes' : 'Add subject' ?></button>
      <a class="btn btn-quiet" href="<?= h(url('admin/subjects.php')) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php endif; ?>

<form method="get" class="filters">
  <div class="field grow"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Code or name"></div>
  <div class="field"><label for="f-year">Year level</label><select id="f-year" name="year"><?= options_html($levels, $fYear ?: '', 'All') ?></select></div>
  <div class="field"><label for="f-strand">Strand</label><select id="f-strand" name="strand"><?= options_html(['none' => 'All-strand subjects'] + $codes, $fStrand, 'Any') ?></select></div>
  <div class="field"><label for="f-sem">Semester</label><select id="f-sem" name="sem"><?= options_html(same_map(SEMESTERS), $fSem, 'Both') ?></select></div>
  <button class="btn btn-quiet" type="submit">Filter</button>
  <?php if ($where): ?><a class="btn btn-quiet" href="<?= h(url('admin/subjects.php')) ?>">Clear</a><?php endif; ?>
</form>

<div class="table-wrap">
<?php if (!$subjects): ?>
  <div class="empty"><strong><?= $where ? 'No subjects match these filters' : 'No subjects yet' ?></strong><?= $where ? 'Try a different search or clear the filters.' : 'Add the first subject to start enrolling students.' ?></div>
<?php else: ?>
  <table>
    <thead><tr><th>Subject</th><th>Year level</th><th>Strand</th><th>Semester</th><th class="num">Units</th><th class="num">Enrolled</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($subjects as $s): ?>
      <tr>
        <td><strong><?= h($s['subject_name']) ?></strong><span class="cell-sub mono"><?= h($s['subject_code']) ?></span></td>
        <td><?= h($s['year_level_name']) ?></td>
        <td><?= h($s['strand_code'] ?: 'All strands') ?></td>
        <td><?= h($s['semester']) ?></td>
        <td class="num"><?= h(fmt_grade($s['units'])) ?></td>
        <td class="num"><?= (int)$s['enrolled'] ?></td>
        <td>
          <div class="row-actions">
            <a href="<?= h(url('admin/subjects.php?edit=' . $s['subject_id'])) ?>">Edit</a>
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="subject_id" value="<?= (int)$s['subject_id'] ?>">
              <button class="linkbtn danger" type="submit" data-confirm="Delete <?= h($s['subject_code']) ?>?">Delete</button>
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
