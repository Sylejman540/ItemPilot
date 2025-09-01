<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { http_response_code(401); exit('Unauthorized'); }

$photo            = trim($_POST['photo'] ?? '');
$brand_flavor     = trim($_POST['brand_flavor'] ?? '');
$quantity         = trim($_POST['quantity'] ?? '');
$department       = trim($_POST['department'] ?? '');
$purchased        = trim($_POST['purchased'] ?? '');
$notes            = trim($_POST['notes'] ?? '');
$table_id         = trim($_POST['table_id'] ?? ''); 

// require a table_id
if ($table_id <= 0) {
  http_response_code(400);
  exit('Missing or invalid table_id');
}

$sql = "INSERT INTO groceries_head (photo, brand_flavor, quantity, department, purchased, notes, user_id, table_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) { http_response_code(500); exit('Prepare failed: ' . $conn->error); }

$stmt->bind_param('ssssssii', $photo, $brand_flavor, $quantity, $department, $purchased, $notes, $uid, $table_id);

if (!$stmt->execute()) {
  http_response_code(500);
  exit('Insert failed: ' . $stmt->error);
}
$stmt->close();

header("Location: /ItemPilot/home.php?autoload=1&type=groceries&table_id={$table_id}");
exit;
