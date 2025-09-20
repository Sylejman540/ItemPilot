<?php
require_once __DIR__ . '/../../db.php';

if (! isset($_GET['id'])) {
  die("No ID provided");
}
$id = (int) $_GET['id'];
$table_id = (int) ($_GET['table_id'] ?? 0);

$stmt = $conn->prepare("DELETE FROM dresses WHERE id = ? AND table_id = ?");
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

  $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

  if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok' => true,
    ]);
    exit;
  }
  header("Location: /ItemPilot/home.php?autoload=1&type=dresses&table_id={$table_id}");
  exit;


