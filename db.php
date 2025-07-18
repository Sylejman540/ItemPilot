<?php

$host = 'localhost';
$dbUser = 'root';
$password = '';
$dbname = "itempilot";

$conn = new mysqli($host, $dbUser, $password, $dbname);
if($conn->connect_error){
    echo "Error, please check it one more time";
}