<?php
session_start();
include 'db_connect.php';

// --- 1. ตรวจสอบสิทธิ์การเข้าถึง (Security) ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// อนุญาตให้ Manager, Sales และ Cashier จัดการลูกค้าได้ (คลังสินค้าห้ามเข้า)
if ($_SESSION['user_role'] == 'Inventory Control') {
    echo "<script>alert('Access Denied! Inventory staff cannot access customer data.'); window.location='index.php';</script>";
    exit();
}

$current_user_id = $_SESSION['user_id'];

// --- 2. Logic การจัดการข้อมูล (CRUD & Audit Trail) ---

// เพิ่มลูกค้าใหม่ (Create)
if (isset($_POST['add_customer'])) {
    $name = $_POST['contact_name'];
    $address = $_POST['address'];
    $level = $_POST['membership_level'];
    
    // บันทึก created_by อัตโนมัติจาก Session
    $sql = "INSERT INTO Customers (contact_name, address, membership_level, created_by) 
            VALUES ('$name', '$address', '$level', $current_user_id)";
    $conn->query($sql);
    header("Location: manage_customers.php");
    exit();
}

// อัปเดตข้อมูลลูกค้า (Update) - *เพิ่ม updated_by*
if (isset($_POST['update_customer'])) {
    $id = $_POST['customer_id'];
    $name = $_POST['contact_name'];
    $address = $_POST['address'];
    $level = $_POST['membership_level'];
    
    // บันทึก updated_by เพื่อ Track ว่าใครเป็นคนแก้ล่าสุด
    $conn->query("UPDATE Customers SET contact_name='$name', address='$address', membership_level='$level', updated_by=$current_user_id WHERE customer_id=$id");
    header("Location: manage_customers.php");
    exit();
}

// ย้ายลงถังขยะ (Soft Delete) - *เก็บประวัติคนลบด้วย*
if (isset($_GET['soft_delete'])) {
    $id = $_GET['soft_delete'];
    $conn->query("UPDATE Customers SET is_deleted = 1, updated_by=$current_user_id WHERE customer_id = $id");
    header("Location: manage_customers.php");
    exit();
}

// กู้คืน (Restore) - *เก็บประวัติคนกู้คืนด้วย*
if (isset($_GET['restore'])) {
    $id = $_GET['restore'];
    $conn->query("UPDATE Customers SET is_deleted = 0, updated_by=$current_user_id WHERE customer_id = $id");
    header("Location: manage_customers.php");
    exit();
}

// ลบถาวร (Permanent Delete)
if (isset($_GET['perm_delete'])) {
    $id = $_GET['perm_delete'];
    $conn->query("DELETE FROM Customers WHERE customer_id = $id");
    header("Location: manage_customers.php");
    exit();
}

// --- 3. ดึงข้อมูลมาแสดงผล (JOIN 2 รอบ เพื่อเอาชื่อคนสร้าง และคนแก้) ---
// Active Customers
$sql_active = "SELECT c.*, 
                      cr.employee_name AS creator_name, 
                      up.employee_name AS updater_name 
               FROM Customers c 
               LEFT JOIN Employees cr ON c.created_by = cr.employee_ID 
               LEFT JOIN Employees up ON c.updated_by = up.employee_ID 
               WHERE c.is_deleted = 0 
               ORDER BY c.customer_id DESC";
$active_customers = $conn->query($sql_active);

// Deleted Customers (Recycle Bin)
$sql_deleted = "SELECT c.*, 
                       cr.employee_name AS creator_name, 
                       up.employee_name AS updater_name 
                FROM Customers c 
                LEFT JOIN Employees cr ON c.created_by = cr.employee_ID 
                LEFT JOIN Employees up ON c.updated_by = up.employee_ID 
                WHERE c.is_deleted = 1 
                ORDER BY c.customer_id DESC";
$deleted_customers = $conn->query($sql_deleted);

