<?php
/**
 * Grade entry. Pick a student, then a school year and semester; enroll them in subjects and record Q1-Q4 grades. Final grade = average of recorded quarters; 75 and above passes.
 */
require_once __DIR__ . '/../config/db.php';
$admin = require_admin();
$pdo = db();

// Which student, school year and semester we are working on (from the URL or the submitted form), validated below.
$studentId = (int)($_GET['student'] ?? $_POST['student_id'] ?? 0);
$sy = trim((string)($_GET['sy'] ?? $_POST['sy'] ?? ''));
if (!preg_match('/^\d{4}-\d{4}$/', $sy)) { $sy = current_school_year(); }
$sem = (string)($_GET['sem'] ?? $_POST['sem'] ?? SEMESTERS[0]);
if (!in_array($sem, SEMESTERS, true)) { $sem = SEMESTERS[0]; }

$student = $studentId > 0 ? load_student($studentId) : null;
// Where to return after every action, so the page keeps the same student and term.
$back = 'admin/grades.php?' . http_build_query(['student' => $studentId, 'sy' => $sy, 'sem' => $sem]);

// ---- Actions (only when a valid student is selected) ----
if ($student && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Enroll in one subject. The UNIQUE constraint stops duplicates for the same term.
    if ($action === 'enroll') {
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        try {
            $pdo->prepare('INSERT INTO student_subjects (student_id, subject_id, school_year, semester) VALUES (?,?,?,?)')->execute([$studentId, $subjectId, $sy, $sem]);
            flash('success', 'Subject added to this term.');
        } catch (PDOException $e) {
            flash('error', 'The student is already enrolled in that subject for this term.');
        }
        redirect($back);
    }

    // Enroll in every subject matching the student's year level, strand (or all-strand) and semester. INSERT OR IGNORE skips ones already enrolled.
    if ($action === 'enroll_all') {
        $st = $pdo->prepare(
            'INSERT OR IGNORE INTO student_subjects (student_id, subject_id, school_year, semester)
             SELECT ?, subject_id, ?, ? FROM subjects
             WHERE year_level_id = ? AND semester = ? AND (strand_id IS NULL OR strand_id = ?)'
        );
        $st->execute([$studentId, $sy, $sem, $student['year_level_id'], $sem, $student['strand_id']]);
        $n = $st->rowCount();
        flash($n > 0 ? 'success' : 'info', $n > 0 ? "Added $n subject" . ($n === 1 ? '' : 's') . ' for this term.' : 'No new subjects were found for this student\'s year level, strand and semester.');
        redirect($back);
    }

    // The grades table submits everything at once; it can also remove a single subject.
    if ($action === 'save_grades') {
        // Remove a subject, but only if the enrollment really belongs to this student. Its grades go first.
        if (isset($_POST['unenroll'])) {
            $eid = (int)$_POST['unenroll'];
            $chk = $pdo->prepare('SELECT 1 FROM student_subjects WHERE enrollment_id = ? AND student_id = ?');
            $chk->execute([$eid, $studentId]);
            if ($chk->fetch()) {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM grades WHERE enrollment_id = ?')->execute([$eid]);
                $pdo->prepare('DELETE FROM student_subjects WHERE enrollment_id = ?')->execute([$eid]);
                $pdo->commit();
                flash('success', 'Subject removed, along with its grades.');
            }
            redirect($back);
        }

        // Only enrollments of this student and term are updated, whatever the form contains.
        $ids = $pdo->prepare('SELECT enrollment_id FROM student_subjects WHERE student_id = ? AND school_year = ? AND semester = ?');
        $ids->execute([$studentId, $sy, $sem]);
        $input = $_POST['grades'] ?? [];
        $bad = 0;
        $pdo->beginTransaction();
        foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $eid) {
            // Empty box = delete that quarter's grade. A number from 0 to 100 = insert or update (upsert). Anything else is counted and skipped.
            foreach (QUARTERS as $i => $period) {
                $raw = trim((string)($input[$eid][$i + 1] ?? ''));
                if ($raw === '') {
                    $pdo->prepare('DELETE FROM grades WHERE enrollment_id = ? AND grading_period = ?')->execute([$eid, $period]);
                } elseif (is_numeric($raw) && (float)$raw >= 0 && (float)$raw <= 100) {
                    $grade = round((float)$raw, 2);
                    $pdo->prepare(
                        'INSERT INTO grades (enrollment_id, grading_period, grade, remarks, date_recorded)
                         VALUES (?,?,?,?,CURRENT_TIMESTAMP)
                         ON CONFLICT(enrollment_id, grading_period)
                         DO UPDATE SET grade = excluded.grade, remarks = excluded.remarks, date_recorded = CURRENT_TIMESTAMP'
                    )->execute([$eid, $period, $grade, remark_text($grade)]);
                } else {
                    $bad++;
                }
            }
        }
        $pdo->commit();
        if ($bad > 0) {
            flash('warning', "Grades saved, but $bad value" . ($bad === 1 ? ' was' : 's were') . ' skipped because grades must be between 0 and 100.');
        } else {
            flash('success', 'Grades saved.');
        }
        redirect($back);
    }
}

