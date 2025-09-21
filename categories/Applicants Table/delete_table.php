<?php
require_once __DIR__ . '/../../db.php';

if (! isset($_GET['table_id'])) {
  die("No table_id provided");
}
$table_id = (int) $_GET['table_id'];

// 1) Delete all rows in applicants for this table_id
$stmt = $conn->prepare("DELETE FROM `applicants` WHERE table_id = ?");
if (! $stmt) {
  die("Prepare failed (applicants): " . $conn->error);
}
$stmt->bind_param('i', $table_id);
if (! $stmt->execute()) {
  die("Execute failed (applicants): " . $stmt->error);
}
$stmt->close();

// 2) Delete the table itself
$stmt = $conn->prepare("DELETE FROM `applicants_table` WHERE table_id = ?");
if (! $stmt) {
  die("Prepare failed (tables): " . $conn->error);
}
$stmt->bind_param('i', $table_id);
if (! $stmt->execute()) {
  die("Execute failed (tables): " . $stmt->error);
}

if ($stmt->affected_rows === 0) {
  die("No record found with table_id {$table_id}");
}
$stmt->close();
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
  ]);
  exit;
}

header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
exit;
