<?php
// /ItemPilot/categories/Football%20Table/edit_tbody.php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid      = (int)($_SESSION['user_id'] ?? 0);
$id       = (int)($_POST['id'] ?? $_GET['id'] ?? 0);   // football.id (row id)
$table_id = (int)($_POST['table_id'] ?? 0);

if ($uid <= 0 || $table_id <= 0 || $id <= 0) {
  $_SESSION['flash_error'] = 'Bad request: missing uid/table_id/id.';
  header("Location: /ItemPilot/home.php?autoload=1&type=football&table_id={$table_id}");
  exit;
}

/* ----- TBODY base fields ----- */
$full_name     = $_POST['full_name']     ?? '';
$position      = $_POST['position']      ?? '';
$home_address  = $_POST['home_address']  ?? '';
$email_address = $_POST['email_address'] ?? '';
$notes         = $_POST['notes']         ?? '';

/* ----- Photo (optional, keep existing if none uploaded) ----- */
$UPLOAD_DIR = __DIR__ . '/uploads/';
$photo = $_POST['existing_photo'] ?? '';
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
  if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
    $_SESSION['flash_error'] = 'Could not create uploads directory.';
    header("Location: /ItemPilot/home.php?autoload=1&type=football&table_id={$table_id}");
    exit;
  }
  $tmp  = $_FILES['photo']['tmp_name'];
  $orig = basename($_FILES['photo']['name']);
  $dest = $UPLOAD_DIR . $orig;
  if (!move_uploaded_file($tmp, $dest)) {
    $_SESSION['flash_error'] = 'Failed to save uploaded file.';
    header("Location: /ItemPilot/home.php?autoload=1&type=football&table_id={$table_id}");
    exit;
  }
  $photo = $orig;
}

/* ----- Dynamic inputs destined for football_base ----- */
$dynIn = $_POST['dyn'] ?? [];

/* ----- Prepare whitelist for football_base ----- */
$colRes = $conn->query("
  SELECT COLUMN_NAME
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'football_base'
");
if (!$colRes) {
  $_SESSION['flash_error'] = 'Schema lookup failed.';
  header("Location: /ItemPilot/home.php?autoload=1&type=football&table_id={$table_id}");
  exit;
}
$validCols = array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
$exclude   = ['id','user_id','table_id','row_id','created_at','updated_at'];
$editable  = array_values(array_diff($validCols, $exclude));

$toSave = [];
foreach ($dynIn as $k => $v) {
  if (in_array($k, $editable, true)) {
    $toSave[$k] = ($v === '') ? null : $v; // empty -> NULL (optional)
  }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  /* 1) Update the TBODY row (football) */
  $stmt = $conn->prepare("
    UPDATE `football`
       SET `photo`=?, `full_name`=?, `position`=?, `home_address`=?, `email_address`=?, `notes`=?
     WHERE `id`=? AND `table_id`=? AND `user_id`=?
  ");
  $stmt->bind_param('ssssssiii',
    $photo, $full_name, $position, $home_address, $email_address, $notes,
    $id, $table_id, $uid
  );
  $stmt->execute();
  $stmt->close();

  /* 2) Ensure a per-row base link exists (user_id, table_id, row_id) */
  $stmt = $conn->prepare("
    SELECT `id` FROM `football_base`
    WHERE `table_id`=? AND `user_id`=? AND `row_id`=?
    LIMIT 1
  ");
  $stmt->bind_param('iii', $table_id, $uid, $id);
  $stmt->execute();
  $res  = $stmt->get_result();
  $base = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  if (!$base) {
    $stmt = $conn->prepare("INSERT INTO `football_base` (`table_id`,`user_id`,`row_id`) VALUES (?,?,?)");
    $stmt->bind_param('iii', $table_id, $uid, $id);
    $stmt->execute();
    $stmt->close();
  }

  /* 3) Update ONLY this row's dynamic columns */
  if ($toSave) {
    $setParts = [];
    $vals = [];
    $types = '';

    foreach ($toSave as $col => $val) {
      if ($val === null) {
        $setParts[] = "`$col` = NULL";
      } else {
        $setParts[] = "`$col` = ?";
        $vals[] = $val; $types .= 's';
      }
    }
    if (in_array('updated_at', $validCols, true)) {
      $setParts[] = "`updated_at` = NOW()";
    }

    if ($setParts) {
      $sql = "UPDATE `football_base` SET ".implode(', ', $setParts)." WHERE `table_id`=? AND `user_id`=? AND `row_id`=?";
      $types .= 'iii';
      $vals[]  = $table_id; $vals[] = $uid; $vals[] = $id;

      $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
      $stmt = $conn->prepare($sql);
      call_user_func_array([$stmt,'bind_param'], array_merge([$types], $byRef($vals)));
      $stmt->execute();
      $stmt->close();
    }
  }

  $conn->commit();

  // AJAX success path (same as universal)
  $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
  if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
  }

  // Non-AJAX: redirect back to Football table
  header("Location: /ItemPilot/home.php?autoload=1&type=football&table_id={$table_id}");
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  $_SESSION['flash_error'] = 'Save failed: '.$e->getMessage();
  header("Location: /ItemPilot/home.php?autoload=1&type=football&table_id={$table_id}");
  exit;
}
