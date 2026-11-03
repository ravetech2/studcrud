<?php
require_once __DIR__.'/includes/functions.php';
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $name=trim($_POST['fullname']??''); $username=trim($_POST['username']??''); $password=$_POST['password']??'';
  if ($name && $username && strlen($password)>=8) {
    try { $q=$pdo->prepare('INSERT INTO admin(username,password,fullname) VALUES(?,?,?)'); $q->execute([$username,password_hash($password,PASSWORD_DEFAULT),$name]); $message='Admin account created. Delete create_admin.php from the server for security.'; }
    catch(PDOException $e) { $message='Could not create admin. Username may already exist.'; }
  } else $message='Fill in all fields. Password must be at least 8 characters.';
}
page_header('Create Administrator'); ?>
<div class="auth-card"><h1>Create first admin</h1><p class="muted">Run this once after importing database.sql.</p><?php if($message) echo '<div class="notice">'.e($message).'</div>'; ?>
<form method="post"><label>Full Name<input name="fullname" required></label><label>Username<input name="username" required></label><label>Password<input type="password" name="password" minlength="8" required></label><button class="btn full">Create Admin</button></form></div><?php page_footer(); ?>
