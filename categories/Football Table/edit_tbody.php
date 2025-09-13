<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

$uid      = (int)($_SESSION['user_id'] ?? 0);
$row_id   = (int)($_POST['id'] ?? $_GET['id'] ?? 0);   // football.id
$table_id = (int)($_POST['table_id'] ?? 0);
if ($uid <= 0 || $table_id <= 0 || $row_id <= 0) { http_response_code(400); exit('Bad request'); }

// fixed fields
$full_name     = trim($_POST['full_name'] ?? '');
$position      = trim($_POST['position'] ?? '');
$home_address  = trim($_POST['home_address'] ?? '');
$email_address = trim($_POST['email_address'] ?? '');
$notes         = trim($_POST['notes'] ?? '');
$photo         = $_POST['existing_photo'] ?? '';

// attachment
$UPLOAD_DIR = __DIR__ . '/uploads/';
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
  if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) { die('Could not create uploads directory'); }
  $tmp = $_FILES['photo']['tmp_name'];
  $orig = basename($_FILES['photo']['name']);
  $dest = $UPLOAD_DIR . $orig;
  if (!move_uploaded_file($tmp, $dest)) { die('Failed to save uploaded file'); }
  $photo = $orig;
}

// dynamic fields payload
$dynIn = $_POST['dyn'] ?? [];

// whitelist editable columns in football_base
$colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='football_base'");
$validCols = array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
$exclude   = ['id','user_id','table_id','row_id','created_at','updated_at'];
$editable  = array_values(array_diff($validCols, $exclude));

$toSave = [];
foreach ($dynIn as $k => $v) {
  if (in_array($k, $editable, true)) {
    $toSave[$k] = ($v === '') ? null : $v;
  }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();
try {
  // 1) Update main football row
  $stmt = $conn->prepare("UPDATE football SET photo=?, full_name=?, position=?, home_address=?, email_address=?, notes=? WHERE id=? AND table_id=? AND user_id=?");
  $stmt->bind_param('ssssssiii', $photo, $full_name, $position, $home_address, $email_address, $notes, $row_id, $table_id, $uid);
  $stmt->execute(); $stmt->close();

  // 2) Ensure base link exists
  $chk = $conn->prepare("SELECT id FROM football_base WHERE table_id=? AND user_id=? AND row_id=? LIMIT 1");
  $chk->bind_param('iii', $table_id, $uid, $row_id);
  $chk->execute();
  $base = $chk->get_result()->fetch_assoc();
  $chk->close();

  if (!$base) {
    $ins = $conn->prepare("INSERT INTO football_base (table_id,user_id,row_id) VALUES (?,?,?)");
    $ins->bind_param('iii', $table_id, $uid, $row_id);
    $ins->execute(); $ins->close();
  }

  // 3) Update dynamic columns
  if ($toSave) {
    $set = []; $vals = []; $types = '';
    foreach ($toSave as $col => $val) {
      if ($val === null) { $set[] = "`$col`=NULL"; }
      else { $set[] = "`$col`=?"; $vals[] = $val; $types .= 's'; }
    }
    if (in_array('updated_at', $validCols, true)) { $set[] = "`updated_at`=NOW()"; }
    if ($set) {
      $sql = "UPDATE football_base SET ".implode(', ',$set)." WHERE table_id=? AND user_id=? AND row_id=?";
      $types .= 'iii';
      $vals[] = $table_id; $vals[] = $uid; $vals[] = $row_id;

      $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
      $stmt = $conn->prepare($sql);
      call_user_func_array([$stmt,'bind_param'], array_merge([$types], $byRef($vals)));
      $stmt->execute(); $stmt->close();
    }
  }

  $conn->commit();
  header("Location: /ItemPilot/home.php?autoload=1&type=football&table_id={$table_id}");
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo "Save failed: ".$e->getMessage();
}
