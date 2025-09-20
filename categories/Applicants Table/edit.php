<?php
require_once __DIR__ . '/../../db.php';
session_start();

$uid = $_SESSION['user_id'] ?? 0;
if (!$uid) {
  header('Location: /ItemPilot/register/login.php');
  exit;
}

$table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
if (!$table_id) {
  $table_id = filter_input(INPUT_GET, 'table_id', FILTER_VALIDATE_INT);
}
if (!$table_id) {
  http_response_code(400);
  exit('No valid table_id provided');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $table_title = trim($_POST['table_title'] ?? '');
  if ($table_title === '') {
    $table_title = 'Undefined Title';
  }

  // Scope by user_id as well
  $sql = "UPDATE applicants_table SET table_title = ? WHERE table_id = ? AND user_id = ?";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    http_response_code(500);
    exit('Prepare failed: ' . $conn->error);
  }

  $stmt->bind_param('sii', $table_title, $table_id, $uid);
  if (!$stmt->execute()) {
    http_response_code(500);
    exit('Update failed: ' . $stmt->error);
  }
  $affected = $stmt->affected_rows;
  $stmt->close();
  $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

  if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok' => true,
    ]);
    exit;
  }

  header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
  exit;
}

// GET: fetch current title (scoped)
$stmt = $conn->prepare("SELECT table_title FROM applicants_table WHERE table_id = ? AND user_id = ?");
$stmt->bind_param('ii', $table_id, $uid);
$stmt->execute();
$stmt->bind_result($table_title);
if (!$stmt->fetch()) {
  $stmt->close();
  http_response_code(404);
  exit("Record #{$table_id} not found");
}
$stmt->close();

// If you want to render something here, echo the title or a small form.
// Otherwise, this endpoint is POST-only and you can redirect:
header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
exit;
