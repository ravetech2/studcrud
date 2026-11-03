<?php
/**
 * Student report card for a chosen term. Read-only; grades below 75 are circled in red and the page can be printed.
 */
require_once __DIR__ . '/../config/db.php';
$me = require_student();
$sid = (int)$me['student_id'];

// Terms this student has enrollments in; the newest is selected by default.
$terms = student_terms($sid);
$sy = (string)($_GET['sy'] ?? ($terms ? $terms[0]['school_year'] : ''));
$sem = (string)($_GET['sem'] ?? ($terms ? $terms[0]['semester'] : ''));

// Build the report only for a valid semester; fetch_report() (config/db.php) does the grade math.
$report = [];
if ($sy !== '' && in_array($sem, SEMESTERS, true)) {
    $report = fetch_report($sid, $sy, $sem);
}
$avg = general_average($report);

// ---- Page output (HTML below) ----
page_start('My grades', 'student', 'grades');
?>
<div class="page-head">
  <div><h1>My grades</h1><p>Quarterly grades and your final grade for each subject.</p></div>
  <?php if ($report): ?><div class="head-actions"><button type="button" class="btn btn-quiet" data-print>Print report card</button></div><?php endif; ?>
</div>

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

<?php if (!$report): ?>
  <div class="table-wrap"><div class="empty"><strong>No grades to show</strong>Once you are enrolled in subjects and your teachers record grades, your report card will appear here.</div></div>
<?php else: ?>
  <div class="report">
    <div class="report-head"><h2><?= h(full_name($me)) ?></h2><span><?= h($me['student_number']) ?> &middot; <?= h($me['year_level_name']) ?>, <?= h($me['strand_code']) ?> &middot; <?= h($sy) ?>, <?= h($sem) ?></span></div>
    <div class="table-wrap" style="border:0;border-radius:0">
      <table>
        <thead><tr><th>Subject</th><?php foreach (QUARTERS as $i => $qn): ?><th class="num" title="<?= h($qn) ?>">Q<?= $i + 1 ?></th><?php endforeach; ?><th class="num">Final</th><th>Remarks</th></tr></thead>
        <tbody>
        <?php foreach ($report as $r): ?>
          <tr>
            <td><strong><?= h($r['subject_name']) ?></strong><span class="cell-sub mono"><?= h($r['subject_code']) ?></span></td>
            <?php foreach (QUARTERS as $qn): $g = $r['q'][$qn]; ?>
              <td class="num"><?php if ($g === null): ?><span class="muted">—</span><?php else: ?><span class="g <?= grade_class($g) ?>"><?= h(fmt_grade($g)) ?></span><?php endif; ?></td>
            <?php endforeach; ?>
            <td class="num"><?php if ($r['final'] === null): ?><span class="muted">—</span><?php else: ?><span class="g final <?= grade_class($r['final']) ?>"><?= h(fmt_grade(round($r['final'], 2))) ?></span><?php endif; ?></td>
            <td><span class="<?= $r['final'] === null ? '' : ($r['final'] >= PASSING_GRADE ? 'rem-pass' : 'rem-fail') ?>"><?= h(remark_text($r['final'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="5" class="right">General average</td>
            <td class="num"><?= $avg === null ? '—' : h(fmt_grade(round($avg, 2))) ?></td>
            <td><span class="<?= $avg === null ? '' : ($avg >= PASSING_GRADE ? 'rem-pass' : 'rem-fail') ?>"><?= h(remark_text($avg)) ?></span></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <p class="muted small" style="margin-top:14px">The final grade is the average of the quarters recorded so far. Grades below <?= PASSING_GRADE ?> are circled in red.</p>
<?php endif; ?>
<?php page_end('student');
