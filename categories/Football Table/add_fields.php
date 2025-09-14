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
    header('Location: /ItemPilot/categories/Football%20Table/insert_football.php?error=missing_params');
    exit();
  }

  $sql = "INSERT INTO football_fields (table_id, user_id, field_name) VALUES (?, ?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('iis', $table_id, $uid, $field_name);
  $stmt->execute();

  // 1) Add the column (DDL → use query, not prepare)
  $conn->query("ALTER TABLE `football_base` ADD COLUMN `".str_replace('`','``',$field_name)."` TEXT");

  // 2) Insert a row and set that new column too
  $col      = str_replace('`','``', $field_name);
  $init_val = '';

  header("Location: /ItemPilot/home.php?autoload=1&type=football&table_id={$table_id}");
  exit();
}
