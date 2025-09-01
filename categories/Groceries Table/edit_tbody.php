<?php
// edit_universal.php
require_once __DIR__ . '/../../db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  die("No valid ID provided");
}

// 1) If this is a POST, run the UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $photo = $_POST['photo'] ?? '';
  $brand_flavor = $_POST['brand_flavor'] ?? '';
  $quantity = $_POST['quantity'] ?? '';
  $department = $_POST['department'] ?? '';
  $purchased = $_POST['purchased'] ?? '';
  $notes = $_POST['notes'] ?? '';
  $table_id = $_POST['table_id'] ?? 0;

  $sql = "UPDATE groceries SET photo = ?, brand_flavor = ?, quantity = ?, department = ?, purchased = ?, notes = ?, WHERE id = ? AND table_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ssssssii', $photo, $brand_flavor, $quantity, $department, $purchased, $notes, $id, $table_id);
  if ($stmt->execute()) {
header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
exit;
  }else {
    die("Update failed: " . $stmt->error);
  }
}


$stmt = $conn->prepare("SELECT photo, brand_flavor, quantity, department, purchased, notes FROM universal WHERE id = ? AND table_id = ?");
$stmt->bind_param('ii', $id, $table_id);
$stmt->execute();
$stmt->bind_result($name, $notes, $assignee, $status );
if (! $stmt->fetch()) {
  die("Record #{$id} not found");
}
$stmt->close();

?>
