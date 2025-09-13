<?php
// /ItemPilot/categories/Universal Table/edit_thead.php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { http_response_code(401); exit('Unauthorized'); }

$table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
if (! $table_id) { http_response_code(400); exit('Missing or invalid table_id'); }

$thead_name       = trim($_POST['thead_name'] ?? '');
$thead_notes      = trim($_POST['thead_notes'] ?? '');
$thead_assignee   = trim($_POST['thead_assignee'] ?? '');
$thead_status     = trim($_POST['thead_status'] ?? '');
$thead_attachment = trim($_POST['thead_attachment'] ?? '');

function validate_field_name(string $s): bool {
  // MySQL identifier rules (simple, safe subset)
  return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $s);
}
function is_reserved_field(string $s): bool {
  static $r = ['id','user_id','table_id','row_id','created_at','updated_at'];
  return in_array(strtolower($s), $r, true);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  // 1) Snapshot the visible labels (like your other categories do)
  $ins = $conn->prepare("
    INSERT INTO universal_thead
      (thead_name, thead_notes, thead_assignee, thead_status, thead_attachment, user_id, table_id)
    VALUES (?,?,?,?,?,?,?)
  ");
  $ins->bind_param('ssssssi',
    $thead_name, $thead_notes, $thead_assignee, $thead_status, $thead_attachment,
    $uid, $table_id
  );
  $ins->execute();
  $ins->close();

  // 2) Fetch current mappings id -> field_name
  $m = $conn->prepare("SELECT id, field_name FROM universal_fields WHERE user_id=? AND table_id=?");
  $m->bind_param('ii', $uid, $table_id);
  $m->execute();
  $map = $m->get_result()->fetch_all(MYSQLI_ASSOC);
  $m->close();

  $byId = [];
  foreach ($map as $row) { $byId[(int)$row['id']] = $row['field_name']; }

  // 3) Prepare update stmt
  $upd = $conn->prepare("UPDATE universal_fields SET field_name=? WHERE id=? AND table_id=? AND user_id=?");

  // 4) Handle every THEAD "extra_field_{id}" input
  foreach ($_POST as $key => $val) {
    if (!preg_match('/^extra_field_(\d+)$/', $key, $mm)) continue;
    $fid    = (int)$mm[1];
    $newRaw = trim((string)$val);

    if (!isset($byId[$fid])) continue;        // not a known field row
    $old = $byId[$fid];
    $new = $newRaw;

    if ($new === '' || $new === $old) continue;
    if (!validate_field_name($new) || is_reserved_field($new)) continue;

    // deny duplicates
    $du = $conn->prepare("SELECT 1 FROM universal_fields WHERE user_id=? AND table_id=? AND field_name=? LIMIT 1");
    $du->bind_param('iis', $uid, $table_id, $new);
    $du->execute(); $hasDup = (bool)$du->get_result()->fetch_row(); $du->close();
    if ($hasDup) continue;

    // rename column in universal_base (only if old name looks valid)
    if (validate_field_name($old) && !is_reserved_field($old)) {
      $sql = "ALTER TABLE `universal_base` CHANGE COLUMN `{$old}` `{$new}` VARCHAR(255) NULL";
      $conn->query($sql);
    }

    // update mapping
    $upd->bind_param('siii', $new, $fid, $table_id, $uid);
    $upd->execute();
  }
  $upd->close();

  $conn->commit();

  // back to the universal page (fallback to referer)
  $dest = $_SERVER['HTTP_REFERER'] ?? "/ItemPilot/categories/Universal%20Table/insert_universal.php?table_id={$table_id}";
  header("Location: {$dest}");
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  exit('Save THEAD failed: '.$e->getMessage());
}