// โหมดแก้ไข (Edit Mode)
$edit_mode = false;
$edit_data = ['customer_id'=>'','contact_name'=>'','address'=>'','membership_level'=>''];
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = $_GET['edit'];
    $edit_data = $conn->query("SELECT * FROM Customers WHERE customer_id = $id")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Management</title>
    <link rel="stylesheet" href="style.css"> 
    <style>
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .badge-premium { background: #fef08a; color: #854d0e; border: 1px solid #fde047; }
        .badge-standard { background: #e2e8f0; color: #475569; border: 1px solid #cbd5e1; }
        .audit-tag { font-size: 0.8em; color: #64748b; background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-block; margin-bottom: 2px;}
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand">SD Project Shop</a>
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="create_order.php">Sell</a>
        <a href="manage_customers.php" class="active">Customers</a>
    </div>
</nav>

<div class="container">
    
    <div class="card" style="border-top: 5px solid <?php echo $edit_mode ? '#f59e0b' : '#3b82f6'; ?>;">
        <h2><?php echo $edit_mode ? "✏️ Edit Customer" : "👤 Add New Customer"; ?></h2>
        <form method="POST">
            <input type="hidden" name="customer_id" value="<?php echo $edit_data['customer_id']; ?>">
            
            <div class="form-grid">
                <div>
                    <label>Full Name / Company:</label>
                    <input type="text" name="contact_name" placeholder="e.g. John Doe or ABC Co." value="<?php echo $edit_data['contact_name']; ?>" required>
                </div>
                <div>
                    <label>Address:</label>
                    <input type="text" name="address" placeholder="e.g. 123 Bangkok" value="<?php echo $edit_data['address']; ?>" required>
                </div>
                <div>
                    <label>Membership Level:</label>
                    <select name="membership_level" required>
                        <option value="Standard" <?php if($edit_data['membership_level'] == 'Standard') echo 'selected'; ?>>Standard (0%)</option>
                        <option value="Premium" <?php if($edit_data['membership_level'] == 'Premium') echo 'selected'; ?>>Premium (5%)</option>
                    </select>
                </div>
                <div>
                    <label><?php echo $edit_mode ? "Last Updated By:" : "Recorded By:"; ?></label>
                    <input type="text" value="<?php echo $_SESSION['user_name']; ?>" readonly style="background: #f1f5f9; color: #64748b; cursor: not-allowed;">
                </div>
            </div>

            <div style="margin-top: 15px;">
                <?php if($edit_mode): ?>
                    <button type="submit" name="update_customer" class="btn btn-warning">💾 Update Customer</button>
                    <a href="manage_customers.php" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <button type="submit" name="add_customer" class="btn btn-primary">➕ Save Customer</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>👥 Active Customers</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer Name</th>
                    <th>Address</th>
                    <th>Level</th>
                    <th>Audit Trail (Logs)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $active_customers->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['customer_id']; ?></td>
                    <td><strong><?php echo $row['contact_name']; ?></strong></td>
                    <td><?php echo $row['address']; ?></td>
                    <td>
                        <span class="badge <?php echo (strtolower($row['membership_level']) == 'premium') ? 'badge-premium' : 'badge-standard'; ?>">
                            <?php echo $row['membership_level']; ?>
                        </span>
                    </td>
                    <td>
                        <div class="audit-tag">✨ Created: <?php echo $row['creator_name'] ? $row['creator_name'] : '-'; ?></div><br>
                        <div class="audit-tag" style="color: #d97706; border-color: #fde68a; background: #fef3c7;">
                            ✏️ Edited: <?php echo $row['updater_name'] ? $row['updater_name'] : '-'; ?>
                        </div>
                    </td>
                    <td>
                        <a href="?edit=<?php echo $row['customer_id']; ?>" class="btn btn-warning" style="padding: 5px 10px; font-size: 0.8em;">✏️ Edit</a>
                        <a href="?soft_delete=<?php echo $row['customer_id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8em;" onclick="return confirm('Move to Trash?')">🗑️ Trash</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="border-top: 5px solid #ef4444;">
        <h3 style="color: #ef4444;">🗑️ Customer Recycle Bin</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Level</th>
                    <th>Deleted By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($deleted_customers->num_rows == 0): ?>
                    <tr><td colspan="5" style="text-align:center; color:#94a3b8;">Trash is empty</td></tr>
                <?php endif; ?>

                <?php while($row = $deleted_customers->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['customer_id']; ?></td>
                    <td><strike><?php echo $row['contact_name']; ?></strike></td>
                    <td><?php echo $row['membership_level']; ?></td>
                    <td><span class="audit-tag" style="color: #dc2626; border-color: #fca5a5; background: #fee2e2;">🗑️ By: <?php echo $row['updater_name'] ? $row['updater_name'] : '-'; ?></span></td>
                    <td>
                        <a href="?restore=<?php echo $row['customer_id']; ?>" class="btn btn-success" style="padding: 5px 10px; font-size: 0.8em;">♻️ Restore</a>
                        <a href="?perm_delete=<?php echo $row['customer_id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8em;" onclick="return confirm('Permanent Delete? Cannot be undone.')">❌ Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>