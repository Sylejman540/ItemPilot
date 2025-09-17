<?php
// /ItemPilot/categories/Universal Table/edit_tbody.php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid       = (int)($_SESSION['user_id'] ?? 0);
$id        = (int)($_POST['id'] ?? $_GET['id'] ?? 0); // tbody row id (row_id)
$table_id  = (int)($_POST['table_id'] ?? 0);
$return_to = $_POST['return_to'] ?? ($_SERVER['HTTP_REFERER'] ?? "/ItemPilot/home.php?autoload=1&table_id={$table_id}");

if ($uid <= 0 || $table_id <= 0 || $id <= 0) {
  $_SESSION['flash_error'] = 'Bad request: missing uid/table_id/id.';
  header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
  exit;
}

/* ----- TBODY base fields ----- */
$name      = $_POST['name']     ?? '';
$notes     = $_POST['notes']    ?? '';
$assignee  = $_POST['assignee'] ?? '';
$status    = $_POST['status']   ?? '';

/* ----- Attachment (optional) ----- */
$UPLOAD_DIR = __DIR__ . '/uploads/';
$attachment_summary = $_POST['existing_attachment'] ?? '';
if (!empty($_FILES['attachment_summary']) && $_FILES['attachment_summary']['error'] === UPLOAD_ERR_OK) {
  if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
    $_SESSION['flash_error'] = 'Could not create uploads directory.';
    header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}"); exit;
  }
  $tmp  = $_FILES['attachment_summary']['tmp_name'];
  $orig = basename($_FILES['attachment_summary']['name']);
  $dest = $UPLOAD_DIR . $orig;
  if (!move_uploaded_file($tmp, $dest)) {
    $_SESSION['flash_error'] = 'Failed to save uploaded file.';
    header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}"); exit;
  }
  $attachment_summary = $orig;
}

/* ----- Dynamic inputs destined for universal_base ----- */
$dynIn = $_POST['dyn'] ?? [];

/* ----- Prepare whitelist for universal_base ----- */
$colRes = $conn->query("
  SELECT COLUMN_NAME
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'universal_base'
");
if (!$colRes) {
  $_SESSION['flash_error'] = 'Schema lookup failed.';
  header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}"); exit;
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
  /* 1) Update the TBODY row (universal) */
  $stmt = $conn->prepare("
    UPDATE `universal`
       SET `name`=?, `notes`=?, `assignee`=?, `status`=?, `attachment_summary`=?
     WHERE `id`=? AND `table_id`=? AND `user_id`=?
  ");
  $stmt->bind_param('sssssiii', $name, $notes, $assignee, $status, $attachment_summary, $id, $table_id, $uid);
  $stmt->execute();
  $stmt->close();

  /* 2) Ensure a per-row base link exists (user_id, table_id, row_id) */
  $stmt = $conn->prepare("
    SELECT `id` FROM `universal_base`
    WHERE `table_id`=? AND `user_id`=? AND `row_id`=?
    LIMIT 1
  ");
  $stmt->bind_param('iii', $table_id, $uid, $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $base = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  if (!$base) {
    $stmt = $conn->prepare("INSERT INTO `universal_base` (`table_id`,`user_id`,`row_id`) VALUES (?,?,?)");
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
      $sql = "UPDATE `universal_base` SET ".implode(', ', $setParts)." WHERE `table_id`=? AND `user_id`=? AND `row_id`=?";
      $types .= 'iii';
      $vals[] = $table_id; $vals[] = $uid; $vals[] = $id;

      // bind_param needs references
      $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
      $stmt = $conn->prepare($sql);
      call_user_func_array([$stmt,'bind_param'], array_merge([$types], $byRef($vals)));
      $stmt->execute();
      $stmt->close();
    }
  }

  $conn->commit();
  $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

  if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok' => true,
      // for INSERT/EDIT optionally send a ready-to-insert row
      // 'row_html' => $renderedRowHtml,
      // 'table_id' => (int)$table_id,
    ]);
    exit;
  }

  // Non-AJAX fallback:
  header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
  exit;


} catch (Throwable $e) {
  $conn->rollback();
  $_SESSION['flash_error'] = 'Save failed: '.$e->getMessage();
  header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
  exit;
}
