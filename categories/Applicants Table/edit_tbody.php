<?php
require_once __DIR__ . '/../../db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  die("No valid ID provided");
}

// 1) If this is a POST, run the UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name               = trim($_POST['name'] ?? '');
  $stage              = trim($_POST['stage'] ?? '');
  $applying_for       = trim($_POST['applying_for'] ?? '');
  $attachment         = trim($_POST['attachment'] ?? '');
  $email_address      = trim($_POST['email_address'] ?? '');
  $phone              = trim($_POST['phone'] ?? '');
  $interview_date     = trim($_POST['interview_date'] ?? '');
  $interviewer        = trim($_POST['interviewer'] ?? '');
  $interview_score    = trim($_POST['interview_score'] ?? '');
  $notes              = trim($_POST['notes'] ?? '');
  $table_id           = trim($_POST['table_id'] ?? '');
  $table_id = $_POST['table_id'] ?? 0;

  $sql = "UPDATE applicants SET name = ?, stage = ?, applying_for = ?, attachment = ?, email_address = ?, phone = ?, interview_date = ?, interviewer = ?, interview_score = ?, notes = ? WHERE id = ? AND table_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('ssssssssssii', $name, $stage, $applying_for, $attachment, $email_address, $phone, $interview_date, $interviewer, $interview_score, $notes, $id, $table_id);
  if ($stmt->execute()) {
  header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
  exit;
  }else {
    die("Update failed: " . $stmt->error);
  }
}


$stmt = $conn->prepare("SELECT name, stage, applying_for, attachment, email_address, phone, interview_date, interviewer, interview_score, notes FROM applicants WHERE id = ? AND table_id = ?");
$stmt->bind_param('ii', $id, $table_id);
$stmt->execute();
$stmt->bind_result( $name, $stage, $applying_for, $attachment, $email_address, $phone, $interview_date, $interviewer, $interview_score, $notes);
if (! $stmt->fetch()) {
  die("Record #{$id} not found");
}
$stmt->close();

?>
