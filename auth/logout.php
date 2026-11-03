<?php
/**
 * Signs the user out: empties the session, expires the cookie, destroys the session, then starts a fresh one just to carry the 'signed out' message.
 */
require_once __DIR__ . '/../config/db.php';

// Remove everything stored for this visitor.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
// End the old session on the server.
session_destroy();

// Fresh session so the flash message below has somewhere to live.
session_start();
flash('success', 'You have been signed out.');
redirect('auth/login.php');
