<?php
require_once __DIR__ . '/../../db.php';
session_start();

if (empty($_SESSION['user_id'])) {
  header('Location: /register/login.php');
  exit;
}

$uid      = (int)$_SESSION['user_id'];
$table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
$label    = trim($_POST['column_name'] ?? '');
$type     = strtolower(trim($_POST['column_type'] ?? 'text')); // from your form

if ($table_id <= 0 || $label === '') {
  die('Missing table_id or field name.');
}

// Make a safe field key: "Priority Owner" -> "priority_owner"
$field_key = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $label));
$field_key = preg_replace('/_{2,}/', '_', trim($field_key, '_'));
if ($field_key === '' || preg_match('/^\d/', $field_key)) {
  die('Invalid field name. Use letters/numbers/_ and do not start with a number.');
}

// Allowed UI types stored in the `type` column
$allowedTypes = ['text','long_text','number','integer','date','checkbox','file'];
if (!in_array($type, $allowedTypes, true)) $type = 'text';

// Prevent duplicate field on the same table for this user
$chk = $conn->prepare("SELECT 1 FROM universal_fields WHERE user_id=? AND table_id=? AND field_key=? LIMIT 1");
$chk->bind_param('iis', $uid, $table_id, $field_key);
$chk->execute();
$chk->store_result();
if ($chk->num_rows) {
  $chk->close();
  header("Location: /ItemPilot/home.php?autoload=1&type=universal&table_id={$table_id}&msg=exists");
  exit;
}
$chk->close();

// Next sort order
$mx = $conn->prepare("SELECT COALESCE(MAX(sort_order),0) FROM universal_fields WHERE user_id=? AND table_id=?");
$mx->bind_param('ii', $uid, $table_id);
$mx->execute();
$mx->bind_result($maxSort);
$mx->fetch();
$mx->close();
$sort_order = (int)$maxSort + 1;

// Insert into universal_fields (NOTE: column is `type`, not `field_type`)
$settings = null; // or json_encode([...]) if you want defaults
$stmt = $conn->prepare("
  INSERT INTO universal_fields
    (user_id, table_id, field_key, label, type, sort_order, settings, created_at)
  VALUES
    (?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param('iisssis', $uid, $table_id, $field_key, $label, $type, $sort_order, $settings);
$stmt->execute();
$stmt->close();

header("Location: /ItemPilot/home.php?autoload=1&type=universal&table_id={$table_id}&msg=added");
exit;
