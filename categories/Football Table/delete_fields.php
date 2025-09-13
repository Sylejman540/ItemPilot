<?php
// /ItemPilot/categories/Universal Table/delete_fields.php
require_once __DIR__ . '/../../db.php';
session_start();

$uid      = (int)($_SESSION['user_id'] ?? 0);
$field_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$table_id = (int)($_GET['table_id'] ?? $_POST['table_id'] ?? 0);

if ($uid <= 0 || $field_id <= 0 || $table_id <= 0) { http_response_code(400); exit('Bad request'); }

function validate_field_name(string $s): bool {
  return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $s);
}
function is_reserved_field(string $s): bool {
  static $r = ['id','user_id','table_id','row_id','created_at','updated_at'];
  return in_array(strtolower($s), $r, true);
}

// look up field name
$s = $conn->prepare("SELECT field_name FROM universal_fields WHERE id=? AND table_id=? AND user_id=? LIMIT 1");
$s->bind_param('iii', $field_id, $table_id, $uid);
$s->execute(); $row = $s->get_result()->fetch_assoc(); $s->close();

if (!$row) { http_response_code(404); exit('Field not found'); }
$field_name = $row['field_name'];

// drop column (if valid and not reserved)
if (validate_field_name($field_name) && !is_reserved_field($field_name)) {
  $sql = "ALTER TABLE `universal_base` DROP COLUMN IF EXISTS `{$field_name}`";
  if (!$conn->query($sql)) { http_response_code(500); exit('Drop column failed: '.$conn->error); }
}

// delete mapping
$d = $conn->prepare("DELETE FROM universal_fields WHERE id=? AND table_id=? AND user_id=? LIMIT 1");
$d->bind_param('iii', $field_id, $table_id, $uid);
$d->execute(); $d->close();

header("Location: /ItemPilot/categories/Universal%20Table/insert_universal.php?table_id={$table_id}");
exit;
