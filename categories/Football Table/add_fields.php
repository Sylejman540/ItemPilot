<?php
// /ItemPilot/categories/Universal Table/add_fields.php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { http_response_code(401); exit('Unauthorized'); }

$table_id   = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
$field_name = trim($_POST['field_name'] ?? '');

if (!$table_id || $field_name === '') { http_response_code(400); exit('Bad request'); }

function validate_field_name(string $s): bool {
  return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $s);
}
function is_reserved_field(string $s): bool {
  static $r = ['id','user_id','table_id','row_id','created_at','updated_at'];
  return in_array(strtolower($s), $r, true);
}

if (!validate_field_name($field_name) || is_reserved_field($field_name)) {
  http_response_code(400); exit('Invalid field name');
}

// prevent duplicates
$du = $conn->prepare("SELECT 1 FROM universal_fields WHERE user_id=? AND table_id=? AND field_name=? LIMIT 1");
$du->bind_param('iis', $uid, $table_id, $field_name);
$du->execute(); $exists = (bool)$du->get_result()->fetch_row(); $du->close();
if ($exists) {
  header("Location: /ItemPilot/categories/Universal%20Table/insert_universal.php?table_id={$table_id}");
  exit;
}

// add column if missing
$col = $conn->prepare("
  SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='universal_base' AND COLUMN_NAME=?
");
$col->bind_param('s', $field_name);
$col->execute(); $has = (bool)$col->get_result()->fetch_row(); $col->close();

if (!$has) {
  $sql = "ALTER TABLE `universal_base` ADD COLUMN `{$field_name}` VARCHAR(255) NULL";
  if (!$conn->query($sql)) { http_response_code(500); exit('ALTER ADD failed: '.$conn->error); }
}

// insert mapping
$ins = $conn->prepare("INSERT INTO universal_fields (user_id, table_id, field_name) VALUES (?,?,?)");
$ins->bind_param('iis', $uid, $table_id, $field_name);
$ins->execute(); $ins->close();

header("Location: /ItemPilot/categories/Universal%20Table/insert_universal.php?table_id={$table_id}");
exit;
