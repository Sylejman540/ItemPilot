<?php
require_once __DIR__ . '/../../db.php';
session_start();

$isAjax = (
  isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);


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

  // 1) Add the column (DDL → use query, not prepare)
  $conn->query("ALTER TABLE `universal_base` ADD COLUMN `".str_replace('`','``',$field_name)."` TEXT");

  // 2) Insert a row and set that new column too
  $col      = str_replace('`','``', $field_name); // escape identifier
  $init_val = '';                                 // whatever initial value you want

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
}
