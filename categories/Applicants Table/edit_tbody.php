<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid      = (int)($_SESSION['user_id'] ?? 0);
$id       = (int)($_POST['id'] ?? $_GET['id'] ?? 0);     // applicants.id
$table_id = (int)($_POST['table_id'] ?? $_GET['table_id'] ?? 0);

if ($uid <= 0 || $table_id <= 0 || $id <= 0) {
  $_SESSION['flash_error'] = 'Missing user/table/row id.';
  header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
  exit;
}

/* ---------- base fields ---------- */
$name            = trim($_POST['name'] ?? '');
$stage           = trim($_POST['stage'] ?? '');
$applying_for    = trim($_POST['applying_for'] ?? '');
$email_address   = trim($_POST['email_address'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$interview_date  = trim($_POST['interview_date'] ?? '');
$interviewer     = trim($_POST['interviewer'] ?? '');
$interview_score = trim($_POST['interview_score'] ?? '');
$notes           = trim($_POST['notes'] ?? '');

/* ---------- attachment (keep if none uploaded) ---------- */
$UPLOAD_DIR = __DIR__ . '/uploads/';
$attachment = $_POST['existing_attachment'] ?? '';

if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
  if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
    $_SESSION['flash_error'] = 'Could not create uploads directory.';
    header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
    exit;
  }
  $tmp  = $_FILES['attachment']['tmp_name'];
  $orig = basename($_FILES['attachment']['name']);
  $dest = $UPLOAD_DIR . $orig;
  if (!move_uploaded_file($tmp, $dest)) {
    $_SESSION['flash_error'] = 'Failed to save uploaded file.';
    header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
    exit;
  }
  $attachment = $orig;
}

/* ---------- dynamic fields for applicants_base ---------- */
$dynIn = $_POST['dyn'] ?? [];

/* Whitelist real columns in applicants_base */
$colRes = $conn->query("
  SELECT COLUMN_NAME
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants_base'
");
if (!$colRes) {
  $_SESSION['flash_error'] = 'Schema lookup failed for applicants_base.';
  header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
  exit;
}
$validCols = array_column($colRes->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
$exclude   = ['id','user_id','table_id','row_id','created_at','updated_at'];
$editable  = array_values(array_diff($validCols, $exclude));

$toSave = [];
foreach ($dynIn as $k => $v) {
  if (in_array($k, $editable, true)) {
    $toSave[$k] = ($v === '') ? null : $v;
  }
}

/* ---------- transaction ---------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  /* 1) Update main applicants row.
     Handle interview_date NULL cleanly by switching SQL if empty. */
  if ($interview_date === '') {
    $sql = "
      UPDATE applicants
         SET name=?, stage=?, applying_for=?, attachment=?,
             email_address=?, phone=?, interview_date=NULL,
             interviewer=?, interview_score=?, notes=?
       WHERE id=? AND table_id=? AND user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      'ssssssssssii',
      $name, $stage, $applying_for, $attachment,
      $email_address, $phone,
      $interviewer, $interview_score, $notes,
      $id, $table_id, $uid
    );
  } else {
    $sql = "
      UPDATE applicants
         SET name=?, stage=?, applying_for=?, attachment=?,
             email_address=?, phone=?, interview_date=?,
             interviewer=?, interview_score=?, notes=?
       WHERE id=? AND table_id=? AND user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      'ssssssssssiii',
      $name, $stage, $applying_for, $attachment,
      $email_address, $phone, $interview_date,
      $interviewer, $interview_score, $notes,
      $id, $table_id, $uid
    );
  }
  $stmt->execute();
  $stmt->close();

  /* 2) Ensure link row in applicants_base */
  $stmt = $conn->prepare("
    SELECT id FROM applicants_base
     WHERE table_id=? AND user_id=? AND row_id=?
     LIMIT 1
  ");
  $stmt->bind_param('iii', $table_id, $uid, $id);
  $stmt->execute();
  $base = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$base) {
    $stmt = $conn->prepare("
      INSERT INTO applicants_base (table_id, user_id, row_id) VALUES (?,?,?)
    ");
    $stmt->bind_param('iii', $table_id, $uid, $id);
    $stmt->execute();
    $stmt->close();
  }

  /* 3) Update dynamic columns (if any) */
  if ($toSave) {
    $setParts = [];
    $vals  = [];
    $types = '';

    foreach ($toSave as $col => $val) {
      if ($val === null) {
        $setParts[] = "`$col`=NULL";
      } else {
        $setParts[] = "`$col`=?";
        $vals[]  = $val;
        $types  .= 's';
      }
    }
    if (in_array('updated_at', $validCols, true)) {
      $setParts[] = "`updated_at`=NOW()";
    }

    if ($setParts) {
      $sql = "UPDATE applicants_base SET ".implode(', ', $setParts)." WHERE table_id=? AND user_id=? AND row_id=?";
      $types .= 'iii';
      $vals[]  = $table_id;
      $vals[]  = $uid;
      $vals[]  = $id;

      $byRef = static function(array &$a){ $r=[]; foreach($a as &$v){ $r[]=&$v; } return $r; };
      $stmt = $conn->prepare($sql);
      call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $byRef($vals)));
      $stmt->execute();
      $stmt->close();
    }
  }

  $conn->commit();
  $_SESSION['flash_success'] = 'Saved.';
  header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  $_SESSION['flash_error'] = 'Save failed: '.$e->getMessage();
  header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
  exit;
}
?>