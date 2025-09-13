<?php
// edit_universal.php
require_once __DIR__ . '/../../db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  die("No valid ID provided");
}

// 1) If this is a POST, run the UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name     = $_POST['full_name'] ?? '';
  $position      = $_POST['position'] ?? '';
  $home_address  = $_POST['home_address'] ?? '';
  $email_address = $_POST['email_address'] ?? '';
  $notes         = $_POST['notes'] ?? '';
  $table_id = $_POST['table_id'] ?? 0;

  $sql = "UPDATE football SET full_name = ?, position = ?, home_address = ?, email_address = ?, notes = ? WHERE id = ? AND table_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('sssssii', $full_name, $position, $home_address, $email_address, $notes, $id, $table_id);
  if ($stmt->execute()) {
  header("Location: /ItemPilot/home.php?autoload=1&type=football&table_id={$table_id}");
  exit;
  }else {
    die("Update failed: " . $stmt->error);
  }
}


$stmt = $conn->prepare("SELECT full_name, position, home_address, email_address, notes FROM football WHERE id = ? AND table_id = ?");
$stmt->bind_param('ii', $id, $table_id);
$stmt->execute();
$stmt->bind_result($full_name, $position, $home_address, $email_address, $notes);
if (! $stmt->fetch()) {
  die("Record #{$id} not found");
}
$stmt->close();

?>
