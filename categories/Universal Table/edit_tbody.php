<?php
// edit_universal.php
require_once __DIR__ . '/../../db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  die("No valid ID provided");
}

// 1) If this is a POST, run the UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = $_POST['name'] ?? '';
  $notes = $_POST['notes'] ?? '';
  $assignee = $_POST['assignee'] ?? '';
  $status = $_POST['status'] ?? '';
  $table_id = $_POST['table_id'] ?? 0;

  $sql = "UPDATE universal SET name = ?, notes = ?, assignee = ?, status = ? WHERE id = ? AND table_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ssssii', $name, $notes, $assignee, $status, $id, $table_id);
  if ($stmt->execute()) {
    header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
    exit;
  }else {
    die("Update failed: " . $stmt->error);
  }
}


$stmt = $conn->prepare("SELECT name, notes, assignee, status FROM universal WHERE id = ? AND table_id = ?");
$stmt->bind_param('ii', $id, $table_id);
$stmt->execute();
$stmt->bind_result($name, $notes, $assignee, $status );
if (! $stmt->fetch()) {
  die("Record #{$id} not found");
}

// inputs you should already have:
$id       = (int)($_POST['id'] ?? 0);
$table_id = (int)($_POST['table_id'] ?? 0);

// the dynamic column name you want to set (e.g. from a hidden input)
$field_name = $_POST['col_name'] ?? '';

// the value to save for this row/column
$value = $_POST['value'] ?? '';

// 1) whitelist the column against INFORMATION_SCHEMA (prevents SQL injection)
$chk = $conn->prepare("
  SELECT 1
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'universal_base'
    AND COLUMN_NAME  = ?
    AND COLUMN_NAME NOT IN ('id','table_id','user_id','created_at','updated_at')
  LIMIT 1
");
$chk->bind_param('s', $field_name);
$chk->execute();
$exists = (bool)$chk->get_result()->fetch_row();
$chk->close();
if (!$exists) { die('Invalid column'); }

// 2) escape the identifier for use in SQL (backticks)
$col = str_replace('`', '``', $field_name);

// 3) build SQL with the (validated) identifier; bind only data values
$sql = "UPDATE `universal_base`
        SET `{$col}` = ?
        WHERE id = ? AND table_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('sii', $value, $id, $table_id);

if ($stmt->execute()) {
  header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
  exit;
} else {
  die("Update failed: " . $stmt->error);
}

$stmt->close();
?>
