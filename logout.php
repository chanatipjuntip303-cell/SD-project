<?php
session_start();
session_destroy(); // ล้างข้อมูลทั้งหมด
header("Location: login.php"); // ส่งกลับไปหน้า Login
exit();
?>