<?php

$host = 'localhost';
$name = 'root';
$password = '';
$dbname = "itempilot";

$conn = new mysqli($host, $name, $password, $dbname);
if($conn->connect_error){
    echo "Error, please check it one more time";
}