/* Student picker (no student chosen) */
// ---- Data for the page ----
$q = trim((string)($_GET['q'] ?? ''));
$picker = [];
if (!$student) {
    $params = [];
    $where = '';
    if ($q !== '') {
        $where = "WHERE s.student_number LIKE :q1 OR s.firstname LIKE :q2 OR s.lastname LIKE :q3 OR (s.firstname || ' ' || s.lastname) LIKE :q4";
        foreach (['q1', 'q2', 'q3', 'q4'] as $k) { $params[':' . $k] = '%' . $q . '%'; }
    }
    $st = $pdo->prepare(
        "SELECT s.*, y.year_level_name, g.strand_code FROM students s
         JOIN year_levels y ON y.year_level_id = s.year_level_id
         JOIN strands g ON g.strand_id = s.strand_id
         $where ORDER BY s.lastname, s.firstname LIMIT 30"
    );
    $st->execute($params);
    $picker = $st->fetchAll();
}

/* Selected student's term */
$report = [];
$available = [];
if ($student) {
    $report = fetch_report($studentId, $sy, $sem);
    $st = $pdo->prepare(
        'SELECT subject_id, subject_code, subject_name FROM subjects sub
         WHERE sub.year_level_id = ? AND sub.semester = ? AND (sub.strand_id IS NULL OR sub.strand_id = ?)
           AND sub.subject_id NOT IN (SELECT subject_id FROM student_subjects WHERE student_id = ? AND school_year = ? AND semester = ?)
         ORDER BY sub.subject_code'
    );
    $st->execute([$student['year_level_id'], $sem, $student['strand_id'], $studentId, $sy, $sem]);
    $available = $st->fetchAll();
}

// ---- Page output (HTML below) ----
page_start('Grades', 'admin', 'grades');
?>
<?php if (!$student): ?>
  <div class="page-head"><div><h1>Grades</h1><p>Choose a student to enroll in subjects and enter quarterly grades.</p></div></div>
  <form method="get" class="filters">
    <div class="field grow"><label for="q">Find a student</label><input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Name or student number" autofocus></div>
    <button class="btn btn-quiet" type="submit">Search</button>
  </form>
  <div class="table-wrap">
  <?php if (!$picker): ?>
    <div class="empty"><strong><?= $q !== '' ? 'No students found' : 'No students yet' ?></strong><?= $q !== '' ? 'Check the spelling or try the student number.' : 'Add a student first, then come back to enter grades.' ?></div>
  <?php else: ?>
    <table>
      <thead><tr><th>Student</th><th>Year and strand</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($picker as $s): ?>
        <tr>
          <td><strong><?= h(full_name($s)) ?></strong><span class="cell-sub mono"><?= h($s['student_number']) ?></span></td>
          <td><?= h($s['year_level_name']) ?>, <?= h($s['strand_code']) ?></td>
          <td><?= status_badge($s['enrollment_status']) ?></td>
          <td class="right"><a class="btn btn-sm btn-quiet" href="<?= h(url('admin/grades.php?student=' . $s['student_id'])) ?>">Open grades</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  </div>

