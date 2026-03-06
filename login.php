<?php
session_start();
include 'db_connect.php';

// ถ้า Login อยู่แล้ว ให้เด้งไปหน้า Dashboard เลย
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = "";

// ตรวจสอบเมื่อกดปุ่ม Login
if (isset($_POST['login_btn'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password']; // ในที่นี้ใช้ Plain Text ตาม SQL ที่เรา Insert ไป ('1234')

    // ดึงข้อมูลพนักงาน (เช็คด้วยว่ายังไม่โดนลบ is_deleted = 0)
    $sql = "SELECT * FROM Employees WHERE username = '$username' AND password = '$password' AND is_deleted = 0";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        
        // บันทึก Session
        $_SESSION['user_id'] = $row['employee_id'];
        $_SESSION['user_name'] = $row['employee_name'];
        $_SESSION['user_role'] = $row['role']; // สำคัญ! เอาไว้เช็คสิทธิ์ Manager/Sales/Inventory

        // เด้งไปหน้าแรก
        header("Location: index.php");
        exit();
    } else {
        $error = "❌ Username or Password incorrect!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - SD Project</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #e2e8f0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-card h1 { color: #2563eb; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; color: #64748b; }
        .form-group input { 
            width: 100%; padding: 10px; 
            border: 1px solid #cbd5e1; border-radius: 6px; 
            box-sizing: border-box; /* สำคัญ: ไม่ให้ input ล้นกรอบ */
        }
        .error-msg { color: #ef4444; background: #fee2e2; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="login-card">
    <h1>Element</h1>
    <p style="color: #64748b; margin-bottom: 30px;">Please sign in to continue</p>

    <?php if($error): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required autofocus placeholder="e.g. admin">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="e.g. 1234">
        </div>
        <button type="submit" name="login_btn" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 1rem;">Sign In</button>
    </form>
    
    <div style="margin-top: 20px; font-size: 0.8rem; color: #94a3b8;">
        Demo Accounts:<br>
        admin / 1234 (Manager)<br>
        sale / 1234 (Sales)<br>
        stock / 1234 (Inventory)
    </div>
</div>

</body>
</html>