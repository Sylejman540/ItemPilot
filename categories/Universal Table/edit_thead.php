<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { http_response_code(401); exit('Unauthorized'); }

$thead_name       = trim($_POST['thead_name'] ?? '');
$thead_notes      = trim($_POST['thead_notes'] ?? '');
$thead_assignee   = trim($_POST['thead_assignee'] ?? '');
$thead_status     = trim($_POST['thead_status'] ?? '');
$thead_attachment = trim($_POST['thead_attachment'] ?? '');
$table_id         = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
if (!$table_id) { http_response_code(400); exit('Missing or invalid table_id'); }

// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// $conn->set_charset('utf8mb4');

try {
  $conn->begin_transaction();

  // 1) Insert thead row
  $sql1 = "INSERT INTO universal_thead
           (thead_name, thead_notes, thead_assignee, thead_status, thead_attachment, user_id, table_id)
           VALUES (?, ?, ?, ?, ?, ?, ?)";
  $stmt1 = $conn->prepare($sql1);
  if (!$stmt1) { throw new Exception('Prepare failed: ' . $conn->error); }
  $stmt1->bind_param('sssssii',
    $thead_name, $thead_notes, $thead_assignee, $thead_status, $thead_attachment, $uid, $table_id
  );
  $stmt1->execute();
  $stmt1->close();

  // 2) Bulk-edit universal_fields.field_name from POST inputs: extra_field_{id}
  //    Example input names produced by your form:
  //    <input name="extra_field_12" value="Title">
  //    <input name="extra_field_13" value="Notes">
  $sql2  = "UPDATE universal_fields
            SET field_name = ?
            WHERE id = ? AND table_id = ? AND user_id = ?";
  $stmt2 = $conn->prepare($sql2);
  if (!$stmt2) { throw new Exception('Prepare failed: ' . $conn->error); }

  foreach ($_POST as $key => $val) {
    if (preg_match('/^extra_field_(\d+)$/', $key, $m)) {
      $field_id   = (int)$m[1];
      $field_name = trim((string)$val);
      // Optional: skip empty strings if you don't want to clear names
      // if ($field_name === '') continue;

      $stmt2->bind_param('siii', $field_name, $field_id, $table_id, $uid);
      $stmt2->execute();
    }
  }
  $stmt2->close();

  $conn->commit();

  header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
  exit;
} catch (Throwable $e) {
  if ($conn->errno === 0) { $conn->rollback(); }
  http_response_code(500);
  exit('DB error: ' . $e->getMessage());
}
