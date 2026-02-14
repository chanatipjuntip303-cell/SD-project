<?php
session_start();
// ถ้ายังไม่ Login ให้ดีดกลับไปหน้า Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['user_role']; // ดึงตำแหน่งงานมาเช็ค
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>

<nav class="navbar">
    <a href="#" class="nav-brand">SD Project Shop</a>
    <div style="display:flex; align-items:center; gap:15px;">
        <span>👤 <?php echo $_SESSION['user_name']; ?> (<?php echo $role; ?>)</span>
        <a href="logout.php" class="btn btn-danger" style="padding:5px 10px; font-size:0.8em;">Logout</a>
    </div>
</nav>

<div class="container">
    <div class="card" style="text-align: center; margin-bottom: 30px;">
        <h1>Welcome, <?php echo $_SESSION['user_name']; ?>!</h1>
    </div>

    <div class="dashboard-grid">
        
        <?php if ($role == 'Manager' || $role == 'Sales' || $role == 'Cashier'): ?>
        <a href="create_order.php" class="menu-card">
            <i class='bx bxs-cart-add' style="font-size: 3rem; color: #2563eb;"></i>
            <h3>New Sale Order</h3>
        </a>
        <a href="manage_customers.php" class="menu-card">
            <i class='bx bxs-user-detail' style="font-size: 3rem; color: #ef4444;"></i>
            <h3>Customers</h3>
        </a>
        <a href="view_invoices.php" class="menu-card">
            <i class='bx bxs-file-doc' style="font-size: 3rem; color: #64748b;"></i>
            <h3>Invoice History</h3>
        </a>
        <?php endif; ?>

        <?php if ($role == 'Manager' || $role == 'Inventory Control'): ?>
        <a href="manage_products.php" class="menu-card">
            <i class='bx bxs-package' style="font-size: 3rem; color: #f59e0b;"></i>
            <h3>Inventory</h3>
        </a>
        <?php endif; ?>

        <?php if ($role == 'Manager'): ?>
        <a href="manage_employees.php" class="menu-card">
            <i class='bx bxs-user-badge' style="font-size: 3rem; color: #10b981;"></i>
            <h3>Employees</h3>
        </a>
        <?php endif; ?>

    </div>
</div>

</body>
</html>