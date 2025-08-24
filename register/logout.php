<?php
session_start();

// Clear session data + cookie
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

/**
 * Redirect to the actual login page you have.
 * If your login file is named differently, change 'login.php' below.
 * Because logout.php is in /register/, this will resolve to /register/login.php.
 */
header('Location: /ItemPilot/index.php');
exit;
