<?php
/**
 * Student management: list with search, filters and paging, plus the add / edit form and delete.
 */
require_once __DIR__ . '/../config/db.php';
$admin = require_admin();
$pdo = db();
$levels = lookup_year_levels();
$strands = lookup_strands();
$errors = [];
$form = null;

// Default values for the 'Add student' form.
$blank = [
    'student_id' => 0, 'student_number' => '', 'firstname' => '', 'middlename' => '', 'lastname' => '',
    'gender' => '', 'birthdate' => '', 'email' => '', 'contact_number' => '', 'address' => '',
    'year_level_id' => '', 'strand_id' => '', 'enrollment_status' => 'Enrolled',
];

// ---- Form submissions: delete or save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Delete a student together with their grades and enrollments in one transaction (foreign keys would otherwise block it).
    if ($action === 'delete') {
        $id = (int)($_POST['student_id'] ?? 0);
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM grades WHERE enrollment_id IN (SELECT enrollment_id FROM student_subjects WHERE student_id = ?)')->execute([$id]);
        $pdo->prepare('DELETE FROM student_subjects WHERE student_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM students WHERE student_id = ?')->execute([$id]);
        $pdo->commit();
        flash('success', 'Student deleted along with their subjects and grades.');
        redirect('admin/students.php');
    }

    // Add or edit. Validate everything first; only write to the database when there are no errors.
    if ($action === 'save') {
        $id = (int)($_POST['student_id'] ?? 0);
        $v = $blank;
        foreach (['student_number', 'firstname', 'middlename', 'lastname', 'gender', 'birthdate', 'email', 'contact_number', 'address', 'year_level_id', 'strand_id', 'enrollment_status'] as $k) {
            $v[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $v['student_id'] = $id;
        $password = (string)($_POST['password'] ?? '');

        if ($v['firstname'] === '') { $errors[] = 'Enter the first name.'; }
        if ($v['lastname'] === '') { $errors[] = 'Enter the last name.'; }
        if ($v['gender'] !== '' && !in_array($v['gender'], GENDERS, true)) { $errors[] = 'Choose a valid gender option.'; }
        if ($v['birthdate'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $v['birthdate']);
            if (!$d || $d->format('Y-m-d') !== $v['birthdate']) { $errors[] = 'Enter a valid birthdate.'; }
        }
        if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid email address.'; }
        if (!isset($levels[(int)$v['year_level_id']])) { $errors[] = 'Choose a year level.'; }
        if (!isset($strands[(int)$v['strand_id']])) { $errors[] = 'Choose a strand.'; }
        if (!in_array($v['enrollment_status'], STATUSES, true)) { $errors[] = 'Choose an enrollment status.'; }
        if ($password !== '' && strlen($password) < 8) { $errors[] = 'A password needs at least 8 characters.'; }

        if (!$errors) {
            // A UNIQUE violation (SQLite error 23000) means the student number or email already belongs to someone else.
            try {
                if ($id > 0) {
                    $pdo->prepare(
                        'UPDATE students SET firstname=?, middlename=?, lastname=?, gender=?, birthdate=?, email=?, contact_number=?, address=?, year_level_id=?, strand_id=?, enrollment_status=? WHERE student_id=?'
                    )->execute([
                        $v['firstname'], nullable($v['middlename']), $v['lastname'], nullable($v['gender']), nullable($v['birthdate']),
                        nullable($v['email']), nullable($v['contact_number']), nullable($v['address']),
                        (int)$v['year_level_id'], (int)$v['strand_id'], $v['enrollment_status'], $id,
                    ]);
                    if ($password !== '') {
                        $pdo->prepare('UPDATE students SET password = ? WHERE student_id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                    }
                    flash('success', 'Student updated.');
                } else {
                    $number = $v['student_number'] !== '' ? $v['student_number'] : next_student_number($pdo);
                    $pdo->prepare(
                        'INSERT INTO students (student_number, firstname, middlename, lastname, gender, birthdate, email, contact_number, address, year_level_id, strand_id, enrollment_status, password)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute([
                        $number, $v['firstname'], nullable($v['middlename']), $v['lastname'], nullable($v['gender']), nullable($v['birthdate']),
                        nullable($v['email']), nullable($v['contact_number']), nullable($v['address']),
                        (int)$v['year_level_id'], (int)$v['strand_id'], $v['enrollment_status'],
                        $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
                    ]);
                    flash('success', 'Student added with number ' . $number . '.');
                }
                redirect('admin/students.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'That student number or email is already used by another student.';
                } else {
                    throw $e;
                }
            }
        }
        if ($id > 0) {
            $n = $pdo->prepare('SELECT student_number FROM students WHERE student_id = ?');
            $n->execute([$id]);
            $v['student_number'] = (string)$n->fetchColumn();
        }
        $form = $v;
    }
}

// No failed submit to redisplay, so open the form only if ?edit=ID or ?new=1 was requested.
if ($form === null) {
    if (isset($_GET['edit'])) {
        $st = $pdo->prepare('SELECT * FROM students WHERE student_id = ?');
        $st->execute([(int)$_GET['edit']]);
        $row = $st->fetch();
        if ($row) {
            unset($row['password']);
            $form = $row;
        }
    } elseif (isset($_GET['new'])) {
        $form = $blank;
    }
}

/* Filters + pagination */
$q = trim((string)($_GET['q'] ?? ''));
$fStrand = (int)($_GET['strand'] ?? 0);
$fYear = (int)($_GET['year'] ?? 0);
$fStatus = (string)($_GET['status'] ?? '');
// Build the WHERE clause from the active filters. Values are bound as parameters, never pasted into the SQL.
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(s.student_number LIKE :q1 OR s.firstname LIKE :q2 OR s.lastname LIKE :q3 OR s.email LIKE :q4 OR (s.firstname || \' \' || s.lastname) LIKE :q5)';
    foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $k) { $params[':' . $k] = '%' . $q . '%'; }
}
if ($fStrand) { $where[] = 's.strand_id = :strand'; $params[':strand'] = $fStrand; }
if ($fYear) { $where[] = 's.year_level_id = :year'; $params[':year'] = $fYear; }
if (in_array($fStatus, STATUSES, true)) { $where[] = 's.enrollment_status = :status'; $params[':status'] = $fStatus; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count the matching rows for the pager, then fetch only the current page.
$perPage = 15;
$cnt = $pdo->prepare("SELECT COUNT(*) FROM students s $whereSql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));

$st = $pdo->prepare(
    "SELECT s.*, y.year_level_name, g.strand_code
     FROM students s
     JOIN year_levels y ON y.year_level_id = s.year_level_id
     JOIN strands g ON g.strand_id = s.strand_id
     $whereSql
     ORDER BY s.lastname, s.firstname
     LIMIT $perPage OFFSET " . (($page - 1) * $perPage)
);
$st->execute($params);
$students = $st->fetchAll();

// ---- Page output (HTML below) ----
page_start('Students', 'admin', 'students');
?>
<div class="page-head">
  <div><h1>Students</h1><p><?= $total ?> <?= $total === 1 ? 'student' : 'students' ?><?= $where ? ' match your filters' : ' on record' ?>.</p></div>
  <div class="head-actions"><a class="btn" href="<?= h(url('admin/students.php?new=1')) ?>">Add student</a></div>
</div>

<?php if ($form !== null): $isEdit = (int)$form['student_id'] > 0; ?>
<section class="panel" id="student-form">
  <div class="panel-head"><h2><?= $isEdit ? 'Edit ' . h(full_name($form)) : 'Add a student' ?></h2></div>
  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><div>Fix the following and try again:<ul>
      <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
    </ul></div></div>
  <?php endif; ?>
  <form method="post" action="<?= h(url('admin/students.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="student_id" value="<?= (int)$form['student_id'] ?>">
    <div class="form-grid">
      <div class="field">
        <label for="student_number">Student number</label>
        <input type="text" id="student_number" name="student_number" value="<?= h($form['student_number']) ?>" <?= $isEdit ? 'disabled' : '' ?>>
        <?php if (!$isEdit): ?><div class="hint">Leave blank to generate one.</div><?php endif; ?>
      </div>
      <div class="field"><label for="firstname">First name</label><input type="text" id="firstname" name="firstname" value="<?= h($form['firstname']) ?>" required></div>
      <div class="field"><label for="middlename">Middle name</label><input type="text" id="middlename" name="middlename" value="<?= h($form['middlename']) ?>"></div>
      <div class="field"><label for="lastname">Last name</label><input type="text" id="lastname" name="lastname" value="<?= h($form['lastname']) ?>" required></div>
      <div class="field"><label for="gender">Gender</label><select id="gender" name="gender"><?= options_html(same_map(GENDERS), $form['gender'], 'Not set') ?></select></div>
      <div class="field"><label for="birthdate">Birthdate</label><input type="date" id="birthdate" name="birthdate" value="<?= h($form['birthdate']) ?>"></div>
      <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= h($form['email']) ?>"></div>
      <div class="field"><label for="contact_number">Contact number</label><input type="text" id="contact_number" name="contact_number" value="<?= h($form['contact_number']) ?>"></div>
      <div class="field"><label for="year_level_id">Year level</label><select id="year_level_id" name="year_level_id" required><?= options_html($levels, $form['year_level_id'], 'Choose…') ?></select></div>
      <div class="field"><label for="strand_id">Strand</label><select id="strand_id" name="strand_id" required><?= options_html($strands, $form['strand_id'], 'Choose…') ?></select></div>
      <div class="field"><label for="enrollment_status">Status</label><select id="enrollment_status" name="enrollment_status"><?= options_html(same_map(STATUSES), $form['enrollment_status']) ?></select></div>
      <div class="field">
        <label for="password"><?= $isEdit ? 'New password' : 'Password' ?></label>
        <input type="password" id="password" name="password" autocomplete="new-password" minlength="8">
        <div class="hint"><?= $isEdit ? 'Leave blank to keep the current password.' : 'Optional. The student cannot sign in until one is set.' ?></div>
      </div>
      <div class="field span-all"><label for="address">Address</label><textarea id="address" name="address"><?= h($form['address']) ?></textarea></div>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit"><?= $isEdit ? 'Save changes' : 'Add student' ?></button>
      <a class="btn btn-quiet" href="<?= h(url('admin/students.php')) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php endif; ?>

<form method="get" class="filters">
  <div class="field grow"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Name, student number or email"></div>
  <div class="field"><label for="f-year">Year level</label><select id="f-year" name="year"><?= options_html($levels, $fYear ?: '', 'All') ?></select></div>
  <div class="field"><label for="f-strand">Strand</label><select id="f-strand" name="strand"><?php
    $codes = [];
    foreach ($pdo->query('SELECT strand_id, strand_code FROM strands ORDER BY strand_code') as $r) { $codes[$r['strand_id']] = $r['strand_code']; }
    echo options_html($codes, $fStrand ?: '', 'All'); ?></select></div>
  <div class="field"><label for="f-status">Status</label><select id="f-status" name="status"><?= options_html(same_map(STATUSES), $fStatus, 'All') ?></select></div>
  <button class="btn btn-quiet" type="submit">Filter</button>
  <?php if ($where): ?><a class="btn btn-quiet" href="<?= h(url('admin/students.php')) ?>">Clear</a><?php endif; ?>
</form>

<div class="table-wrap">
<?php if (!$students): ?>
  <div class="empty">
    <strong><?= $where ? 'No students match these filters' : 'No students yet' ?></strong>
    <?= $where ? 'Try a different search or clear the filters.' : 'Add a student, or wait for students to register themselves.' ?>
  </div>
<?php else: ?>
  <table>
    <thead><tr><th>Student</th><th>Year and strand</th><th>Contact</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($students as $s): ?>
      <tr>
        <td><a href="<?= h(url('admin/grades.php?student=' . $s['student_id'])) ?>"><?= h(full_name($s)) ?></a><span class="cell-sub mono"><?= h($s['student_number']) ?></span></td>
        <td><?= h($s['year_level_name']) ?>, <?= h($s['strand_code']) ?></td>
        <td><?= h($s['email'] ?: '—') ?><span class="cell-sub"><?= h($s['contact_number'] ?: '') ?></span></td>
        <td><?= status_badge($s['enrollment_status']) ?></td>
        <td>
          <div class="row-actions">
            <a href="<?= h(url('admin/students.php?edit=' . $s['student_id'])) ?>">Edit</a>
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="student_id" value="<?= (int)$s['student_id'] ?>">
              <button class="linkbtn danger" type="submit" data-confirm="Delete <?= h(full_name($s)) ?>? Their enrolled subjects and grades will be deleted too.">Delete</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php render_pager($page, $pages); ?>
<?php page_end('admin');
