<?php
/** Displays the signed-in student's personal and enrollment information. */
require_once __DIR__ . '/../includes/functions.php';
require_role('student');
$q = $pdo->prepare('SELECT s.*,y.year_level_name,t.strand_code,t.strand_name FROM students s JOIN year_levels y ON y.year_level_id=s.year_level_id JOIN strands t ON t.strand_id=s.strand_id WHERE student_id=?');
$q->execute([$_SESSION['user_id']]);
$s = $q->fetch();
page_header('My Profile'); ?>
<div class="page-heading">
    <h1>My Profile</h1>
</div>
<div class="panel profile">
    <div class="avatar"><?= e(strtoupper(substr($s['firstname'], 0, 1))) ?></div>
    <div>
        <h2><?= e($s['firstname'] . ' ' . $s['lastname']) ?></h2>
        <p class="muted"><?= e($s['student_number']) ?></p>
    </div>
</div>
<div class="panel details">
    <?php foreach (['email' => 'Email', 'contact_number' => 'Contact Number', 'gender' => 'Gender', 'birthdate' => 'Birthdate', 'address' => 'Address', 'year_level_name' => 'Year Level', 'strand_code' => 'Strand', 'enrollment_status' => 'Status'] as $k => $label): ?>
        <p><b><?= e($label) ?>:</b> <?= e($s[$k] ?? '—') ?></p><?php endforeach; ?>
</div><?php page_footer(); ?>