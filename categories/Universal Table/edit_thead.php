<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { http_response_code(401); exit('Unauthorized'); }

$thead_name       = trim($_POST['thead_name'] ?? '');
$thead_notes      = trim($_POST['thead_notes'] ?? '');
$thead_assignee   = trim($_POST['thead_assignee'] ?? '');
$thead_status     = trim($_POST['thead_status'] ?? '');
$thead_attachment = trim($_POST['thead_attachment'] ?? '');
$table_id         = trim($_POST['table_id'] ?? ''); 

// require a table_id
if ($table_id <= 0) {
  http_response_code(400);
  exit('Missing or invalid table_id');
}

$sql = "INSERT INTO universal_thead (thead_name, thead_notes, thead_assignee, thead_status, thead_attachment, user_id, table_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) { http_response_code(500); exit('Prepare failed: ' . $conn->error); }

$stmt->bind_param('sssssii', $thead_name, $thead_notes, $thead_assignee, $thead_status, $thead_attachment, $uid, $table_id
);

if (!$stmt->execute()) {
  http_response_code(500);
  exit('Insert failed: ' . $stmt->error);
}
$stmt->close();

header("Location: /ItemPilot/home.php?autoload=1");
exit;
