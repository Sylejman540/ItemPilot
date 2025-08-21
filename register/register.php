<?php
require_once __DIR__ . '/../db.php';

if (isset($_POST['signup'])) {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        header("location: /ItemPilot/index.php?status=missing_fields");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("location: /ItemPilot/index.php?status=invalid_format");
        exit;
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Check if email exists
    $checkEmail = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($checkEmail);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Email already taken
        header("location: /ItemPilot/index.php?status=invalid_email");
        exit;
    }

    // Insert new user
    $insertInto = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insertInto);
    $stmt->bind_param('sss', $name, $email, $hash);

    if ($stmt->execute()) {
        header("location: /ItemPilot/home.php");
        exit;
    } else {
        // Log error in production instead of showing
        die("Error inserting user: " . $stmt->error);
    }
}
