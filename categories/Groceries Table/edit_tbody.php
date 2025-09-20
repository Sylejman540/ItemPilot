<?php
// /ItemPilot/categories/Groceries/edit_groceries.php
require_once __DIR__ . '/../../db.php';
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ---------- helpers ---------- */
function is_ajax(): bool {
  return (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
  ) || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
     || (($_POST['ajax'] ?? $_GET['ajax'] ?? '') === '1');
}
function json_out(array $p, int $code = 200): void {
  while (ob_get_level()) { ob_end_clean(); }
  header_remove('Location');
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($p);
  exit;
}

/* ---------- auth & ids ---------- */
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
  if (is_ajax()) json_out(['ok'=>false,'error'=>'Unauthorized'], 401);
  http_response_code(401); exit('Unauthorized');
}

$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id       = (int)($_POST['id'] ?? $_GET['id'] ?? 0);          // groceries.id
$table_id = (int)($_POST['table_id'] ?? $_GET['table_id'] ?? 0);

if ($id <= 0 || $table_id <= 0) {
  if (is_ajax()) json_out(['ok'=>false,'error'=>'Missing id/table_id','debug'=>compact('id','table_id')], 400);
  http_response_code(400); exit('Missing id/table_id');
}

/* =======================================================================
   POST: update groceries + dynamic fields
   ======================================================================= */
if ($method === 'POST') {
  // Base columns
  $brand_flavor = trim($_POST['brand_flavor'] ?? '');
  $quantity     = trim($_POST['quantity'] ?? '');
  $department   = trim($_POST['department'] ?? '');
  $purchased    = empty($_POST['purchased']) ? 0 : 1;   // checkbox -> tinyint(1)
  $notes        = trim($_POST['notes'] ?? '');
  $photo        = trim($_POST['photo'] ?? '');          // keep existing if no upload

  // Optional upload
  $UPLOAD_DIR = __DIR__ . '/uploads/';
  if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
      if (is_ajax()) json_out(['ok'=>false,'error'=>'Could not create uploads directory.'], 500);
      http_response_code(500); exit('Could not create uploads directory.');
    }
    $tmp  = $_FILES['photo']['tmp_name'];
    $orig = basename($_FILES['photo']['name']);
    $dest = $UPLOAD_DIR . $orig;
    if (!move_uploaded_file($tmp, $dest)) {
      if (is_ajax()) json_out(['ok'=>false,'error'=>'Failed to save uploaded file.'], 500);
      http_response_code(500); exit('Failed to save uploaded file.');
    }
    $photo = $orig;
  }

  // Dynamic inputs
  $dynIn = $_POST['dyn'] ?? [];

  // Also accept extra_field_{id} posts (e.g., from modal)
  $mapStmt = $conn->prepare("SELECT id, field_name FROM groceries_fields WHERE user_id=? AND table_id=? ORDER BY id ASC");
  $mapStmt->bind_param('ii', $uid, $table_id);
  $mapStmt->execute();
  $res = $mapStmt->get_result();
  while ($m = $res->fetch_assoc()) {
    $k = 'extra_field_' . (int)$m['id'];
    if (array_key_exists($k, $_POST)) {
      $v = $_POST[$k];
      $dynIn[$m['field_name']] = ($v === '') ? null : $v;
    }
  }
  $mapStmt->close();

  // Whitelist real columns in groceries_base
  $colRes = $conn->query("
    SELECT COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'groceries_base'
  ");
  if (!$colRes) {
    if (is_ajax()) json_out(['ok'=>false,'error'=>'Schema lookup failed for groceries_base'], 500);
    http_response_code(500); exit('Schema lookup failed for groceries_base');
  }
  $validCols = array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
  $exclude   = ['id','user_id','table_id','row_id','created_at','updated_at'];
  $editable  = array_values(array_diff($validCols, $exclude));

  $toSave = [];
  foreach ($dynIn as $k => $v) {
    if (in_array($k, $editable, true)) {
      $toSave[$k] = ($v === '' ? null : $v);
    }
  }

  $conn->begin_transaction();

  try {
    /* 1) Update main groceries row */
    $sql = "
      UPDATE `groceries`
         SET `photo` = ?,
             `brand_flavor` = ?,
             `quantity` = ?,
             `department` = ?,
             `purchased` = ?,
             `notes` = ?
       WHERE `id` = ? AND `table_id` = ? AND `user_id` = ?
    ";
    $stmt = $conn->prepare($sql);
    // 4 strings, 1 int, 1 string, then 3 ints  => 'ssssisiii'
    $stmt->bind_param(
      'ssssisiii',
      $photo, $brand_flavor, $quantity, $department, $purchased, $notes,
      $id, $table_id, $uid
    );
    $stmt->execute();
    $stmt->close();

    /* 2) Ensure link row exists in groceries_base */
    $chk = $conn->prepare("
      SELECT id FROM `groceries_base`
       WHERE `table_id`=? AND `user_id`=? AND `row_id`=?
       LIMIT 1
    ");
    $chk->bind_param('iii', $table_id, $uid, $id);
    $chk->execute();
    $base = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$base) {
      $ins = $conn->prepare("INSERT INTO `groceries_base` (`table_id`,`user_id`,`row_id`) VALUES (?,?,?)");
      $ins->bind_param('iii', $table_id, $uid, $id);
      $ins->execute();
      $ins->close();
    }

    /* 3) Update dynamic values */
    if ($toSave) {
      $set   = [];
      $vals  = [];
      $types = '';
      foreach ($toSave as $col => $val) {
        if ($val === null) {
          $set[] = "`$col`=NULL";
        } else {
          $set[] = "`$col`=?";
          $vals[] = $val; $types .= 's';
        }
      }
      if (in_array('updated_at', $validCols, true)) {
        $set[] = "`updated_at`=NOW()";
      }
      if ($set) {
        $sql = "UPDATE `groceries_base` SET ".implode(', ', $set)." WHERE `table_id`=? AND `user_id`=? AND `row_id`=?";
        $types .= 'iii';
        $vals[]  = $table_id; $vals[] = $uid; $vals[] = $id;

        // bind_param needs references
        $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
        $stmt = $conn->prepare($sql);
        call_user_func_array([$stmt,'bind_param'], array_merge([$types], $byRef($vals)));
        $stmt->execute();
        $stmt->close();
      }
    }

    $conn->commit();

    // JSON on AJAX, redirect otherwise
    if (is_ajax()) {
      json_out(['ok'=>true, 'purchased'=>$purchased]);
    }
    header("Location: /ItemPilot/home.php?autoload=1&type=groceries&table_id={$table_id}");
    exit;

  } catch (Throwable $e) {
    $conn->rollback();
    if (is_ajax()) json_out(['ok'=>false,'error'=>'Save failed: '.$e->getMessage()], 500);
    http_response_code(500); exit('Save failed: '.$e->getMessage());
  }
}

/* =======================================================================
   Optional AJAX GET fetch (handy for editors)
   ======================================================================= */
$stmt = $conn->prepare("
  SELECT `photo`, `brand_flavor`, `quantity`, `department`, `purchased`, `notes`
    FROM `groceries`
   WHERE `id`=? AND `table_id`=? AND `user_id`=?
   LIMIT 1
");
$stmt->bind_param('iii', $id, $table_id, $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
  if (is_ajax()) json_out(['ok'=>false,'error'=>"Record #{$id} not found"], 404);
  http_response_code(404); exit("Record #{$id} not found");
}

if (is_ajax()) {
  json_out(['ok'=>true, 'row'=>$row]);
}

http_response_code(405);
exit('Method Not Allowed');
