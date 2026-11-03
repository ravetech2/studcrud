<?php
/**
 * Student subjects: what they are enrolled in for a chosen term, and everything offered for their year level and strand.
 */
require_once __DIR__ . '/../config/db.php';
$me = require_student();
$pdo = db();
$sid = (int)$me['student_id'];

// Terms (school year + semester) this student has enrollments in; the newest is selected by default.
$terms = student_terms($sid);
$sy = (string)($_GET['sy'] ?? ($terms ? $terms[0]['school_year'] : ''));
$sem = (string)($_GET['sem'] ?? ($terms ? $terms[0]['semester'] : ''));

$enrolled = [];
$units = 0.0;
if ($sy !== '' && in_array($sem, SEMESTERS, true)) {
    $st = $pdo->prepare(
        'SELECT sub.subject_code, sub.subject_name, sub.description, sub.units
         FROM student_subjects ss JOIN subjects sub ON sub.subject_id = ss.subject_id
         WHERE ss.student_id = ? AND ss.school_year = ? AND ss.semester = ?
         ORDER BY sub.subject_code'
    );
    $st->execute([$sid, $sy, $sem]);
    $enrolled = $st->fetchAll();
    foreach ($enrolled as $e) { $units += (float)$e['units']; }
}

$st = $pdo->prepare(
    'SELECT subject_code, subject_name, units, semester FROM subjects
     WHERE year_level_id = ? AND (strand_id IS NULL OR strand_id = ?)
     ORDER BY semester, subject_code'
);
// Subjects offered to this student's year level and strand (subjects with no strand are open to all).
$st->execute([$me['year_level_id'], $me['strand_id']]);
$curriculum = $st->fetchAll();

// ---- Page output (HTML below) ----
page_start('My subjects', 'student', 'subjects');
?>
<div class="page-head"><div><h1>My subjects</h1><p>Subjects you are enrolled in, and everything offered for your year level and strand.</p></div></div>

<?php if ($terms): ?>
<form method="get" class="term-picker">
  <div class="field"><label for="term">Term</label>
    <select id="term" onchange="var p=this.value.split('|');location.search='?sy='+encodeURIComponent(p[0])+'&sem='+encodeURIComponent(p[1]);">
      <?php foreach ($terms as $t): $val = $t['school_year'] . '|' . $t['semester']; ?>
        <option value="<?= h($val) ?>"<?= ($t['school_year'] === $sy && $t['semester'] === $sem) ? ' selected' : '' ?>><?= h($t['school_year']) ?>, <?= h($t['semester']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</form>
<?php endif; ?>

<div class="table-wrap" style="margin-bottom:34px">
<?php if (!$enrolled): ?>
  <div class="empty"><strong>No enrolled subjects</strong>The registrar will add your subjects for the term. They will appear here.</div>
<?php else: ?>
  <table>
    <thead><tr><th>Subject</th><th>Description</th><th class="num">Units</th></tr></thead>
    <tbody>
    <?php foreach ($enrolled as $e): ?>
      <tr>
        <td><strong><?= h($e['subject_name']) ?></strong><span class="cell-sub mono"><?= h($e['subject_code']) ?></span></td>
        <td><?= h($e['description'] ?: '—') ?></td>
        <td class="num"><?= h(fmt_grade($e['units'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="2" class="right"><strong>Total units</strong></td><td class="num"><strong><?= h(fmt_grade($units)) ?></strong></td></tr></tfoot>
  </table>
<?php endif; ?>
</div>

<div class="panel-head"><h2>Offered for <?= h($me['year_level_name']) ?>, <?= h($me['strand_code']) ?></h2></div>
<div class="table-wrap">
<?php if (!$curriculum): ?>
  <div class="empty"><strong>No subjects listed yet</strong>Subjects for your year level and strand have not been added.</div>
<?php else: ?>
  <table>
    <thead><tr><th>Subject</th><th>Semester</th><th class="num">Units</th></tr></thead>
    <tbody>
    <?php foreach ($curriculum as $c): ?>
      <tr>
        <td><strong><?= h($c['subject_name']) ?></strong><span class="cell-sub mono"><?= h($c['subject_code']) ?></span></td>
        <td><?= h($c['semester']) ?></td>
        <td class="num"><?= h(fmt_grade($c['units'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php page_end('student');
