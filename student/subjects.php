<?php
/** Lists subjects assigned to the signed-in student. */
require_once __DIR__ . '/../includes/functions.php';
require_role('student');
$q = $pdo->prepare("SELECT sub.*,en.school_year FROM student_subjects en JOIN subjects sub ON sub.subject_id=en.subject_id WHERE en.student_id=? ORDER BY en.school_year DESC,sub.subject_name");
$q->execute([$_SESSION['user_id']]);
$rows = $q->fetchAll();
page_header('My Subjects'); ?>
<div class="page-heading">
    <h1>My Subjects</h1>
    <p class="muted">Subjects assigned to your student account.</p>
</div>
<div class="panel">
    <div class="table-wrap">
        <table>
            <tr>
                <th>Code</th>
                <th>Subject</th>
                <th>Units</th>
                <th>School Year</th>
                <th>Semester</th>
            </tr><?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['subject_code']) ?></td>
                    <td><?= e($r['subject_name']) ?></td>
                    <td><?= e($r['units']) ?></td>
                    <td><?= e($r['school_year']) ?></td>
                    <td><?= e($r['semester']) ?></td>
                </tr><?php endforeach; ?>
        </table>
    </div>
    <?php if (!$rows)
        echo '<p class="muted">No subjects have been assigned yet. Please contact the administrator.</p>'; ?>
</div><?php page_footer(); ?>