<?php
session_start();
include 'db_connect.php';

if (isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // เช็คข้อมูลในฐานข้อมูล
    $sql = "SELECT * FROM Employees WHERE username = '$user' AND password = '$pass' AND is_deleted = 0";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // เก็บข้อมูลคน Login ลง Session
        $_SESSION['user_id'] = $row['employee_ID'];
        $_SESSION['user_name'] = $row['employee_name'];
        $_SESSION['user_role'] = $row['position']; // เก็บตำแหน่งงานไว้เช็คสิทธิ์

        header("Location: index.php"); // ส่งไปหน้า Dashboard
    } else {
        $error = "Incorrect Username or Password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; height: 100vh; background: #e2e8f0; }
        .login-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 350px; text-align: center; }
        input { margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2 style="color: #2563eb;">🔐 Staff Login</h2>
        <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login" class="btn btn-primary" style="width:100%;">Login</button>
        </form>
    </div>
</body>
</html>