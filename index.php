<?php
/**
 * Landing page. Signed-in users are sent straight to their dashboard; visitors see a short intro, a sample report card and sign-in / register buttons.
 */
require_once __DIR__ . '/config/db.php';

// Already signed in? Skip the landing page.
if (admin_user()) {
    redirect('admin/dashboard.php');
}
if (student_user()) {
    redirect('student/dashboard.php');
}

// Strands are read from the database so the list always matches what the school offers.
$strands = db()->query('SELECT strand_code, strand_name FROM strands ORDER BY strand_code')->fetchAll();

// ---- Page output (HTML below) ----
page_start('Welcome', 'public');
?>
<div class="land">
  <header class="land-nav">
    <a class="brand" href="<?= h(url('index.php')) ?>">
      <img src="<?= h(asset('images/logo.svg')) ?>" alt="" width="34" height="34">
      <span>Senior High<small>School portal</small></span>
    </a>
    <a class="btn btn-quiet" href="<?= h(url('auth/login.php')) ?>">Sign in</a>
  </header>

  <section class="hero">
    <div>
      <h1>See every grade the day it is recorded.</h1>
      <p class="lead">Check your subjects and quarterly grades for Grade 11 and Grade 12, and keep your contact details current.</p>
      <div class="cta">
        <a class="btn btn-pencil" href="<?= h(url('auth/login.php')) ?>">Student sign in</a>
        <a class="btn btn-quiet" href="<?= h(url('auth/register.php')) ?>">Create a student account</a>
      </div>
    </div>

    <div>
      <div class="report sample" aria-hidden="true">
        <div class="report-head"><h2>Report card</h2><span>1st Semester</span></div>
        <table>
          <thead><tr><th>Subject</th><th class="num">Q1</th><th class="num">Q2</th><th class="num">Final</th></tr></thead>
          <tbody>
            <tr><td>General Mathematics</td><td class="num"><span class="g pass">88</span></td><td class="num"><span class="g pass">91</span></td><td class="num final">89.5</td></tr>
            <tr><td>Earth Science</td><td class="num"><span class="g pass">84</span></td><td class="num"><span class="g pass">86</span></td><td class="num final">85</td></tr>
            <tr><td>Oral Communication</td><td class="num"><span class="g fail">72</span></td><td class="num"><span class="g pass">78</span></td><td class="num final">75</td></tr>
          </tbody>
        </table>
      </div>
      <p class="sample-note">Sample card. Grades below <?= PASSING_GRADE ?> are circled in red.</p>
    </div>
  </section>

  <?php if ($strands): ?>
  <div class="strand-line">
    <?php foreach ($strands as $s): ?>
      <span><b><?= h($s['strand_code']) ?></b> <?= h($s['strand_name']) ?></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <p class="land-foot">School staff: <a href="<?= h(url('auth/login.php?role=admin')) ?>">sign in to the admin console</a>.</p>
</div>
<?php page_end('public');
