<?php
session_start();
include 'db_connect.php';

// 1. เช็ค Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- 2. ดึงข้อมูลสรุป (KPIs) ---

// 💡 2.1 คำนวณกำไรประจำเดือน (Monthly Profit)
$current_month = date('m');
$current_year = date('Y');

// ก. หายอดขายรวมของเดือนนี้ (เฉพาะบิลที่ไม่ได้ถูกลบและส่งของแล้ว)
$sql_rev = "SELECT SUM(net_total) as total_rev 
            FROM Orders 
            WHERE (status = 'Shipped' OR status = 'Completed') 
            AND is_deleted = 0 
            AND MONTH(order_date) = '$current_month' 
            AND YEAR(order_date) = '$current_year'";
$monthly_revenue = $conn->query($sql_rev)->fetch_assoc()['total_rev'] ?? 0;

// ข. หาต้นทุนรวมของเดือนนี้ (เอา Qty ที่ขายได้ * Cost ของสินค้า)
$sql_cost = "SELECT SUM(d.qty * p.cost) as total_cost 
             FROM Order_Details d 
             JOIN Orders o ON d.order_id = o.order_id 
             JOIN Products p ON d.product_id = p.product_id 
             WHERE (o.status = 'Shipped' OR o.status = 'Completed') 
             AND o.is_deleted = 0 
             AND MONTH(o.order_date) = '$current_month' 
             AND YEAR(o.order_date) = '$current_year'";
$monthly_cost = $conn->query($sql_cost)->fetch_assoc()['total_cost'] ?? 0;

// ค. คำนวณกำไร = ยอดขาย - ต้นทุน
$monthly_profit = $monthly_revenue - $monthly_cost;

// 2.2 ออเดอร์ค้างส่ง (ไม่รวมถังขยะ)
$sql_pending = "SELECT COUNT(*) as count FROM Orders WHERE status = 'Pending' AND is_deleted = 0";
$pending_count = $conn->query($sql_pending)->fetch_assoc()['count'] ?? 0;

// 2.3 สินค้าใกล้หมด (< 30)
$sql_stock = "SELECT COUNT(*) as count FROM Products WHERE stock_qty < 30 AND is_deleted = 0";
$low_stock = $conn->query($sql_stock)->fetch_assoc()['count'] ?? 0;

$emp_name = $_SESSION['user_name'] ?? 'Staff';
$role = $_SESSION['user_role'] ?? 'General';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - SD Project</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">SD Project System</div>
    <div class="nav-menu">
        <span style="margin-right: 15px; color: #64748b;"><?php echo $emp_name; ?> (<?php echo $role; ?>)</span>
        <a href="logout.php" style="color: #ef4444;">Logout</a>
    </div>
</nav>

<div class="container">
    <div style="margin-bottom: 30px;">
        <h1>Welcome back, <?php echo $emp_name; ?>!</h1>
        <p style="color: #64748b;">Here is your store overview for <?php echo date('F Y'); ?>.</p>
    </div>

    <div class="dashboard-grid">
        <div class="card" style="border-left: 5px solid #f59e0b;">
            <div class="stat-title">Pending Orders</div>
            <div class="stat-value" style="color: #f59e0b;"><?php echo $pending_count; ?></div>
            <small style="color: #64748b;">Waiting for shipment</small>
        </div>

        <div class="card" style="border-left: 5px solid #10b981;">
            <div class="stat-title">Monthly Profit</div>
            <div class="stat-value" style="color: #10b981;">฿<?php echo number_format($monthly_profit, 2); ?></div>
            <small style="color: #64748b;">Net profit for <?php echo date('M Y'); ?></small>
        </div>

        <div class="card" style="border-left: 5px solid #ef4444;">
            <div class="stat-title">Low Stock Items</div>
            <div class="stat-value" style="color: #ef4444;"><?php echo $low_stock; ?></div>
            <small style="color: #64748b;">Need restocking (< 30 items)</small>
        </div>
    </div>

    <h2 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 20px;">Sales & Operations</h2>

    <div class="dashboard-grid" style="grid-template-columns: repeat(4, 1fr);">
        <a href="pos_direct_sale.php" class="card" style="text-align: center; color: inherit; border: 2px solid #10b981;">
            <i class='bx bx-barcode-reader menu-icon' style="color: #10b981;"></i>
            <h3>POS / Direct Sale</h3>
            <p style="color: #64748b; font-size: 0.85rem;">ขายหน้าร้าน (ตัดสต็อกทันที)</p>
        </a>

        <a href="create_sale_order.php" class="card" style="text-align: center; color: inherit;">
            <i class='bx bxs-cart-add menu-icon' style="color: #2563eb;"></i>
            <h3>Create Sales Order</h3>
            <p style="color: #64748b; font-size: 0.85rem;">สั่งออเดอร์ (ยังไม่ตัดสต็อก)</p>
        </a>

        <a href="manage_orders.php" class="card" style="text-align: center; color: inherit;">
            <i class='bx bxs-package menu-icon' style="color: #f59e0b;"></i>
            <h3>Manage & Ship</h3>
            <p style="color: #64748b; font-size: 0.85rem;">จัดส่ง & ตัดสต็อก</p>
            <?php if($pending_count > 0): ?>
                <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;"><?php echo $pending_count; ?> Pending</span>
            <?php endif; ?>
        </a>

        <a href="view_invoices.php" class="card" style="text-align: center; color: inherit;">
            <i class='bx bxs-file-doc menu-icon' style="color: #8b5cf6;"></i>
            <h3>View Invoices</h3>
            <p style="color: #64748b; font-size: 0.85rem;">ดูประวัติใบเสร็จทั้งหมด</p>
        </a>
    </div>

    <h2 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 20px;">Master Data</h2>

    <div class="dashboard-grid" style="grid-template-columns: repeat(3, 1fr);">
        <a href="manage_products.php" class="card" style="text-align: center; color: inherit;">
            <i class='bx bxs-box menu-icon' style="color: #64748b;"></i>
            <h3>Products & Stock</h3>
            <p style="color: #64748b; font-size: 0.9rem;">จัดการสินค้า & เติมของ</p>
        </a>

        <a href="manage_customers.php" class="card" style="text-align: center; color: inherit;">
            <i class='bx bxs-user-detail menu-icon' style="color: #8b5cf6;"></i>
            <h3>Customers</h3>
            <p style="color: #64748b; font-size: 0.9rem;">จัดการข้อมูลลูกค้า</p>
        </a>

        <a href="manage_employees.php" class="card" style="text-align: center; color: inherit;">
            <i class='bx bxs-id-card menu-icon' style="color: #0ea5e9;"></i>
            <h3>Employees</h3>
            <p style="color: #64748b; font-size: 0.9rem;">จัดการบัญชีผู้ใช้งาน</p>
        </a>
    </div>
</div>

</body>
</html>