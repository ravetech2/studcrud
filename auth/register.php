<?php
/** Registers a student account and stores a securely hashed password. */
require_once __DIR__ . '/../includes/functions.php';
$error = '';
$years = $pdo->query("SELECT * FROM year_levels ORDER BY year_level_id")->fetchAll();
$strands = $pdo->query("SELECT * FROM strands ORDER BY strand_name")->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $number = trim($_POST['student_number'] ?? '');
  $first = trim($_POST['firstname'] ?? '');
  $last = trim($_POST['lastname'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $year = (int) ($_POST['year_level_id'] ?? 0);
  $strand = (int) ($_POST['strand_id'] ?? 0);
  $pass = $_POST['password'] ?? '';
  if (!$number || !$first || !$last || strlen($pass) < 8)
    $error = 'Complete all required fields. Password must have at least 8 characters.';
  else
    try {
      $q = $pdo->prepare("INSERT INTO students(student_number,firstname,lastname,email,year_level_id,strand_id,password,enrollment_status) VALUES(?,?,?,?,?,?,?,'Pending')");
      $q->execute([$number, $first, $last, $email ?: null, $year, $strand, password_hash($pass, PASSWORD_DEFAULT)]);
      $_SESSION['flash'] = 'Registration submitted. You can now sign in.';
      header('Location: login.php');
      exit;
    } catch (PDOException $ex) {
      $error = 'Registration failed. Student number or email may already exist.';
    }
}
page_header('Student Registration'); ?>
<div class="auth-card wide">
  <h1>Student registration</h1>
  <p class="muted">Create your student portal account.</p>
  <?php if ($error)
    echo '<div class="error">' . e($error) . '</div>'; ?>
  <form method="post" class="form-grid">
    <label>Student Number<input name="student_number" required></label><label>First Name<input name="firstname"
        required></label><label>Last Name<input name="lastname" required></label><label>Email<input type="email"
        name="email"></label>
    <label>Year Level<select name="year_level_id"
        required><?php foreach ($years as $y)
          echo '<option value="' . $y['year_level_id'] . '">' . e($y['year_level_name']) . '</option>'; ?></select></label>
    <label>Strand<select name="strand_id"
        required><?php foreach ($strands as $s)
          echo '<option value="' . $s['strand_id'] . '">' . e($s['strand_code'] . ' - ' . $s['strand_name']) . '</option>'; ?></select></label>
    <label class="span-2">Password (minimum 8 characters)<input type="password" name="password" minlength="8"
        required></label><button class="btn span-2">Create account</button>
  </form>
</div><?php page_footer(); ?>