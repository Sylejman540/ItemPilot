<?php
require_once __DIR__ . '/../../db.php';

if (! isset($_GET['table_id'])) {
  die("No table_id provided");
}
$table_id = (int) $_GET['table_id'];

// 1) Delete all rows in universal for this table_id
$stmt = $conn->prepare("DELETE FROM `football` WHERE table_id = ?");
if (! $stmt) {
  die("Prepare failed (football): " . $conn->error);
}
$stmt->bind_param('i', $table_id);
if (! $stmt->execute()) {
  die("Execute failed (football): " . $stmt->error);
}
$stmt->close();

// 2) Delete the table itself
$stmt = $conn->prepare("DELETE FROM `football_table` WHERE table_id = ?");
if (! $stmt) {
  die("Prepare failed (football_table): " . $conn->error);
}
$stmt->bind_param('i', $table_id);
if (! $stmt->execute()) {
  die("Execute failed (football_table): " . $stmt->error);
}

if ($stmt->affected_rows === 0) {
  die("No record found with table_id {$table_id}");
}
$stmt->close();

header("Location: /ItemPilot/home.php?autoload=1");
exit;
