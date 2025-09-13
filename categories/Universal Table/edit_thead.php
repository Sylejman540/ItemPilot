<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid      = (int)($_SESSION['user_id'] ?? 0);
$table_id = (int)($_POST['table_id'] ?? 0);
$thead_id = (int)($_POST['id'] ?? 0); // hidden input from your form

if ($uid <= 0 || $table_id <= 0) {
  http_response_code(400);
  exit('Missing or invalid user/table');
}

/* fixed labels from THEAD */
$thead_name       = trim($_POST['thead_name'] ?? '');
$thead_notes      = trim($_POST['thead_notes'] ?? '');
$thead_assignee   = trim($_POST['thead_assignee'] ?? '');
$thead_status     = trim($_POST['thead_status'] ?? '');
$thead_attachment = trim($_POST['thead_attachment'] ?? '');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // throw on SQL errors
$conn->begin_transaction();

try {
  /* ---- 1) Upsert the header labels ---- */
  if ($thead_id > 0) {
    $u = $conn->prepare("
      UPDATE universal_thead
         SET thead_name=?, thead_notes=?, thead_assignee=?, thead_status=?, thead_attachment=?
       WHERE id=? AND table_id=? AND user_id=?");
    $u->bind_param(
      'ssssiiii',
      $thead_name, $thead_notes, $thead_assignee, $thead_status, $thead_attachment,
      $thead_id, $table_id, $uid
    );
    $u->execute();
    $u->close();
  } else {
    $i = $conn->prepare("
      INSERT INTO universal_thead
        (thead_name, thead_notes, thead_assignee, thead_status, thead_attachment, user_id, table_id)
      VALUES (?,?,?,?,?,?,?)");
    $i->bind_param('sssssii',
      $thead_name, $thead_notes, $thead_assignee, $thead_status, $thead_attachment,
      $uid, $table_id
    );
    $i->execute();
    $i->close();
  }

  /* ---- 2) Load current dynamic field names (universal_fields) ---- */
  $map = [];
  $s = $conn->prepare("
    SELECT id, field_name
      FROM universal_fields
     WHERE user_id=? AND table_id=?
     ORDER BY id ASC
  ");
  $s->bind_param('ii', $uid, $table_id);
  $s->execute();
  $res = $s->get_result();
  while ($row = $res->fetch_assoc()) {
    $map[(int)$row['id']] = $row['field_name'];
  }
  $s->close();

  /* Helpers for safe column rename in universal_base */
  $colMeta = $conn->prepare("
    SELECT COLUMN_TYPE, IS_NULLABLE
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'universal_base'
       AND COLUMN_NAME  = ?
  ");

  $colExists = $conn->prepare("
    SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'universal_base'
       AND COLUMN_NAME  = ?
  ");

  $updMeta = $conn->prepare("
    UPDATE universal_fields
       SET field_name = ?
     WHERE id = ? AND table_id = ? AND user_id = ?
  ");

  /* ---- 3) For every extra_field_<id> from the THEAD form, rename if needed ---- */
  foreach ($_POST as $k => $v) {
    if (!preg_match('/^extra_field_(\d+)$/', $k, $m)) continue;

    $fid     = (int)$m[1];
    $newName = trim((string)$v);
    $oldName = $map[$fid] ?? null;

    if ($oldName === null) continue;            // unknown field id
    if ($newName === '' || $newName === $oldName) continue;

    // Basic identifier validation (MySQL-safe unquoted identifier)
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $newName)) {
      // Skip invalid names silently (or set a flash if you prefer)
      continue;
    }

    // If a column with the old name exists in universal_base, rename it (preserving type & nullability)
    $colMeta->bind_param('s', $oldName);
    $colMeta->execute();
    $meta = $colMeta->get_result()->fetch_assoc();

    if ($meta) {
      // Don't collide with an existing column of the new name
      $colExists->bind_param('s', $newName);
      $colExists->execute();
      $hasNew = (bool)$colExists->get_result()->fetch_row();

      if (!$hasNew) {
        $type = $meta['COLUMN_TYPE'];                 // e.g. 'text', 'varchar(191)', 'int(11)'
        $null = ($meta['IS_NULLABLE'] === 'YES') ? 'NULL' : 'NOT NULL';

        // ALTER TABLE ... CHANGE COLUMN old new TYPE NULL/NOT NULL
        $sql = sprintf(
          "ALTER TABLE `universal_base` CHANGE COLUMN `%s` `%s` %s %s",
          $conn->real_escape_string($oldName),
          $conn->real_escape_string($newName),
          $type,
          $null
        );
        $conn->query($sql);
      }
    }

    // Update the metadata name in universal_fields
    $updMeta->bind_param('siii', $newName, $fid, $table_id, $uid);
    $updMeta->execute();
  }

  $updMeta->close();
  $colMeta->close();
  $colExists->close();

  $conn->commit();

  header("Location: /ItemPilot/home.php?autoload=1&table_id={$table_id}");
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  exit('DB error: ' . $e->getMessage());
}
