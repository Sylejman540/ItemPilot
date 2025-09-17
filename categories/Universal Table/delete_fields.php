<?php
// /ItemPilot/categories/Universal Table/delete_fields.php
require_once __DIR__ . '/../../db.php';
session_start();

$isAjax = (
  isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);


$uid      = (int)($_SESSION['user_id'] ?? 0);
$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($uid <= 0)                { http_response_code(401); exit('Unauthorized'); }
if ($id <= 0 || $table_id<=0) { http_response_code(400); exit('Bad request'); }

// helper to make a safe SQL identifier (same rule as in your THEAD editor)
$makeIdent = function(string $s): string {
  $s = preg_replace('/\s+/', '_', $s);
  $s = preg_replace('/[^A-Za-z0-9_]/', '', $s);
  if ($s === '' || ctype_digit($s[0])) $s = '_' . $s;
  return substr($s, 0, 64);
};

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  // 1) Get the field name we’re deleting (scoped to user & table)
  $stmt = $conn->prepare("SELECT field_name FROM universal_fields WHERE id=? AND table_id=? AND user_id=? LIMIT 1");
  $stmt->bind_param('iii', $id, $table_id, $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if (!$row) {
    throw new RuntimeException("No field mapping found for id={$id} (table_id={$table_id}).");
  }

  $storedName = (string)$row['field_name'];
  $colName    = $makeIdent($storedName);

  // 2) Delete the mapping row
  $stmt = $conn->prepare("DELETE FROM universal_fields WHERE id=? AND table_id=? AND user_id=?");
  $stmt->bind_param('iii', $id, $table_id, $uid);
  $stmt->execute();
  if ($stmt->affected_rows === 0) {
    throw new RuntimeException("Delete failed or already removed.");
  }
  $stmt->close();

  // 3) If no other mappings in this table still point to the same name, drop the column
  $stmt = $conn->prepare("SELECT COUNT(*) FROM universal_fields WHERE user_id=? AND table_id=? AND field_name=?");
  $stmt->bind_param('iis', $uid, $table_id, $storedName);
  $stmt->execute();
  $stmt->bind_result($stillUsing);
  $stmt->fetch();
  $stmt->close();

  if ((int)$stillUsing === 0) {
    // Confirm the column actually exists before dropping
    $stmt = $conn->prepare("
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'universal_base'
        AND COLUMN_NAME  = ?
      LIMIT 1
    ");
    $stmt->bind_param('s', $colName);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_column();
    $stmt->close();

    if ($exists) {
      // Drop the dynamic column
      $conn->query("ALTER TABLE `universal_base` DROP COLUMN `{$colName}`");
    }
  }

  $conn->commit();
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
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo "Delete failed: " . $e->getMessage();
  exit;
}
