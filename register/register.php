<?php
require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../vendor/autoload.php';

if (isset($_POST['signup'])) {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        header("Location: /ItemPilot/index.php?status=missing_fields");
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: /ItemPilot/index.php?status=invalid_format");
        exit;
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        header("Location: /ItemPilot/index.php?status=invalid_email");
        exit;
    }

    // Password hash
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Generate verification code
    $verifyCode = rand(100000, 999999);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (name,email,password,is_verified,verify_code) VALUES (?,?,?,?,?)");
    $zero = 0;
    $stmt->bind_param("sssis", $name, $email, $hash, $zero, $verifyCode);
    $stmt->execute();

    // Send verification email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'durgutisylejman00@gmail.com';
        $mail->Password   = 'abqk vjfp qien siea'; // ⚠️ Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('durgutisylejman00@gmail.com', 'ItemPilot');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Verify your ItemPilot Account';
        $mail->Body    = "<p>Your verification code is:</p><h2>$verifyCode</h2>";
        $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
    }

    $_SESSION['verify_email'] = $email;
    header("Location: /ItemPilot/register/verify.php");
    exit;
}
