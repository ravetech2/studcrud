<?php
require_once __DIR__.'/includes/functions.php';
if (logged_in()) redirect_home();
page_header('Welcome');
?>
<section class="hero">
 <div><p class="eyebrow">SENIOR HIGH SCHOOL</p><h1>Learning management made simple.</h1>
 <p>Manage student records, strands, subjects, enrollment, and grades in one organized portal.</p>
 <div class="actions"><a class="btn" href="auth/login.php">Sign in</a><a class="btn btn-outline" href="auth/register.php">Student registration</a></div></div>
 <div class="hero-card"><div class="big-icon">✦</div><h2>Senior High Portal</h2><p>Admin and student access</p><div class="mini-stats"><span>Students</span><span>Subjects</span><span>Grades</span></div></div>
</section>
<?php page_footer(); ?>
