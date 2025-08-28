<?php
require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $upd = $conn->prepare("UPDATE users SET is_verified=0 WHERE id=?");
    $upd->bind_param("i", $uid);
    $upd->execute();
}

// Clear session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}
session_destroy();

header("Location: /ItemPilot/index.php");
exit;
