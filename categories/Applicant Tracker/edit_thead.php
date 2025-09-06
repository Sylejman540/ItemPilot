<?php
require_once __DIR__ . '/../../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$uid = (int)($_SESSION['user_id'] ?? 0);
if ($uid <= 0) { http_response_code(401); exit('Unauthorized'); }

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

// require a table_id
if ($table_id <= 0) {
  http_response_code(400);
  exit('Missing or invalid table_id');
}

$sql = "INSERT INTO applicants_thead (name, stage, applying_for, attachment, email_address, phone, interview_date, interviewer, interview_score, notes, user_id, table_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) { http_response_code(500); exit('Prepare failed: ' . $conn->error); }

$stmt->bind_param('sssssssssii', $name, $stage, $applying_for, $attachment, $email_address, $phone, $interview_date, $interviewer, $interview_score, $notes, $uid, $table_id);

if (!$stmt->execute()) {
  http_response_code(500);
  exit('Insert failed: ' . $stmt->error);
}
$stmt->close();

header("Location: /ItemPilot/home.php?autoload=1&type=applicant&table_id={$table_id}");
exit;
