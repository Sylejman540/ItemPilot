<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }

$uid      = (int)($_SESSION['user_id'] ?? 0);
$table_id = (int)($_POST['table_id'] ?? 0);
$thead_id = (int)($_POST['id'] ?? 0); // if you pass it from the form

if ($uid <= 0 || $table_id <= 0) { http_response_code(400); exit('Missing context'); }

// fixed labels
$name            = trim($_POST['name'] ?? '');
$stage           = trim($_POST['stage'] ?? '');
$applying_for    = trim($_POST['applying_for'] ?? '');
$attachment      = trim($_POST['attachment'] ?? '');
$email_address   = trim($_POST['email_address'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$interview_date  = trim($_POST['interview_date'] ?? '');
$interviewer     = trim($_POST['interviewer'] ?? '');
$interview_score = trim($_POST['interview_score'] ?? '');
$notes           = trim($_POST['notes'] ?? '');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
  // Upsert THEAD labels
  if ($thead_id > 0) {
    $u = $conn->prepare("
      UPDATE applicants_thead
         SET name=?, stage=?, applying_for=?, attachment=?, email_address=?, phone=?,
             interview_date=?, interviewer=?, interview_score=?, notes=?
       WHERE id=? AND table_id=? AND user_id=?");
    $u->bind_param('ssssssssssiii',
      $name, $stage, $applying_for, $attachment, $email_address, $phone,
      $interview_date, $interviewer, $interview_score, $notes,
      $thead_id, $table_id, $uid
    );
    $u->execute(); $u->close();
  } else {
    $i = $conn->prepare("
      INSERT INTO applicants_thead
        (name, stage, applying_for, attachment, email_address, phone,
         interview_date, interviewer, interview_score, notes, user_id, table_id)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $i->bind_param('ssssssssssii',
      $name, $stage, $applying_for, $attachment, $email_address, $phone,
      $interview_date, $interviewer, $interview_score, $notes, $uid, $table_id
    );
    $i->execute(); $i->close();
  }

  /* ---- Rename dynamic fields (metadata + base table column) ---- */

  // Load current names once
  $map = [];
  $s = $conn->prepare("SELECT id, field_name FROM applicants_fields WHERE user_id=? AND table_id=?");
  $s->bind_param('ii', $uid, $table_id);
  $s->execute();
  $res = $s->get_result();
  while ($row = $res->fetch_assoc()) $map[(int)$row['id']] = $row['field_name'];
  $s->close();

  // Helper to get column type so we can ALTER properly
  $colType = $conn->prepare("
    SELECT COLUMN_TYPE, IS_NULLABLE
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='applicants_base' AND COLUMN_NAME=?");

  $updMeta = $conn->prepare("UPDATE applicants_fields SET field_name=? WHERE id=? AND table_id=? AND user_id=?");

  foreach ($_POST as $k => $v) {
    if (!preg_match('/^extra_field_(\d+)$/', $k, $m)) continue;
    $fid      = (int)$m[1];
    $newName  = trim((string)$v);
    $oldName  = $map[$fid] ?? null;

    if ($oldName === null) continue;            // not our field
    if ($newName === '' || $newName === $oldName) continue;

    // Basic identifier validation to avoid SQL injection in ALTER
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $newName)) continue;

    // If the base table has the old column, rename it to the new one (and only if the new one doesn't exist).
    $colType->bind_param('s', $oldName);
    $colType->execute();
    $meta = $colType->get_result()->fetch_assoc();

    if ($meta) {
      // Ensure no existing column with the new name
      $existsNew = $conn->prepare("
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='applicants_base' AND COLUMN_NAME=?");
      $existsNew->bind_param('s', $newName);
      $existsNew->execute();
      $hasNew = (bool)$existsNew->get_result()->fetch_row();
      $existsNew->close();

      if (!$hasNew) {
        $type = $meta['COLUMN_TYPE'];                 // e.g. 'text', 'varchar(191)', etc.
        $null = ($meta['IS_NULLABLE'] === 'YES') ? 'NULL' : 'NOT NULL';
        $sql  = sprintf(
          "ALTER TABLE `applicants_base` CHANGE COLUMN `%s` `%s` %s %s",
          $conn->real_escape_string($oldName),
          $conn->real_escape_string($newName),
          $type,
          $null
        );
        $conn->query($sql);
      }
    }

    // Update metadata name
    $updMeta->bind_param('siii', $newName, $fid, $table_id, $uid);
    $updMeta->execute();
  }

  $updMeta->close();
  $colType->close();

  $conn->commit();
  header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
  exit;

} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  exit('Save failed: '.$e->getMessage());
}
