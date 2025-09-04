<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { http_response_code(401); exit('Unauthorized'); }

$photo           = trim($_POST['photo'] ?? '');
$full_name       = trim($_POST['full_name'] ?? '');
$position        = trim($_POST['position'] ?? '');
$home_address    = trim($_POST['home_address'] ?? '');
$email_address   = trim($_POST['email_address'] ?? '');
$notes           = trim($_POST['notes'] ?? '');
$table_id        = trim($_POST['table_id'] ?? ''); 

// require a table_id
if ($table_id <= 0) {
  http_response_code(400);
  exit('Missing or invalid table_id');
}

$sql = "INSERT INTO football_thead (photo, full_name, position, home_address, email_address, notes ,user_id, table_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) { http_response_code(500); exit('Prepare failed: ' . $conn->error); }

$stmt->bind_param('ssssssii', $photo, $full_name, $position, $home_address, $email_address, $notes, $uid, $table_id);

if (!$stmt->execute()) {
  http_response_code(500);
  exit('Insert failed: ' . $stmt->error);
}
$stmt->close();

header("Location: /ItemPilot/home.php?autoload=1&type=football&table_id={$table_id}");
exit;
