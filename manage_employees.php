<?php
session_start();
include 'db_connect.php';

// --- 1. ตรวจสอบสิทธิ์ (Security) ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// อนุญาตเฉพาะ Manager เท่านั้นที่จัดการพนักงานได้
if ($_SESSION['user_role'] != 'Manager') {
    echo "<script>alert('Access Denied! Only Managers can manage employees.'); window.location='index.php';</script>";
    exit();
}

$current_user_id = $_SESSION['user_id'];

// --- 2. Logic การจัดการข้อมูล (CRUD & Audit Trail) ---

// เพิ่มพนักงานใหม่ (Create)
if (isset($_POST['add_employee'])) {
    $name = $_POST['employee_name'];
    $pos = $_POST['position'];
    
    // บันทึก created_by อัตโนมัติ
    $conn->query("INSERT INTO Employees (employee_name, position, created_by) 
                  VALUES ('$name', '$pos', $current_user_id)");
    header("Location: manage_employees.php");
    exit();
}

// อัปเดตข้อมูลพนักงาน (Update) - *เพิ่มการบันทึก updated_by*
if (isset($_POST['update_employee'])) {
    $id = $_POST['employee_id'];
    $name = $_POST['employee_name'];
    $pos = $_POST['position'];
    
    // บันทึก updated_by เพื่อ Track ว่าใครเป็นคนแก้ล่าสุด
    $conn->query("UPDATE Employees SET employee_name='$name', position='$pos', updated_by=$current_user_id WHERE employee_ID=$id");
    header("Location: manage_employees.php");
    exit();
}

// ย้ายลงถังขยะ (Soft Delete)
if (isset($_GET['soft_delete'])) {
    $id = $_GET['soft_delete'];
    $conn->query("UPDATE Employees SET is_deleted = 1, updated_by=$current_user_id WHERE employee_ID = $id");
    header("Location: manage_employees.php");
    exit();
}

// กู้คืน (Restore)
if (isset($_GET['restore'])) {
    $id = $_GET['restore'];
    $conn->query("UPDATE Employees SET is_deleted = 0, updated_by=$current_user_id WHERE employee_ID = $id");
    header("Location: manage_employees.php");
    exit();
}

// ลบถาวร (Permanent Delete)
if (isset($_GET['perm_delete'])) {
    $id = $_GET['perm_delete'];
    $conn->query("DELETE FROM Employees WHERE employee_ID = $id");
    header("Location: manage_employees.php");
    exit();
}

// --- 3. ดึงข้อมูลมาแสดงผล (ใช้เทคนิค Self-Join) ---
// เราเชื่อมตาราง Employees กับตาราง Employees ด้วยกันเอง เพื่อเอาชื่อคนสร้างและคนแก้
$sql_active = "SELECT e.*, 
                      c.employee_name AS creator_name, 
                      u.employee_name AS updater_name 
               FROM Employees e 
               LEFT JOIN Employees c ON e.created_by = c.employee_ID 
               LEFT JOIN Employees u ON e.updated_by = u.employee_ID 
               WHERE e.is_deleted = 0 
               ORDER BY e.employee_ID DESC";
$active_employees = $conn->query($sql_active);

$sql_deleted = "SELECT e.*, 
                       c.employee_name AS creator_name, 
                       u.employee_name AS updater_name 
                FROM Employees e 
                LEFT JOIN Employees c ON e.created_by = c.employee_ID 
                LEFT JOIN Employees u ON e.updated_by = u.employee_ID 
                WHERE e.is_deleted = 1 
                ORDER BY e.employee_ID DESC";
$deleted_employees = $conn->query($sql_deleted);

// โหมดแก้ไข
$edit_mode = false;
$edit_data = ['employee_ID'=>'','employee_name'=>'','position'=>''];
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $id = $_GET['edit'];
    $edit_data = $conn->query("SELECT * FROM Employees WHERE employee_ID = $id")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Management</title>
    <link rel="stylesheet" href="style.css"> 
    <style>
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .badge-mgr { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-staff { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .audit-tag { font-size: 0.8em; color: #64748b; background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-block; margin-bottom: 2px;}
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand">SD Project Shop</a>
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="create_order.php">Sell</a>
        <a href="manage_employees.php" class="active">Employees</a>
    </div>
</nav>

<div class="container">
    
    <div class="card" style="border-top: 5px solid <?php echo $edit_mode ? '#f59e0b' : '#10b981'; ?>;">
        <h2><?php echo $edit_mode ? "✏️ Edit Employee" : "👨‍💼 Add New Employee"; ?></h2>
        <form method="POST">
            <input type="hidden" name="employee_id" value="<?php echo $edit_data['employee_ID']; ?>">
            
            <div class="form-grid">
                <div>
                    <label>Full Name:</label>
                    <input type="text" name="employee_name" placeholder="Firstname Lastname" value="<?php echo $edit_data['employee_name']; ?>" required>
                </div>
                <div>
                    <label>Position / Role:</label>
                    <select name="position" required>
                        <option value="">-- Select Role --</option>
                        <option value="Manager" <?php if($edit_data['position'] == 'Manager') echo 'selected'; ?>>Manager</option>
                        <option value="Sales" <?php if($edit_data['position'] == 'Sales') echo 'selected'; ?>>Sales</option>
                        <option value="Inventory Control" <?php if($edit_data['position'] == 'Inventory Control') echo 'selected'; ?>>Inventory Control</option>
                        <option value="Cashier" <?php if($edit_data['position'] == 'Cashier') echo 'selected'; ?>>Cashier</option>
                    </select>
                </div>
                <div>
                    <label><?php echo $edit_mode ? "Last Updated By:" : "Recorded By:"; ?></label>
                    <input type="text" value="<?php echo $_SESSION['user_name']; ?>" readonly style="background: #f1f5f9; color: #64748b; cursor: not-allowed;">
                </div>
            </div>

            <div style="margin-top: 15px;">
                <?php if($edit_mode): ?>
                    <button type="submit" name="update_employee" class="btn btn-warning">💾 Update Employee</button>
                    <a href="manage_employees.php" class="btn btn-secondary">Cancel</a>
                <?php else: ?>
                    <button type="submit" name="add_employee" class="btn btn-success">➕ Save Employee</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>👥 Active Employees</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Audit Trail (Logs)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $active_employees->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['employee_ID']; ?></td>
                    <td><strong><?php echo $row['employee_name']; ?></strong></td>
                    <td>
                        <span class="badge <?php echo ($row['position'] == 'Manager') ? 'badge-mgr' : 'badge-staff'; ?>">
                            <?php echo $row['position']; ?>
                        </span>
                    </td>
                    <td>
                        <div class="audit-tag">✨ Created: <?php echo $row['creator_name'] ? $row['creator_name'] : '-'; ?></div><br>
                        <div class="audit-tag" style="color: #d97706; border-color: #fde68a; background: #fef3c7;">
                            ✏️ Edited: <?php echo $row['updater_name'] ? $row['updater_name'] : '-'; ?>
                        </div>
                    </td>
                    <td>
                        <a href="?edit=<?php echo $row['employee_ID']; ?>" class="btn btn-warning" style="padding: 5px 10px; font-size: 0.8em;">✏️ Edit</a>
                        <?php if($row['employee_ID'] != $_SESSION['user_id']): ?>
                            <a href="?soft_delete=<?php echo $row['employee_ID']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8em;" onclick="return confirm('Move to Trash?')">🗑️ Trash</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="border-top: 5px solid #ef4444;">
        <h3 style="color: #ef4444;">🗑️ Recycle Bin</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Deleted By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($deleted_employees->num_rows == 0): ?>
                    <tr><td colspan="5" style="text-align:center; color:#94a3b8;">Trash is empty</td></tr>
                <?php endif; ?>

                <?php while($row = $deleted_employees->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['employee_ID']; ?></td>
                    <td><strike><?php echo $row['employee_name']; ?></strike></td>
                    <td><?php echo $row['position']; ?></td>
                    <td><span class="audit-tag" style="color: #dc2626; border-color: #fca5a5; background: #fee2e2;">🗑️ By: <?php echo $row['updater_name'] ? $row['updater_name'] : '-'; ?></span></td>
                    <td>
                        <a href="?restore=<?php echo $row['employee_ID']; ?>" class="btn btn-success" style="padding: 5px 10px; font-size: 0.8em;">♻️ Restore</a>
                        <a href="?perm_delete=<?php echo $row['employee_ID']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8em;" onclick="return confirm('Permanent Delete? Cannot be undone.')">❌ Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>