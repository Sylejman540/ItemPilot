<?php
// edit_universal.php
require_once __DIR__ . '/../../db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  die("No valid ID provided");
}

// 1) If this is a POST, run the UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title     = $_POST['title']     ?? '';

  $sql = "UPDATE universal SET title = ? WHERE id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('si', $title, $id);
  if (! $stmt->execute()) {
    http_response_code(500);
    echo "Update failed: " . $stmt->error;
  }else {
    header("Location: /ItemPilot/home.php?autoload=1");
    exit;
  }
}


$stmt = $conn->prepare("SELECT title FROM universal WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($title);
if (! $stmt->fetch()) {
  die("Record #{$id} not found");
}
$stmt->close();

?>
