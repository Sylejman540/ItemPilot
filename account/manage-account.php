<?php
require_once __DIR__ . '/../db.php';
session_start();

$uid = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
if (!$uid) {
  header('Location: /login.php'); exit;
}

function flash_redirect(string $msg, string $to = '/ItemPilot/home.php#account') {
  $_SESSION['flash'] = $msg;
  header("Location: {$to}");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  flash_redirect(''); // this endpoint handles POST only
}

/* ----- DELETE ACCOUNT ----- */
if (!empty($_POST['delete_account'])) {
  $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
  $stmt->bind_param('i', $uid);
  if ($stmt->execute()) {
    $stmt->close();
    session_unset(); session_destroy();
    header('Location: ItemPilot/index.php'); exit;
  }
  $err = $stmt->error; $stmt->close();
  flash_redirect('❌ Failed to delete account: ' . htmlspecialchars($err));
}

/* ----- UPDATE NAME (always allowed) ----- */
$name = trim($_POST['name'] ?? '');
if ($name === '') flash_redirect('❌ Name is required.');
$stmt = $conn->prepare("UPDATE users SET name = ? WHERE id = ?");
$stmt->bind_param('si', $name, $uid);
if (!$stmt->execute()) {
  $err = $stmt->error; $stmt->close();
  flash_redirect('❌ Update failed: ' . htmlspecialchars($err));
}
$stmt->close();
$_SESSION['name'] = $name;

/* ----- CHANGE PASSWORD (only if user filled fields) ----- */
$current = trim($_POST['current_password'] ?? '');
$new     = trim($_POST['new_password'] ?? '');
$confirm = trim($_POST['new_password_confirm'] ?? '');

if ($current !== '' || $new !== '' || $confirm !== '') {
  // All three must be provided
  if ($current === '' || $new === '' || $confirm === '') {
    flash_redirect('❌ To change password, fill Current, New, and Confirm fields.');
  }

  // Fetch hash
  $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if (!$row || empty($row['password'])) {
    flash_redirect('❌ Could not verify current password.');
  }

  // Verify current
  if (!password_verify($current, $row['password'])) {
    flash_redirect('❌ Current password is incorrect.');
  }

  // Validate new
  if ($new !== $confirm) {
    flash_redirect('❌ New password and confirmation do not match.');
  }
  if (strlen($new) < 8) {
    flash_redirect('❌ New password must be at least 8 characters.');
  }
  if (password_verify($new, $row['password'])) {
    flash_redirect('❌ New password must be different from the current password.');
  }

  // Update password
  $hashed = password_hash($new, PASSWORD_DEFAULT);
  $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
  $stmt->bind_param('si', $hashed, $uid);
  if (!$stmt->execute()) {
    $err = $stmt->error; $stmt->close();
    flash_redirect('❌ Could not update password: ' . htmlspecialchars($err));
  }
  $stmt->close();
}

/* ----- SUCCESS ----- */
flash_redirect('✅ Profile updated successfully!');
