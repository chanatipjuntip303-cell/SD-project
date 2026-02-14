<?php
$host = 'db'; // ชื่อ service ใน docker-compose
$user = 'root';
$pass = 'root_password';
$dbname = 'sale_system';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// เชื่อมต่อสำเร็จ!
?>