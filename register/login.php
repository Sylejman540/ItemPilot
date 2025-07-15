<?php
// register/login.php
require_once __DIR__ . '/../db.php';
session_start();

if (isset($_POST['login'])) {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    // 1) Fetch the user row by email
    $sql  = 'SELECT id, password FROM users WHERE email = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // 2) Verify the submitted password against the stored hash
        if (password_verify($password, $user['password'])) {
            // 3) Login successful → store user ID in session
            $_SESSION['user_id'] = $user['id'];

            header('Location: /ItemPilot/home.php');
            exit;
        }
    }

    // 4) On failure, redirect back to the login panel
    header('Location: /ItemPilot/index.php?status=invalid_data#login');
    exit;
}
