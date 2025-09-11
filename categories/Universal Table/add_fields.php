<?php
require_once __DIR__ . '/../../db.php';
session_start();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $field_name = trim($_POST['field_name'] ?? '');
  $table_id   = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
  if (!$table_id) {
    $table_id = filter_input(INPUT_GET, 'table_id', FILTER_VALIDATE_INT);
  }
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

  if ($field_name === '' || !$table_id || $uid <= 0) {
    header('Location: /ItemPilot/categories/Universal Table/insert_universal.php?error=missing_params');
    exit();
  }

  $sql = "INSERT INTO universal_fields (table_id, user_id, field_name) VALUES (?, ?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('iis', $table_id, $uid, $field_name);
  $stmt->execute();

  $sql = "ALTER TABLE universal_base ADD COLUMN `" . str_replace("`", "``", $field_name) . "` TEXT";
  $stmt = $conn->prepare($sql);
  $stmt->execute();

  $sql = "INSERT INTO universal_base (table_id, user_id) VALUES (?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ii', $table_id, $uid);
  $stmt->execute();

  header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
  exit();
}
