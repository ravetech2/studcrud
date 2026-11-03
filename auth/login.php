<?php
require_once __DIR__.'/../includes/functions.php';
if (logged_in()) redirect_home();
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $username=trim($_POST['username']??''); $password=$_POST['password']??'';
  $q=$pdo->prepare("SELECT admin_id AS id, username, password, fullname, 'admin' AS role FROM admin WHERE username=?");
  $q->execute([$username]); $user=$q->fetch();
  if (!$user) {
    $q=$pdo->prepare("SELECT student_id AS id, student_number AS username, password, CONCAT(firstname,' ',lastname) AS fullname, 'student' AS role FROM students WHERE student_number=?");
    $q->execute([$username]); $user=$q->fetch();
  }
  if ($user && password_verify($password,$user['password'])) {
    session_regenerate_id(true); $_SESSION['role']=$user['role']; $_SESSION['user_id']=$user['id']; $_SESSION['fullname']=$user['fullname'];
    header('Location: '.($user['role']==='admin'?'../admin/dashboard.php':'../student/dashboard.php')); exit;
  } else $error='Invalid username/student number or password.';
}
page_header('Login'); ?>
<div class="auth-card"><h1>Welcome back</h1><p class="muted">Sign in to your account.</p><?php if($error) echo '<div class="error">'.e($error).'</div>'; notice(); ?>
<form method="post"><label>Username or Student Number<input name="username" required autofocus></label><label>Password<input type="password" name="password" required></label><button class="btn full">Login</button></form>
<p class="muted center">New student? <a href="register.php">Register here</a></p></div><?php page_footer(); ?>
