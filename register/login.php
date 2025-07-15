<?php

require_once __DIR__ . '/../db.php';

if(isset($_POST['login'])){
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql =  "SELECT * FROM users WHERE email = '$email' and password = '$password'";
    $result = $conn->query($sql);
    if($result->num_rows>0){
        header("location: /ItemPilot/home.php");
        exit;
    }else{
        header("location: /ItemPilot/index.php?status=invalid_data#login");
        exit;
    }
}