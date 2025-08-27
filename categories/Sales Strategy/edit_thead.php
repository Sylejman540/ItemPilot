<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { http_response_code(401); exit('Unauthorized'); }

$linked_initiatives = trim($_POST['linked_initiatives'] ?? '');
$executive_sponsor  = trim($_POST['executive_sponsor'] ?? '');
$status             = trim($_POST['status'] ?? '');
$complete          = trim($_POST['complete'] ?? '');
$notes             = trim($_POST['notes'] ?? '');
$priority           = trim($_POST['priority'] ?? '');
$owner             = trim($_POST['owner'] ?? '');
$deadline             = trim($_POST['deadline'] ?? '');
$attachment      = trim($_POST['attachment'] ?? '');
$table_id         = trim($_POST['table_id'] ?? '');

// require a table_id
if ($table_id <= 0) {
  http_response_code(400);
  exit('Missing or invalid table_id');
}

$sql = "INSERT INTO sales_strategy_thead (linked_initiatives, executive_sponsor, status, complete, notes, priority, owner, deadline, attachment, user_id, table_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) { http_response_code(500); exit('Prepare failed: ' . $conn->error); }

$stmt->bind_param('sssssssssii', $linked_initiatives, $executive_sponsor, $status, $complete, $notes, $priority, $owner, $deadline, $attachment, $uid, $table_id);

if (!$stmt->execute()) {
  http_response_code(500);
  exit('Insert failed: ' . $stmt->error);
}
$stmt->close();

header("Location: /ItemPilot/home.php");
exit;
