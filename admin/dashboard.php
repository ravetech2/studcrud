<?php
/**
 * Admin dashboard: headline numbers, counts per strand / year level, and the newest students waiting for approval.
 */
require_once __DIR__ . '/../config/db.php';
$admin = require_admin();
$pdo = db();

// 'Mark as enrolled' button. Only Pending students are changed; then redirect so refreshing does not resubmit.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve') {
    $st = $pdo->prepare("UPDATE students SET enrollment_status = 'Enrolled' WHERE student_id = ? AND enrollment_status = 'Pending'");
    $st->execute([(int)($_POST['student_id'] ?? 0)]);
    flash('success', 'Student marked as Enrolled.');
    redirect('admin/dashboard.php');
}

// Students per enrollment status. Every status starts at 0 so missing ones still display.
$status = array_fill_keys(STATUSES, 0);
foreach ($pdo->query('SELECT enrollment_status AS status, COUNT(*) AS c FROM students GROUP BY enrollment_status') as $r) {
    $status[$r['status']] = (int)$r['c'];
}
$total = array_sum($status);
$subjectCount = (int)$pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn();

// Counts per strand / year level. LEFT JOIN keeps groups that have zero students.
$byStrand = $pdo->query(
    'SELECT st.strand_code AS label, COUNT(s.student_id) AS c
     FROM strands st LEFT JOIN students s ON s.strand_id = st.strand_id
     GROUP BY st.strand_id ORDER BY st.strand_code'
)->fetchAll();
$byLevel = $pdo->query(
    'SELECT y.year_level_name AS label, COUNT(s.student_id) AS c
     FROM year_levels y LEFT JOIN students s ON s.year_level_id = y.year_level_id
     GROUP BY y.year_level_id ORDER BY y.year_level_id'
)->fetchAll();
// The 8 most recent students still waiting for approval.
$pending = $pdo->query(
    "SELECT s.*, y.year_level_name, g.strand_code
     FROM students s
     JOIN year_levels y ON y.year_level_id = s.year_level_id
     JOIN strands g ON g.strand_id = s.strand_id
     WHERE s.enrollment_status = 'Pending'
     ORDER BY s.created_at DESC, s.student_id DESC LIMIT 8"
)->fetchAll();

// Prints one horizontal bar per row, scaled against the largest count.
function bars(array $rows): void
{
    $max = 1;
    foreach ($rows as $r) { $max = max($max, (int)$r['c']); }
    if (!$rows) { echo '<p class="muted">Nothing to show yet.</p>'; return; }
    foreach ($rows as $r) {
        $w = round(((int)$r['c'] / $max) * 100);
        echo '<div class="bar-row"><span>' . h($r['label']) . '</span><div class="bar"><i style="width:' . $w . '%"></i></div><b>' . (int)$r['c'] . '</b></div>';
    }
}

// ---- Page output (HTML below) ----
page_start('Dashboard', 'admin', 'dashboard');
?>
<div class="page-head">
  <div><h1>Hello, <?= h(explode(' ', trim($admin['fullname']))[0]) ?></h1><p>A summary of students and subjects.</p></div>
  <div class="head-actions">
    <a class="btn" href="<?= h(url('admin/students.php?new=1')) ?>">Add student</a>
    <a class="btn btn-quiet" href="<?= h(url('admin/grades.php')) ?>">Enter grades</a>
  </div>
</div>

<div class="stat-strip">
  <div class="stat"><b><?= $total ?></b><span>Students</span></div>
  <div class="stat"><b><?= $status['Enrolled'] ?></b><span>Enrolled</span></div>
  <div class="stat"><b><?= $status['Pending'] ?></b><span>Pending approval</span></div>
  <div class="stat"><b><?= $subjectCount ?></b><span>Subjects offered</span></div>
</div>

<div class="cols-2">
  <section class="panel">
    <div class="panel-head"><h2>Students by strand</h2></div>
    <?php bars($byStrand); ?>
  </section>
  <section class="panel">
    <div class="panel-head"><h2>Students by year level</h2></div>
    <?php bars($byLevel); ?>
  </section>
</div>

<section>
  <div class="panel-head"><h2>Waiting for enrollment</h2><a href="<?= h(url('admin/students.php?status=Pending')) ?>">View all pending</a></div>
  <div class="table-wrap">
    <?php if (!$pending): ?>
      <div class="empty"><strong>No pending registrations</strong>New student registrations will appear here for you to approve.</div>
    <?php else: ?>
    <table>
      <thead><tr><th>Student</th><th>Year and strand</th><th>Registered</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($pending as $s): ?>
        <tr>
          <td><a href="<?= h(url('admin/students.php?edit=' . $s['student_id'])) ?>"><?= h(full_name($s)) ?></a><span class="cell-sub"><?= h($s['student_number']) ?></span></td>
          <td><?= h($s['year_level_name']) ?>, <?= h($s['strand_code']) ?></td>
          <td><?= h(substr((string)$s['created_at'], 0, 10)) ?></td>
          <td class="right">
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="student_id" value="<?= (int)$s['student_id'] ?>">
              <button class="btn btn-sm" type="submit">Mark as enrolled</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</section>
<?php page_end('admin');
