<?php
require_once __DIR__ . '/../../db.php';

if (! isset($_GET['id'])) {
  die("No ID provided");
}
$id = (int) $_GET['id'];
$table_id = (int) ($_GET['table_id'] ?? 0);

$stmt = $conn->prepare("DELETE FROM universal WHERE id = ? AND table_id = ?");
if (! $stmt) {
  die("Prepare failed: " . $conn->error);
}

$stmt->bind_param('ii', $id, $table_id);
if (! $stmt->execute()) {
  die("Execute failed: " . $stmt->error);
}

if ($stmt->affected_rows === 0) {
  die("No record found with ID {$id}");
}

$stmt->close();

header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
exit;

