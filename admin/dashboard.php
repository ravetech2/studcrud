<?php
require_once __DIR__.'/../includes/functions.php'; require_role('admin');
$counts=[]; foreach(['students'=>'Students','subjects'=>'Subjects','strands'=>'Strands','admin'=>'Administrators'] as $t=>$label) $counts[$label]=(int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
page_header('Admin Dashboard'); notice(); ?>
<div class="page-heading"><div><p class="eyebrow">ADMINISTRATION</p><h1>Dashboard</h1><p class="muted">Welcome, <?=e($_SESSION['fullname'])?>.</p></div></div>
<div class="stats"><?php foreach($counts as $label=>$count) echo '<div class="stat-card"><span>'.e($label).'</span><strong>'.$count.'</strong></div>'; ?></div>
<div class="panel"><h2>Quick links</h2><div class="quick-links"><a href="students.php">Manage students →</a><a href="subjects.php">Manage subjects →</a><a href="grades.php">Encode grades →</a><a href="year-levels.php">Year levels →</a><a href="strands.php">Strands →</a><a href="manage-admin.php">Administrators →</a></div></div>
<?php page_footer(); ?>
