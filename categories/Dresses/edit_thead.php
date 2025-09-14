<?php
// /ItemPilot/categories/Dresses/edit_thead.php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid      = (int)($_SESSION['user_id'] ?? 0);
$table_id = (int)($_POST['table_id'] ?? 0);
$thead_id = (int)($_POST['id'] ?? 0); // hidden input in THEAD form

if ($uid <= 0)              { http_response_code(401); exit('Unauthorized'); }
if ($table_id <= 0)         { http_response_code(400); exit('Missing or invalid table_id'); }

// Fixed header labels
$linked_initiatives = trim($_POST['linked_initiatives'] ?? '');
$executive_sponsor  = trim($_POST['executive_sponsor']  ?? '');
$status             = trim($_POST['status']             ?? '');
$complete           = trim($_POST['complete']           ?? '');
$notes              = trim($_POST['notes']              ?? '');
$priority           = trim($_POST['priority']           ?? '');
$owner              = trim($_POST['owner']              ?? '');
$deadline           = trim($_POST['deadline']           ?? '');
$attachment         = trim($_POST['attachment']         ?? '');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  /* -------------------------------
     1) Upsert fixed THEAD labels
  ------------------------------- */
  if ($thead_id > 0) {
    $sql = "
      UPDATE dresses_thead
         SET linked_initiatives=?, executive_sponsor=?, status=?, complete=?, notes=?,
             priority=?, owner=?, deadline=?, attachment=?
       WHERE id=? AND table_id=? AND user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      'sssssssssiii',
      $linked_initiatives, $executive_sponsor, $status, $complete, $notes,
      $priority, $owner, $deadline, $attachment, $thead_id, $table_id, $uid
    );
    $stmt->execute();
    $stmt->close();
  } else {
    $sql = "
      INSERT INTO dresses_thead
        (linked_initiatives, executive_sponsor, status, complete, notes,
         priority, owner, deadline, attachment, user_id, table_id)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
      'sssssssssii',
      $linked_initiatives, $executive_sponsor, $status, $complete, $notes,
      $priority, $owner, $deadline, $attachment, $uid, $table_id
    );
    $stmt->execute();
    $thead_id = (int)$stmt->insert_id;
    $stmt->close();
  }

  /* ------------------------------------------------------------------
     2) Rename dynamic fields (dresses_fields + physical dresses_base)
         - expects inputs like:  <input name="extra_field_12" ...>
  ------------------------------------------------------------------ */

  // fetch current metadata names once
  $map = []; // id => field_name
  $s = $conn->prepare("SELECT id, field_name FROM dresses_fields WHERE user_id=? AND table_id=?");
  $s->bind_param('ii', $uid, $table_id);
  $s->execute();
  $res = $s->get_result();
  while ($row = $res->fetch_assoc()) {
    $map[(int)$row['id']] = $row['field_name'];
  }
  $s->close();

  // helpers
  $colType = $conn->prepare("
    SELECT COLUMN_TYPE, IS_NULLABLE
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'dresses_base'
       AND COLUMN_NAME  = ?
  ");
  $updMeta = $conn->prepare("
    UPDATE dresses_fields
       SET field_name=?
     WHERE id=? AND table_id=? AND user_id=?
  ");

  foreach ($_POST as $k => $v) {
    if (!preg_match('/^extra_field_(\d+)$/', $k, $m)) continue;

    $fid     = (int)$m[1];
    $newName = trim((string)$v);
    $oldName = $map[$fid] ?? null;

    if ($oldName === null) continue;                 // not a known field
    if ($newName === '' || $newName === $oldName) continue;

    // safe SQL identifier (MySQL limit 64 chars)
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $newName)) {
      // skip invalid identifiers silently; you can collect errors if you prefer
      continue;
    }

    // does old column exist in dresses_base?
    $colType->bind_param('s', $oldName);
    $colType->execute();
    $meta = $colType->get_result()->fetch_assoc();

    if ($meta) {
      // ensure there isn't already a column with the new name
      $existsNew = $conn->prepare("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'dresses_base'
           AND COLUMN_NAME  = ?
      ");
      $existsNew->bind_param('s', $newName);
      $existsNew->execute();
      $hasNew = (bool)$existsNew->get_result()->fetch_row();
      $existsNew->close();

      if (!$hasNew) {
        $type = $meta['COLUMN_TYPE'];                // e.g. 'varchar(191)', 'text', 'int(11)'
        $null = ($meta['IS_NULLABLE'] === 'YES') ? 'NULL' : 'NOT NULL';

        // identifiers already validated by regex; wrap in backticks
        $sql = "ALTER TABLE `dresses_base` CHANGE COLUMN `{$oldName}` `{$newName}` {$type} {$null}";
        $conn->query($sql);
      }
    }

    // update metadata name
    $updMeta->bind_param('siii', $newName, $fid, $table_id, $uid);
    $updMeta->execute();
  }

  $updMeta->close();
  $colType->close();

  $conn->commit();
  header("Location: /ItemPilot/home.php?autoload=1&type=dresses&table_id={$table_id}");
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  exit('Save failed: ' . $e->getMessage());
}
