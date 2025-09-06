<?php
require_once __DIR__ . '/../../db.php';

if (! isset($_GET['table_id'])) {
  die("No table_id provided");
}
$table_id = (int) $_GET['table_id'];

// 1) Delete all rows in dresses for this table_id
$stmt = $conn->prepare("DELETE FROM `dresses` WHERE table_id = ?");
if (! $stmt) {
  die("Prepare failed (dresses): " . $conn->error);
}
$stmt->bind_param('i', $table_id);
if (! $stmt->execute()) {
  die("Execute failed (dresses): " . $stmt->error);
}
$stmt->close();

// 2) Delete the table itself
$stmt = $conn->prepare("DELETE FROM `dresses_table` WHERE table_id = ?");
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

header("Location: /ItemPilot/home.php");
exit;
