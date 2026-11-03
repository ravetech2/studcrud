<?php
/**
 * Student home: status, current term subjects with final grades, and the general average.
 */
require_once __DIR__ . '/../config/db.php';
$me = require_student();
$sid = (int)$me['student_id'];

// Show the student's most recent school year / semester.
$terms = student_terms($sid);
$term = $terms ? $terms[0] : null;
$report = $term ? fetch_report($sid, $term['school_year'], $term['semester']) : [];
$avg = general_average($report);
// Subjects that already have at least one grade.
$graded = array_values(array_filter($report, function ($r) { return $r['final'] !== null; }));

// ---- Page output (HTML below) ----
page_start('Dashboard', 'student', 'dashboard');
?>
<div class="page-head">
  <div><h1>Hello, <?= h($me['firstname']) ?></h1><p><?= h($me['year_level_name']) ?>, <?= h($me['strand_code']) ?> &middot; <?= h($me['strand_name']) ?></p></div>
  <div class="head-actions"><a class="btn btn-quiet" href="<?= h(url('student/grades.php')) ?>">Open my grades</a></div>
</div>

<?php if ($me['enrollment_status'] === 'Pending'): ?>
  <div class="alert alert-info" role="status"><span>Your registration is pending. The registrar will confirm your enrollment and add your subjects.</span></div>
<?php elseif ($me['enrollment_status'] === 'Dropped'): ?>
  <div class="alert alert-warning" role="status"><span>Your status is Dropped. Contact the registrar if this is a mistake.</span></div>
<?php endif; ?>

<div class="stat-strip">
  <div class="stat"><b class="mono"><?= h($me['student_number']) ?></b><span>Student number</span></div>
  <div class="stat"><b style="font-size:1.4rem;padding-top:.45rem"><?= status_badge($me['enrollment_status']) ?></b><span>Enrollment status</span></div>
  <div class="stat"><b><?= count($report) ?></b><span>Subjects this term</span></div>
  <div class="stat"><b class="<?= $avg === null ? '' : 'rem-' . ($avg >= PASSING_GRADE ? 'pass' : 'fail') ?>"><?= $avg === null ? '—' : h(fmt_grade(round($avg, 2))) ?></b><span>General average</span></div>
</div>

<?php if (!$term): ?>
  <div class="table-wrap"><div class="empty"><strong>No subjects yet</strong>Your subjects and grades will show up here once the registrar enrolls you in them.</div></div>
<?php else: ?>
  <div class="panel-head"><h2><?= h($term['school_year']) ?>, <?= h($term['semester']) ?></h2><a href="<?= h(url('student/grades.php?sy=' . urlencode($term['school_year']) . '&sem=' . urlencode($term['semester']))) ?>">Full report card</a></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Subject</th><th class="num">Final grade</th><th>Remarks</th></tr></thead>
      <tbody>
      <?php foreach ($report as $r): ?>
        <tr>
          <td><strong><?= h($r['subject_name']) ?></strong><span class="cell-sub mono"><?= h($r['subject_code']) ?></span></td>
          <td class="num"><?php if ($r['final'] === null): ?><span class="muted">Not graded yet</span><?php else: ?><span class="g <?= grade_class($r['final']) ?>"><?= h(fmt_grade(round($r['final'], 2))) ?></span><?php endif; ?></td>
          <td><span class="<?= $r['final'] === null ? '' : ($r['final'] >= PASSING_GRADE ? 'rem-pass' : 'rem-fail') ?>"><?= h(remark_text($r['final'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php page_end('student');
