<?php
require_once __DIR__.'/../../db.php';
session_start();
$uid = $_SESSION['user_id'] ?? 0;
$key  = $_POST['col_key'];
$text = $_POST['label_text'];

$stmt = $conn->prepare("
  INSERT INTO user_table_labels
    (user_id,col_key,label_text)
  VALUES (?,?,?)
  ON DUPLICATE KEY UPDATE
    label_text = VALUES(label_text)
");
$stmt->bind_param("iss",$uid,$key,$text);
$stmt->execute();
