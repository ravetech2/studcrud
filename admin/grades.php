<?php
/** Creates student-subject enrollment records and records quarterly grades. */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $student = (int) $_POST['student_id'];
        $subject = (int) $_POST['subject_id'];
        $sy = trim($_POST['school_year']);
        $sem = $_POST['semester'];
        $q = $pdo->prepare('INSERT INTO student_subjects(student_id,subject_id,school_year,semester) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE enrollment_id=LAST_INSERT_ID(enrollment_id)');
        $q->execute([$student, $subject, $sy, $sem]);
        $eid = (int) $pdo->lastInsertId();
        $q = $pdo->prepare('INSERT INTO grades(enrollment_id,grading_period,grade,remarks) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE grade=VALUES(grade),remarks=VALUES(remarks)');
        $q->execute([$eid, $_POST['grading_period'], $_POST['grade'] === '' ? null : (float) $_POST['grade'], $_POST['remarks']]);
        $_SESSION['flash'] = 'Grade saved.';
    } catch (PDOException $e) {
        $_SESSION['flash'] = 'Could not save grade. Check the student, subject, and grade.';
    }
    header('Location: grades.php');
    exit;
}
$students = $pdo->query("SELECT student_id,student_number,CONCAT(firstname,' ',lastname) name FROM students ORDER BY lastname")->fetchAll();
$subjects = $pdo->query('SELECT subject_id,subject_code,subject_name FROM subjects ORDER BY subject_name')->fetchAll();
$rows = $pdo->query("SELECT st.student_number,CONCAT(st.firstname,' ',st.lastname) student,sub.subject_code,sub.subject_name,g.grading_period,g.grade,g.remarks FROM grades g JOIN student_subjects en ON en.enrollment_id=g.enrollment_id JOIN students st ON st.student_id=en.student_id JOIN subjects sub ON sub.subject_id=en.subject_id ORDER BY g.date_recorded DESC")->fetchAll();
page_header('Grades');
notice(); ?>
<div class="page-heading">
    <div>
        <p class="eyebrow">ACADEMICS</p>
        <h1>Grade management</h1>
    </div>
</div>
<div class="panel">
    <h2>Encode grade</h2>
    <form method="post" class="form-grid"><label>Student<select name="student_id"
                required><?php foreach ($students as $v)
                    echo '<option value="' . $v['student_id'] . '">' . e($v['student_number'] . ' - ' . $v['name']) . '</option>'; ?></select></label><label>Subject<select
                name="subject_id"
                required><?php foreach ($subjects as $v)
                    echo '<option value="' . $v['subject_id'] . '">' . e($v['subject_code'] . ' - ' . $v['subject_name']) . '</option>'; ?></select></label><label>School
            Year<input name="school_year" placeholder="2026-2027" required></label><label>Semester<select
                name="semester">
                <option>1st Semester</option>
                <option>2nd Semester</option>
            </select></label><label>Grading Period<select
                name="grading_period"><?php foreach (['1st Quarter', '2nd Quarter', '3rd Quarter', '4th Quarter'] as $v)
                    echo '<option>' . $v . '</option>'; ?></select></label><label>Grade
            (0-100)<input type="number" name="grade" min="0" max="100" step=".01" required></label><label>Remarks<input
                name="remarks" placeholder="Passed / Failed"></label><button class="btn">Save grade</button></form>
</div>
<div class="panel">
    <h2>Recorded grades</h2>
    <div class="table-wrap">
        <table>
            <tr>
                <th>Student</th>
                <th>Subject</th>
                <th>Period</th>
                <th>Grade</th>
                <th>Remarks</th>
            </tr><?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['student_number'] . ' - ' . $r['student']) ?></td>
                    <td><?= e($r['subject_code'] . ' ' . $r['subject_name']) ?></td>
                    <td><?= e($r['grading_period']) ?></td>
                    <td><?= e($r['grade']) ?></td>
                    <td><?= e($r['remarks']) ?></td>
                </tr><?php endforeach; ?>
        </table>
    </div>
</div><?php page_footer(); ?>