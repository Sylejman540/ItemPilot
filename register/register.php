<?php

require_once __DIR__ . '/../db.php';

if (isset($_POST['signup'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $checkEmail = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($checkEmail);
    $stmt->bind_param('s', $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows>0){
        header("location: /ItemPilot/index.php?status=invalid_email");
        exit;
    }else{
        $insertInto = "INSERT INTO users(name, email, password) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insertInto);
        $stmt->bind_param('sss', $name, $email, $hash);

        if($stmt->execute()){
            header("location: /ItemPilot/home.php");
            exit;
        }else{
            echo "Error!";
        }
    }
}