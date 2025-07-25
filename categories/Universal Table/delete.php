<?php
require_once __DIR__ . '/../../db.php';

if (! isset($_GET['id'])) {
  die("No ID provided");
}
$id = (int) $_GET['id'];

$stmt = $conn->prepare("DELETE FROM universal WHERE id = ?");
if (! $stmt) {
  die("Prepare failed: " . $conn->error);
}

$stmt->bind_param('i', $id);
if (! $stmt->execute()) {
  die("Execute failed: " . $stmt->error);
}

if ($stmt->affected_rows === 0) {
  die("No record found with ID {$id}");
}

$stmt->close();

header("Location: /ItemPilot/home.php#events");
exit;
