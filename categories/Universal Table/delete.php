<?php
require_once __DIR__ . '/../../db.php';

$isAjax = (
  isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);


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

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok'       => true,
      'id'       => isset($row_id) ? (int)$row_id : (int)($_POST['id'] ?? 0),
      'table_id' => (int)$table_id,

      // include whatever the UI should update instantly:
      // DRESSES example:
      'profit'         => $deadlineDb ?? null, // computed on the server
      'attachment_url' => !empty($attachment) ? ($UPLOAD_URL . '/' . rawurlencode($attachment)) : null,

      // UNIVERSAL example:
      'status'         => $_POST['status'] ?? null,
    ]);
    exit;
  }

  // Non-AJAX fallback (user hard-submits or JS disabled)
  header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? "/ItemPilot/home.php?autoload=1&table_id={$table_id}"));
  exit;
