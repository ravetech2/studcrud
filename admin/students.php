<?php
/** Allows administrators to create, update, view, and delete student records. */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');
if (isset($_GET['delete'])) {
    $q = $pdo->prepare('DELETE FROM students WHERE student_id=?');
    try {
        $q->execute([(int) $_GET['delete']]);
        $_SESSION['flash'] = 'Student deleted.';
    } catch (PDOException $e) {
        $_SESSION['flash'] = 'Cannot delete a student with enrollment or grade records.';
    }
    header('Location: students.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['student_id'] ?? 0);
    try {
        if ($id) {
            $q = $pdo->prepare('UPDATE students SET student_number=?,firstname=?,middlename=?,lastname=?,gender=?,birthdate=?,email=?,contact_number=?,address=?,year_level_id=?,strand_id=?,enrollment_status=? WHERE student_id=?');
            $q->execute([$_POST['student_number'], $_POST['firstname'], $_POST['middlename'] ?: null, $_POST['lastname'], $_POST['gender'] ?: null, $_POST['birthdate'] ?: null, $_POST['email'] ?: null, $_POST['contact_number'] ?: null, $_POST['address'] ?: null, (int) $_POST['year_level_id'], (int) $_POST['strand_id'], $_POST['enrollment_status'], $id]);
        } else {
            $q = $pdo->prepare('INSERT INTO students(student_number,firstname,middlename,lastname,gender,birthdate,email,contact_number,address,year_level_id,strand_id,enrollment_status,password) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $q->execute([$_POST['student_number'], $_POST['firstname'], $_POST['middlename'] ?: null, $_POST['lastname'], $_POST['gender'] ?: null, $_POST['birthdate'] ?: null, $_POST['email'] ?: null, $_POST['contact_number'] ?: null, $_POST['address'] ?: null, (int) $_POST['year_level_id'], (int) $_POST['strand_id'], $_POST['enrollment_status'], password_hash(bin2hex(random_bytes(5)), PASSWORD_DEFAULT)]);
        }
        $_SESSION['flash'] = 'Student saved.';
    } catch (PDOException $e) {
        $_SESSION['flash'] = 'Unable to save. Check unique student number/email and required selections.';
    }
    header('Location: students.php');
    exit;
}
$edit = null;
if (isset($_GET['edit'])) {
    $q = $pdo->prepare('SELECT * FROM students WHERE student_id=?');
    $q->execute([(int) $_GET['edit']]);
    $edit = $q->fetch();
}
$years = $pdo->query('SELECT * FROM year_levels')->fetchAll();
$strands = $pdo->query('SELECT * FROM strands')->fetchAll();
$rows = $pdo->query('SELECT s.*,y.year_level_name,t.strand_code FROM students s JOIN year_levels y ON y.year_level_id=s.year_level_id JOIN strands t ON t.strand_id=s.strand_id ORDER BY s.student_id DESC')->fetchAll();
page_header('Students');
notice(); ?>
<div class="page-heading">
    <div>
        <p class="eyebrow">RECORDS</p>
        <h1>Students</h1>
    </div>
</div>
<div class="panel">
    <h2><?= $edit ? 'Edit student' : 'Add student' ?></h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="student_id" value="<?= e($edit['student_id'] ?? '') ?>"><label>Student Number<input
                name="student_number" required value="<?= e($edit['student_number'] ?? '') ?>"></label><label>First
            Name<input name="firstname" required value="<?= e($edit['firstname'] ?? '') ?>"></label><label>Middle Name<input
                name="middlename" value="<?= e($edit['middlename'] ?? '') ?>"></label><label>Last Name<input name="lastname"
                required value="<?= e($edit['lastname'] ?? '') ?>"></label>
        <label>Gender<select name="gender">
                <option value="">Select</option>
                <?php foreach (['Male', 'Female', 'Other'] as $v)
                    echo '<option ' . (($edit['gender'] ?? '') === $v ? 'selected' : '') . '>' . $v . '</option>'; ?>
            </select></label><label>Birthdate<input type="date" name="birthdate"
                value="<?= e($edit['birthdate'] ?? '') ?>"></label><label>Email<input type="email" name="email"
                value="<?= e($edit['email'] ?? '') ?>"></label><label>Contact Number<input name="contact_number"
                value="<?= e($edit['contact_number'] ?? '') ?>"></label><label>Year Level<select name="year_level_id"
                required><?php foreach ($years as $v)
                    echo '<option value="' . $v['year_level_id'] . '" ' . (($edit['year_level_id'] ?? '') == $v['year_level_id'] ? 'selected' : '') . '>' . e($v['year_level_name']) . '</option>'; ?></select></label><label>Strand<select
                name="strand_id"
                required><?php foreach ($strands as $v)
                    echo '<option value="' . $v['strand_id'] . '" ' . (($edit['strand_id'] ?? '') == $v['strand_id'] ? 'selected' : '') . '>' . e($v['strand_code']) . '</option>'; ?></select></label><label>Status<select
                name="enrollment_status"><?php foreach (['Pending', 'Enrolled', 'Graduated', 'Dropped'] as $v)
                    echo '<option ' . (($edit['enrollment_status'] ?? 'Pending') === $v ? 'selected' : '') . '>' . $v . '</option>'; ?></select></label><label
            class="span-2">Address<input name="address" value="<?= e($edit['address'] ?? '') ?>"></label><button
            class="btn"><?= $edit ? 'Update' : 'Save student' ?></button><?php if ($edit)
                echo '<a class="btn btn-outline" href="students.php">Cancel</a>'; ?>
    </form>
</div>
<div class="panel">
    <h2>Student list</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Student No.</th>
                    <th>Name</th>
                    <th>Year</th>
                    <th>Strand</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody><?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['student_number']) ?></td>
                        <td><?= e($r['firstname'] . ' ' . $r['lastname']) ?></td>
                        <td><?= e($r['year_level_name']) ?></td>
                        <td><?= e($r['strand_code']) ?></td>
                        <td><span class="pill"><?= e($r['enrollment_status']) ?></span></td>
                        <td><a href="?edit=<?= $r['student_id'] ?>">Edit</a> · <a class="danger"
                                data-confirm="Delete this student?" href="?delete=<?= $r['student_id'] ?>">Delete</a></td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div><?php page_footer(); ?>