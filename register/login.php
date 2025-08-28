<?php
require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require __DIR__ . '/../vendor/autoload.php';

if (isset($_POST['login'])) {
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows === 1) {
        $user = $res->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Always require verification
            $verifyCode = rand(100000, 999999);

            $upd = $conn->prepare("UPDATE users SET verify_code=?, is_verified=0 WHERE id=?");
            $upd->bind_param("si", $verifyCode, $user['id']);
            $upd->execute();

            // Send mail
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'durgutisylejman00@gmail.com';   // Replace with your Gmail
                $mail->Password   = 'abqk vjfp qien siea';      // Replace with your Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('durgutisylejman00@gmail.com', 'ItemPilot');
                $mail->addAddress($email, $user['name']);
                $mail->isHTML(true);
                $mail->Subject = 'Login Verification Code';
                $mail->Body    = "<p>Your login code is:</p><h2>$verifyCode</h2>";
                $mail->send();
            } catch (Exception $e) {
                error_log("Mailer Error: {$mail->ErrorInfo}");
            }

            $_SESSION['verify_email'] = $email;
            header("Location: /ItemPilot/register/verify.php");
            exit;
        }
    }

    // Invalid login
    header("Location: /ItemPilot/index.php?status=invalid_data#login");
    exit;
}
