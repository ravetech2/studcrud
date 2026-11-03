<?php
/** Clears the active session and signs the user out. */
require_once __DIR__ . '/../includes/functions.php';
$_SESSION = [];
session_destroy();
header('Location: ' . url_for('index.php'));
exit;
