<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid      = (int)($_SESSION['user_id'] ?? 0);
$table_id = (int)($_POST['table_id'] ?? 0);
$thead_id = (int)($_POST['id'] ?? 0); // optional THEAD id from form

if ($uid <= 0 || $table_id <= 0) {
  http_response_code(400);
  exit('Missing context');
}

/* ---- Fixed THEAD labels (Football) ---- */
$photo         = trim($_POST['photo'] ?? '');
$full_name     = trim($_POST['full_name'] ?? '');
$position      = trim($_POST['position'] ?? '');
$home_address  = trim($_POST['home_address'] ?? '');
$email_address = trim($_POST['email_address'] ?? '');
$notes         = trim($_POST['notes'] ?? '');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  /* ---- Upsert football_thead ---- */
  if ($thead_id > 0) {
    $u = $conn->prepare("
      UPDATE football_thead
         SET photo=?, full_name=?, position=?, home_address=?, email_address=?, notes=?
       WHERE id=? AND table_id=? AND user_id=?
    ");
    $u->bind_param(
      'ssssssiii',
      $photo, $full_name, $position, $home_address, $email_address, $notes,
      $thead_id, $table_id, $uid
    );
    $u->execute();
    $u->close();
  } else {
    $i = $conn->prepare("
      INSERT INTO football_thead
        (photo, full_name, position, home_address, email_address, notes, user_id, table_id)
      VALUES (?,?,?,?,?,?,?,?)
    ");
    $i->bind_param(
      'ssssssii',
      $photo, $full_name, $position, $home_address, $email_address, $notes,
      $uid, $table_id
    );
    $i->execute();
    $i->close();
  }

  /* ---- Rename dynamic fields (metadata + base table column) ----
     Tables used:
       - football_fields (id, user_id, table_id, field_name)
       - football_base   (table_id, user_id, row_id, <dynamic columns...>)
  */

  // Load current field id -> name map
  $map = [];
  $s = $conn->prepare("
    SELECT id, field_name
      FROM football_fields
     WHERE user_id=? AND table_id=?
  ");
  $s->bind_param('ii', $uid, $table_id);
  $s->execute();
  $res = $s->get_result();
  while ($row = $res->fetch_assoc()) {
    $map[(int)$row['id']] = $row['field_name'];
  }
  $s->close();

  // Helper: fetch column type/nullability for an existing column in football_base
  $colType = $conn->prepare("
    SELECT COLUMN_TYPE, IS_NULLABLE
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'football_base'
       AND COLUMN_NAME  = ?
  ");

  // Prepared statement to update metadata name
  $updMeta = $conn->prepare("
    UPDATE football_fields
       SET field_name=?
     WHERE id=? AND table_id=? AND user_id=?
  ");

  foreach ($_POST as $k => $v) {
    if (!preg_match('/^extra_field_(\d+)$/', $k, $m)) continue;

    $fid     = (int)$m[1];
    $newName = trim((string)$v);
    $oldName = $map[$fid] ?? null;

    if ($oldName === null) continue;                 // unknown field id
    if ($newName === '' || $newName === $oldName) continue;

    // Validate identifier (simple whitelist) to keep ALTER safe
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $newName)) continue;

    // If old column exists in football_base, rename it to newName (when newName not yet taken)
    $colType->bind_param('s', $oldName);
    $colType->execute();
    $meta = $colType->get_result()->fetch_assoc();

    if ($meta) {
      // Ensure the new column name does not already exist
      $existsNew = $conn->prepare("
        SELECT 1
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'football_base'
           AND COLUMN_NAME  = ?
      ");
      $existsNew->bind_param('s', $newName);
      $existsNew->execute();
      $hasNew = (bool)$existsNew->get_result()->fetch_row();
      $existsNew->close();

      if (!$hasNew) {
        $type = $meta['COLUMN_TYPE'];                    // e.g. 'varchar(191)', 'text', etc.
        $null = ($meta['IS_NULLABLE'] === 'YES') ? 'NULL' : 'NOT NULL';

        // Perform the column rename
        $sql = sprintf(
          "ALTER TABLE `football_base` CHANGE COLUMN `%s` `%s` %s %s",
          $conn->real_escape_string($oldName),
          $conn->real_escape_string($newName),
          $type,
          $null
        );
        $conn->query($sql);
      }
    }

    // Update metadata name in football_fields
    $updMeta->bind_param('siii', $newName, $fid, $table_id, $uid);
    $updMeta->execute();
  }

  $updMeta->close();
  $colType->close();

  $conn->commit();
  header("Location: /ItemPilot/home.php?autoload=1&type=football&table_id={$table_id}");
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  exit('Save failed: ' . $e->getMessage());
}
