<?php
require_once __DIR__ . '/../../db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  die("No valid ID provided");
}

// 1) If this is a POST, run the UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $linked_initiatives = $_POST['linked_initiatives'] ?? '';
  $executive_sponsor = $_POST['executive_sponsor'] ?? '';
  $status = $_POST['status'] ?? '';
  $complete = $_POST['complete'] ?? '';
  $notes = $_POST['notes'] ?? '';
  $priority = $_POST['priority'] ?? '';
  $owner = $_POST['owner'] ?? '';
  $deadline = $_POST['deadline'] ?? '';
  $table_id = $_POST['table_id'] ?? 0;

  $sql = "UPDATE sales_strategy SET linked_initiatives = ?, executive_sponsor = ?, status = ?, complete = ?, notes = ?, priority = ?, owner = ?, deadline = ? WHERE id = ? AND table_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ssssssssii', $linked_initiatives, $executive_sponsor, $status, $complete, $notes, $priority, $owner, $deadline, $id, $table_id);
  if ($stmt->execute()) {
  header("Location: /ItemPilot/home.php?autoload=1&type=sales&table_id={$table_id}");
  exit;
  }else {
    die("Update failed: " . $stmt->error);
  }
}


$stmt = $conn->prepare("SELECT linked_initiatives, executive_sponsor, status, complete, notes, priority, owner, deadline FROM sales_strategy WHERE id = ? AND table_id = ?");
$stmt->bind_param('ii', $id, $table_id);
$stmt->execute();
$stmt->bind_result($linked_initiatives, $executive_sponsor, $status, $complete, $notes, $priority, $owner, $deadline);
if (! $stmt->fetch()) {
  die("Record #{$id} not found");
}
$stmt->close();

?>