<?php else: ?>
  <div class="page-head">
    <div class="student-bar">
      <h1><?= h(full_name($student)) ?></h1>
      <span class="muted mono"><?= h($student['student_number']) ?></span>
      <span><?= h($student['year_level_name']) ?>, <?= h($student['strand_code']) ?></span>
      <?= status_badge($student['enrollment_status']) ?>
    </div>
    <div class="head-actions"><a class="btn btn-quiet" href="<?= h(url('admin/grades.php')) ?>">Choose another student</a></div>
  </div>

  <form method="get" class="term-picker">
    <input type="hidden" name="student" value="<?= (int)$studentId ?>">
    <div class="field"><label for="sy">School year</label><input type="text" id="sy" name="sy" value="<?= h($sy) ?>" pattern="\d{4}-\d{4}" placeholder="2026-2027" required></div>
    <div class="field"><label for="sem">Semester</label><select id="sem" name="sem"><?= options_html(same_map(SEMESTERS), $sem) ?></select></div>
    <button class="btn btn-quiet" type="submit">Show term</button>
  </form>

  <section class="panel">
    <div class="panel-head"><h2>Subjects this term</h2></div>
    <?php if ($available): ?>
    <form method="post" class="filters" action="<?= h(url('admin/grades.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="enroll">
      <input type="hidden" name="student_id" value="<?= (int)$studentId ?>"><input type="hidden" name="sy" value="<?= h($sy) ?>"><input type="hidden" name="sem" value="<?= h($sem) ?>">
      <div class="field grow"><label for="subject_id">Add a subject</label>
        <select id="subject_id" name="subject_id"><?php
          $opts = [];
          foreach ($available as $a) { $opts[$a['subject_id']] = $a['subject_code'] . ' – ' . $a['subject_name']; }
          echo options_html($opts); ?></select></div>
      <button class="btn btn-quiet" type="submit">Add subject</button>
    </form>
    <form method="post" action="<?= h(url('admin/grades.php')) ?>" style="margin-bottom:18px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="enroll_all">
      <input type="hidden" name="student_id" value="<?= (int)$studentId ?>"><input type="hidden" name="sy" value="<?= h($sy) ?>"><input type="hidden" name="sem" value="<?= h($sem) ?>">
      <button class="btn btn-sm btn-pencil" type="submit">Add all <?= count($available) ?> matching <?= count($available) === 1 ? 'subject' : 'subjects' ?></button>
      <span class="muted small">Subjects for <?= h($student['year_level_name']) ?>, <?= h($student['strand_code']) ?> in the <?= h($sem) ?>.</span>
    </form>
    <?php elseif (!$report): ?>
      <p class="muted">No subjects in the catalog match this student's year level, strand and semester. <a href="<?= h(url('admin/subjects.php?new=1')) ?>">Add a subject</a> first.</p>
    <?php endif; ?>

    <?php if (!$report): ?>
      <div class="empty"><strong>Not enrolled in any subject for <?= h($sy) ?>, <?= h($sem) ?></strong>Add subjects above, then enter grades here.</div>
    <?php else: ?>
    <form method="post" action="<?= h(url('admin/grades.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_grades">
      <input type="hidden" name="student_id" value="<?= (int)$studentId ?>"><input type="hidden" name="sy" value="<?= h($sy) ?>"><input type="hidden" name="sem" value="<?= h($sem) ?>">
      <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true">Save grades</button>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Subject</th><?php foreach (QUARTERS as $i => $qn): ?><th class="num">Q<?= $i + 1 ?></th><?php endforeach; ?><th class="num">Final</th><th>Remarks</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($report as $r): ?>
            <tr class="grade-row">
              <td><strong><?= h($r['subject_name']) ?></strong><span class="cell-sub mono"><?= h($r['subject_code']) ?></span></td>
              <?php foreach (QUARTERS as $i => $qn): $val = $r['q'][$qn]; ?>
                <td class="num"><input class="grade-input" type="number" min="0" max="100" step="0.01" inputmode="decimal"
                  name="grades[<?= (int)$r['enrollment_id'] ?>][<?= $i + 1 ?>]" value="<?= $val === null ? '' : h(fmt_grade($val)) ?>"
                  aria-label="<?= h($r['subject_code'] . ' ' . $qn) ?>"></td>
              <?php endforeach; ?>
              <td class="num"><span class="final"><?= h(fmt_grade($r['final'])) ?></span></td>
              <td><span class="remark <?= $r['final'] === null ? '' : ($r['final'] >= PASSING_GRADE ? 'rem-pass' : 'rem-fail') ?>"><?= h(remark_text($r['final'])) ?></span></td>
              <td class="right"><button class="linkbtn danger" type="submit" name="unenroll" value="<?= (int)$r['enrollment_id'] ?>" formnovalidate data-confirm="Remove <?= h($r['subject_code']) ?> and its grades for this term?">Remove</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit">Save grades</button>
        <span class="muted small" style="align-self:center">Leave a box empty for a quarter without a grade. Passing is <?= PASSING_GRADE ?> and above.</span>
      </div>
    </form>
    <?php endif; ?>
  </section>
<?php endif; ?>
<?php page_end('admin');
