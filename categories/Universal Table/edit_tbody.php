<?php
// edit_universal.php
require_once __DIR__ . '/../../db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  die("No valid ID provided");
}

// 1) If this is a POST, run the UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = $_POST['name'] ?? '';
  $notes = $_POST['notes'] ?? '';
  $assignee = $_POST['assignee'] ?? '';
  $status = $_POST['status'] ?? '';

  $sql = "UPDATE universal SET name = ?, notes = ?, assignee = ?, status = ? WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ssssi', $name, $notes, $assignee, $status ,$id);
  if ($stmt->execute()) {
    header("Location: /ItemPilot/home.php");
    exit;
  }else {
    die("Update failed: " . $stmt->error);
  }
}


$stmt = $conn->prepare("SELECT name, notes, assignee, status FROM universal WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($name, $notes, $assignee, $status );
if (! $stmt->fetch()) {
  die("Record #{$id} not found");
}
$stmt->close();

?>
