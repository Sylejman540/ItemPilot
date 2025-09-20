<?php
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
function json_out(array $p, int $code=200): void {
  while (ob_get_level()) { ob_end_clean(); }
  header_remove('Location');
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($p);
  exit;
}
$parse_money = function ($s) {
  $s = (string)$s;
  if ($s === '') return null;
  if (preg_match('/-?\d+(?:[.,]\d+)?/', $s, $m)) {
    return (float) str_replace(',', '.', $m[0]);
  }
  return null;
};

/* ---------- auth & method ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  if (is_ajax()) json_out(['ok'=>false, 'error'=>'Method Not Allowed'], 405);
  http_response_code(405); exit('Method Not Allowed');
}

$uid      = (int)($_SESSION['user_id'] ?? 0);
$id       = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$table_id = (int)($_POST['table_id'] ?? $_GET['table_id'] ?? 0);

if ($uid <= 0) {
  if (is_ajax()) json_out(['ok'=>false, 'error'=>'Unauthorized'], 401);
  http_response_code(401); exit('Unauthorized');
}
if ($id <= 0 || $table_id <= 0) {
  if (is_ajax()) json_out(['ok'=>false, 'error'=>'Missing id/table_id','debug'=>compact('id','table_id')], 400);
  http_response_code(400); exit('Missing id/table_id');
}

/* ---------- inputs ---------- */
$linked_initiatives = $_POST['linked_initiatives'] ?? '';
$executive_sponsor  = $_POST['executive_sponsor'] ?? '';
$status             = $_POST['status'] ?? '';
$complete           = $_POST['complete'] ?? '';
$notes              = $_POST['notes'] ?? '';
$priority           = $_POST['priority'] ?? ''; // price
$owner              = $_POST['owner'] ?? '';    // material cost

// deadline = price - cost (nullable)
$priceNum   = $parse_money($priority);
$costNum    = $parse_money($owner);
$deadlineDb = (is_null($priceNum) && is_null($costNum))
  ? null
  : number_format((float)$priceNum - (float)$costNum, 2, '.', '');

/* ---------- update ---------- */
$sql = "
  UPDATE `dresses`
     SET `linked_initiatives` = ?,
         `executive_sponsor`  = ?,
         `status`             = ?,
         `complete`           = ?,
         `notes`              = ?,
         `priority`           = ?,
         `owner`              = ?,
         `deadline`           = ?
   WHERE `id` = ? AND `table_id` = ? AND `user_id` = ?
";
$stmt = $conn->prepare($sql);
/* 8 strings + 3 ints */
$stmt->bind_param(
  'ssssssssiii',
  $linked_initiatives, $executive_sponsor, $status, $complete, $notes,
  $priority, $owner, $deadlineDb,
  $id, $table_id, $uid
);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

/* ---------- response ---------- */
if (is_ajax()) {
  json_out([
    'ok'        => true,
    'affected'  => $affected,
    'deadline'  => $deadlineDb,   // handy for UI to reflect computed value
    'status'    => $status
  ]);
}

/* Non-AJAX fallback */
header("Location: /ItemPilot/home.php?autoload=1&type=dresses&table_id={$table_id}");
exit;
