<?php
// /ItemPilot/categories/Universal Table/delete_fields.php
declare(strict_types=1);
require_once __DIR__ . '/../../db.php';
session_start();

/* ---------- AJAX helpers ---------- */
function is_ajax(): bool {
  return (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
  ) || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
}
function json_out(array $payload, int $code = 200): void {
  while (ob_get_level()) { ob_end_clean(); }
  header_remove('Location');
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}

/* ---------- Auth + inputs ---------- */
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
  is_ajax() ? json_out(['ok'=>false,'error'=>'Unauthorized'], 401) : (http_response_code(401) && exit('Unauthorized'));
}

$id       = isset($_REQUEST['id'])       ? (int)$_REQUEST['id']       : 0;  // accept GET or POST
$table_id = isset($_REQUEST['table_id']) ? (int)$_REQUEST['table_id'] : 0;

if ($id <= 0) {
  is_ajax() ? json_out(['ok'=>false,'error'=>'Missing id'], 400) : (http_response_code(400) && exit('Bad request'));
}

/* ---------- Safe SQL identifier (matches THEAD editor rules) ---------- */
$makeIdent = function(string $s): string {
  $s = preg_replace('/\s+/', '_', $s);
  $s = preg_replace('/[^A-Za-z0-9_]/', '', $s);
  if ($s === '' || ctype_digit($s[0])) $s = '_' . $s;
  return substr($s, 0, 64);
};

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  // If table_id wasn't provided (AJAX safety), look it up by id + user
  if ($table_id <= 0) {
    $stmt = $conn->prepare("SELECT table_id FROM applicants_fields WHERE id=? AND user_id=? LIMIT 1");
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $rowTmp = $res->fetch_assoc();
    $stmt->close();
    if (!$rowTmp) throw new RuntimeException("No field mapping found.");
    $table_id = (int)$rowTmp['table_id'];
  }

  // 1) Read field name (scoped to user & table)
  $stmt = $conn->prepare("SELECT field_name FROM applicants_fields WHERE id=? AND table_id=? AND user_id=? LIMIT 1");
  $stmt->bind_param('iii', $id, $table_id, $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if (!$row) throw new RuntimeException("No field mapping found for id={$id} (table_id={$table_id}).");

  $storedName = (string)$row['field_name'];
  $colName    = $makeIdent($storedName);

  // 2) Delete the mapping
  $stmt = $conn->prepare("DELETE FROM applicants_fields WHERE id=? AND table_id=? AND user_id=?");
  $stmt->bind_param('iii', $id, $table_id, $uid);
  $stmt->execute();
  if ($stmt->affected_rows === 0) throw new RuntimeException("Delete failed or already removed.");
  $stmt->close();

  // 3) Drop column in universal_base if no other mapping references it
  $stmt = $conn->prepare("SELECT COUNT(*) FROM applicants_fields WHERE user_id=? AND table_id=? AND field_name=?");
  $stmt->bind_param('iis', $uid, $table_id, $storedName);
  $stmt->execute();
  $stmt->bind_result($stillUsing);
  $stmt->fetch();
  $stmt->close();

  $dropped = false;
  if ((int)$stillUsing === 0) {
    // Confirm column exists
    $stmt = $conn->prepare("
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'applicants_base'
        AND COLUMN_NAME  = ?
      LIMIT 1
    ");
    $stmt->bind_param('s', $colName);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_column();
    $stmt->close();

    if ($exists) {
      $conn->query("ALTER TABLE `applicants_base` DROP COLUMN `{$colName}`");
      $dropped = true;
    }
  }

  $conn->commit();

  if (is_ajax()) {
    json_out([
      'ok'            => true,
      'table_id'      => $table_id,
      'field_id'      => $id,
      'field_name'    => $storedName,   // return canonical name used by client to remove inputs
      'dropped_column'=> $dropped
    ]);
  }

  // Non-AJAX: keep your old redirect
  header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  if (is_ajax()) {
    json_out(['ok'=>false, 'error'=>'Delete failed: '.$e->getMessage()], 500);
  }
  http_response_code(500);
  echo "Delete failed: " . $e->getMessage();
  exit;
}
