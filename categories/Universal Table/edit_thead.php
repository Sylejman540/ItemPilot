<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { http_response_code(401); exit('Unauthorized'); }

$table_id = filter_input(INPUT_POST, 'table_id', FILTER_VALIDATE_INT);
if (!$table_id) { http_response_code(400); exit('Missing or invalid table_id'); }

// optional: save current thead labels (keep your fields/ids)
$thead_name       = trim($_POST['thead_name'] ?? '');
$thead_notes      = trim($_POST['thead_notes'] ?? '');
$thead_assignee   = trim($_POST['thead_assignee'] ?? '');
$thead_status     = trim($_POST['thead_status'] ?? '');
$thead_attachment = trim($_POST['thead_attachment'] ?? '');

// helper: make a safe SQL identifier (col name)
$makeIdent = function(string $s): string {
  // spaces -> _, strip non [A-Za-z0-9_], ensure doesn’t start with digit
  $s = preg_replace('/\s+/', '_', $s);
  $s = preg_replace('/[^A-Za-z0-9_]/', '', $s);
  if ($s === '' || ctype_digit($s[0])) $s = '_' . $s;
  return substr($s, 0, 64); // MySQL identifier limit
};

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();
try {
  // 1) record thead labels (like before)
  $stmt = $conn->prepare("
    INSERT INTO universal_thead
      (thead_name, thead_notes, thead_assignee, thead_status, thead_attachment, user_id, table_id)
    VALUES (?,?,?,?,?,?,?)
  ");
  $stmt->bind_param('sssssii', $thead_name, $thead_notes, $thead_assignee, $thead_status, $thead_attachment, $uid, $table_id);
  $stmt->execute();
  $stmt->close();

  // 2) load current universal_fields map (id => old_name)
  $map = [];
  $stmt = $conn->prepare("SELECT id, field_name FROM universal_fields WHERE user_id=? AND table_id=? ORDER BY id ASC");
  $stmt->bind_param('ii', $uid, $table_id);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $map[(int)$row['id']] = $row['field_name'];
  }
  $stmt->close();

  // 3) current columns in universal_base
  $cols = [];
  $q = $conn->query("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'universal_base'
  ");
  foreach ($q->fetch_all(MYSQLI_ASSOC) as $c) { $cols[$c['COLUMN_NAME']] = true; }

  // 4) prepare statements we’ll reuse
  $updField = $conn->prepare("UPDATE universal_fields SET field_name=? WHERE id=? AND table_id=? AND user_id=?");

  // 5) walk POSTed extra_field_* and add/rename columns as needed
  foreach ($_POST as $key => $val) {
    if (!preg_match('/^extra_field_(\d+)$/', $key, $m)) continue;
    $fid     = (int)$m[1];
    if (!array_key_exists($fid, $map)) continue;

    $oldRaw  = (string)$map[$fid];
    $newRaw  = trim((string)$val);

    // sanitize to valid identifiers
    $old = $makeIdent($oldRaw);
    $new = $makeIdent($newRaw);

    if ($new === '') continue; // skip empty

    // if name didn’t change (after sanitize), just ensure the column exists
    if ($old === $new) {
      if (!isset($cols[$new])) {
        $conn->query("ALTER TABLE `universal_base` ADD COLUMN `{$new}` TEXT NULL");
        $cols[$new] = true;
      }
    } else {
      // name changed
      $oldExists = isset($cols[$old]);
      $newExists = isset($cols[$new]);

      if ($oldExists && !$newExists) {
        // rename column
        $conn->query("ALTER TABLE `universal_base` CHANGE COLUMN `{$old}` `{$new}` TEXT NULL");
        unset($cols[$old]); $cols[$new] = true;
      } elseif (!$oldExists && !$newExists) {
        // brand new column
        $conn->query("ALTER TABLE `universal_base` ADD COLUMN `{$new}` TEXT NULL");
        $cols[$new] = true;
      }
      // if $new already exists, we won’t drop/merge here; we’ll just re-map the field.
    }

    // update the field mapping to the (sanitized) $new
    $updField->bind_param('siii', $new, $fid, $table_id, $uid);
    $updField->execute();
  }
  $updField->close();

  $conn->commit();
  header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo 'DB error: ' . $e->getMessage();
  exit;
}
