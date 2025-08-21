<?php
require_once __DIR__ . '/../../db.php';
session_start();

$uid = $_SESSION['user_id'] ?? 0;
if ($uid <= 0) {
  die("Unauthorized access");
}

$thead_name       = $_POST['thead_name'] ?? '';
$thead_notes      = $_POST['thead_notes'] ?? '';
$thead_assignee   = $_POST['thead_assignee'] ?? '';
$thead_status     = $_POST['thead_status'] ?? '';
$thead_attachment = $_POST['thead_attachment'] ?? '';

$sql = "INSERT INTO universal_thead (thead_name, thead_notes, thead_assignee, thead_status, thead_attachment, user_id) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param('sssssi', $thead_name, $thead_notes, $thead_assignee, $thead_status, $thead_attachment, $uid);

if (! $stmt->execute()) {
  // if it fails, send a 500 and the error
  http_response_code(500);
  echo "Insert failed: " . $stmt->error;
  exit;
}
$stmt->close();

// ✅ Only after a successful insert, redirect
header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
exit;
