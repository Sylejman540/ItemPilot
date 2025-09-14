<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid      = (int)($_SESSION['user_id'] ?? 0);
$table_id = (int)($_POST['table_id'] ?? 0);
$thead_id = (int)($_POST['id'] ?? 0); // optional hidden input in your form

if ($uid <= 0 || $table_id <= 0) {
  http_response_code(400);
  exit('Missing user or table context');
}

/* -------- fixed label fields (head) -------- */
$photo        = trim($_POST['photo'] ?? '');
$brandFlavor  = trim($_POST['brand_flavor'] ?? '');
$quantity     = trim($_POST['quantity'] ?? '');
$department   = trim($_POST['department'] ?? '');
$purchased    = trim($_POST['purchased'] ?? '');
$notes        = trim($_POST['notes'] ?? '');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  /* -------------------------------
     1) Upsert groceries_head labels
     ------------------------------- */
  if ($thead_id > 0) {
    $u = $conn->prepare("
      UPDATE groceries_head
         SET photo=?, brand_flavor=?, quantity=?, department=?, purchased=?, notes=?
       WHERE id=? AND table_id=? AND user_id=?
    ");
    $u->bind_param(
      'ssssssiii',
      $photo, $brandFlavor, $quantity, $department, $purchased, $notes,
      $thead_id, $table_id, $uid
    );
    $u->execute(); $u->close();
  } else {
    $i = $conn->prepare("
      INSERT INTO groceries_head
        (photo, brand_flavor, quantity, department, purchased, notes, user_id, table_id)
      VALUES (?,?,?,?,?,?,?,?)
    ");
    $i->bind_param(
      'ssssssii',
      $photo, $brandFlavor, $quantity, $department, $purchased, $notes,
      $uid, $table_id
    );
    $i->execute(); $i->close();
  }

  /* ---------------------------------------------------------
     2) Rename dynamic fields (groceries_fields + groceries_base)
        - Look for inputs named extra_field_{id}
        - Validate new identifier
        - ALTER TABLE groceries_base CHANGE COLUMN old -> new
     --------------------------------------------------------- */

  // Load current metadata: id -> field_name
  $map = [];
  $s = $conn->prepare("
    SELECT id, field_name
      FROM groceries_fields
     WHERE user_id=? AND table_id=?
  ");
  $s->bind_param('ii', $uid, $table_id);
  $s->execute();
  $res = $s->get_result();
  while ($row = $res->fetch_assoc()) {
    $map[(int)$row['id']] = $row['field_name'];
  }
  $s->close();

  // Helper: fetch existing column definition from groceries_base
  $colInfo = $conn->prepare("
    SELECT COLUMN_TYPE, IS_NULLABLE
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'groceries_base'
       AND COLUMN_NAME  = ?
  ");

  // Update metadata name
  $updMeta = $conn->prepare("
    UPDATE groceries_fields
       SET field_name=?
     WHERE id=? AND table_id=? AND user_id=?
  ");

  foreach ($_POST as $key => $val) {
    if (!preg_match('/^extra_field_(\d+)$/', $key, $m)) continue;

    $fid     = (int)$m[1];
    $oldName = $map[$fid] ?? null;
    $newName = trim((string)$val);

    if ($oldName === null)            continue;       // not our field
    if ($newName === '' )             continue;       // empty -> ignore
    if ($newName === $oldName)        continue;       // unchanged

    // Basic identifier validation to avoid SQL injection in ALTER
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $newName)) continue;

    // If groceries_base has the old column, and not the new one, rename it
    $colInfo->bind_param('s', $oldName);
    $colInfo->execute();
    $meta = $colInfo->get_result()->fetch_assoc();

    if ($meta) {
      // Ensure no existing column with the new name
      $existsNew = $conn->prepare("
        SELECT 1
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'groceries_base'
           AND COLUMN_NAME  = ?
      ");
      $existsNew->bind_param('s', $newName);
      $existsNew->execute();
      $hasNew = (bool)$existsNew->get_result()->fetch_row();
      $existsNew->close();

      if (!$hasNew) {
        $type = $meta['COLUMN_TYPE'];                  // e.g. varchar(191) / text / int(11) ...
        $null = ($meta['IS_NULLABLE'] === 'YES') ? 'NULL' : 'NOT NULL';
        // Use quoted identifiers and real_escape_string for safety
        $sql = sprintf(
          "ALTER TABLE `groceries_base` CHANGE COLUMN `%s` `%s` %s %s",
          $conn->real_escape_string($oldName),
          $conn->real_escape_string($newName),
          $type,
          $null
        );
        $conn->query($sql);
      }
    }

    // Update the metadata record
    $updMeta->bind_param('siii', $newName, $fid, $table_id, $uid);
    $updMeta->execute();
  }

  $updMeta->close();
  $colInfo->close();

  $conn->commit();
  header("Location: /ItemPilot/home.php?autoload=1&type=groceries&table_id={$table_id}");
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  exit('Save failed: ' . $e->getMessage());
}
