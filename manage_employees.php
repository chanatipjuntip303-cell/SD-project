<?php
session_start();
include 'db_connect.php';

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// สำคัญ: เฉพาะ Manager เท่านั้นที่จัดการพนักงานได้!
if ($_SESSION['user_role'] != 'Manager') {
    echo "<script>alert('Access Denied! Only Managers can manage employees.'); window.location='index.php';</script>";
    exit();
}

$uid = $_SESSION['user_id'];

// --- 2. Action Logic ---

// 2.1 เพิ่มพนักงาน (Add)
if (isset($_POST['add_employee'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $user = $conn->real_escape_string($_POST['username']);
    $pass = $_POST['password']; // ในระบบจริงควรใช้ password_hash()
    $role = $_POST['role'];

    // เช็คว่า Username ซ้ำไหม
    $check = $conn->query("SELECT * FROM Employees WHERE username = '$user'");
    if ($check->num_rows > 0) {
        echo "<script>alert('Username already exists!');</script>";
    } else {
        $sql = "INSERT INTO Employees (employee_name, username, password, role, created_by) 
                VALUES ('$name', '$user', '$pass', '$role', $uid)";
        $conn->query($sql);
        header("Location: manage_employees.php");
    }
}

// 2.2 แก้ไขพนักงาน (Edit)
if (isset($_POST['edit_employee'])) {
    $eid = $_POST['employee_id'];
    $name = $conn->real_escape_string($_POST['name']);
    $user = $conn->real_escape_string($_POST['username']);
    $pass = $_POST['password'];
    $role = $_POST['role'];

    $sql = "UPDATE Employees SET employee_name='$name', username='$user', password='$pass', role='$role' WHERE employee_id=$eid";
    $conn->query($sql);
    header("Location: manage_employees.php");
}

// 2.3 ลบพนักงาน (Soft Delete)
if (isset($_GET['delete_id'])) {
    $eid = $_GET['delete_id'];
    
    // ป้องกันการลบตัวเอง และลบ Admin หลัก
    if ($eid == $uid) {
        echo "<script>alert('You cannot delete yourself!'); window.location='manage_employees.php';</script>";
    } elseif ($eid == 1) {
        echo "<script>alert('Cannot delete the Main Admin!'); window.location='manage_employees.php';</script>";
    } else {
        $conn->query("UPDATE Employees SET is_deleted = 1, deleted_by = $uid, deleted_at = NOW() WHERE employee_id = $eid");
        header("Location: manage_employees.php");
    }
}

// 2.4 กู้คืน (Restore)
if (isset($_GET['restore_id'])) {
    $eid = $_GET['restore_id'];
    $conn->query("UPDATE Employees SET is_deleted = 0 WHERE employee_id = $eid");
    header("Location: manage_employees.php");
}

// 2.5 ลบถาวร (Permanent Delete)
if (isset($_GET['perm_del_id'])) {
    $eid = $_GET['perm_del_id'];
    if ($eid == 1 || $eid == $uid) {
        echo "<script>alert('Action not allowed!'); window.location='manage_employees.php';</script>";
    } else {
        $conn->query("DELETE FROM Employees WHERE employee_id = $eid");
        header("Location: manage_employees.php");
    }
}

// --- 3. Fetch Data ---
// e1 คือตัวพนักงานเอง, e2 คือคนที่สร้างพนักงานคนนี้ขึ้นมา
$employees = $conn->query("SELECT e1.*, e2.employee_name as creator_name 
                           FROM Employees e1 
                           LEFT JOIN Employees e2 ON e1.created_by = e2.employee_id 
                           WHERE e1.is_deleted = 0 
                           ORDER BY e1.employee_id ASC");

$recent = $conn->query("SELECT e1.*, e2.employee_name as creator_name 
                        FROM Employees e1 
                        LEFT JOIN Employees e2 ON e1.created_by = e2.employee_id 
                        WHERE e1.is_deleted = 0 
                        ORDER BY e1.created_at DESC LIMIT 5");

$trash = $conn->query("SELECT e1.*, e2.employee_name as deleter_name 
                       FROM Employees e1 
                       LEFT JOIN Employees e2 ON e1.deleted_by = e2.employee_id 
                       WHERE e1.is_deleted = 1 
                       ORDER BY e1.deleted_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Employees</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .badge-mgr { background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .badge-sale { background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .badge-inv { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .audit-tag { font-size: 0.75em; color: #dc2626; background: #fee2e2; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 2px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; width: 400px; margin: 80px auto; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand">SD Project</a>
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="manage_employees.php" class="active">Employees</a>
    </div>
</nav>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>👔 Employee Management</h1>
        <button onclick="openModal('addModal')" class="btn btn-primary">+ New Employee</button>
    </div>

    <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
        <div class="card">
            <h3>All Staff</h3>
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Role</th><th>Login Info</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php while($e = $employees->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $e['employee_id']; ?></td>
                        <td>
                            <strong><?php echo $e['employee_name']; ?></strong>
                            <?php if($e['employee_id'] == $uid) echo "<small style='color:#10b981;'><br>(You)</small>"; ?>
                        </td>
                        <td>
                            <?php 
                                if($e['role'] == 'Manager') echo '<span class="badge-mgr">Manager</span>';
                                elseif($e['role'] == 'Sales') echo '<span class="badge-sale">Sales</span>';
                                else echo '<span class="badge-inv">Inventory</span>';
                            ?>
                        </td>
                        <td style="font-size: 0.9em; color: #64748b;">
                            User: <strong><?php echo $e['username']; ?></strong><br>
                            Pass: <?php echo $e['password']; ?>
                        </td>
                        <td>
                            <button onclick="openEdit(<?php echo htmlspecialchars(json_encode($e)); ?>)" class="btn btn-warning" style="font-size:0.8em;">Edit</button>
                            <?php if($e['employee_id'] != 1 && $e['employee_id'] != $uid): ?>
                                <a href="?delete_id=<?php echo $e['employee_id']; ?>" class="btn btn-danger" style="font-size:0.8em;" onclick="return confirm('Disable this account?')">Del</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>🕒 Recently Added</h3>
            <ul style="list-style: none; padding: 0;">
                <?php while($r = $recent->fetch_assoc()): ?>
                <li style="border-bottom: 1px solid #e2e8f0; padding: 10px 0;">
                    <div style="display: flex; justify-content: space-between;">
                        <strong><?php echo $r['employee_name']; ?></strong>
                        <span style="font-size:0.8em;"><?php echo $r['role']; ?></span>
                    </div>
                    <div style="font-size: 0.8em; color: #64748b;">Added by: <?php echo $r['creator_name'] ? $r['creator_name'] : 'System'; ?><br><?php echo date('d/M H:i', strtotime($r['created_at'])); ?></div>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>

    <?php if($trash->num_rows > 0): ?>
    <div class="card" style="margin-top: 30px; border-top: 4px solid #ef4444;">
        <h3 style="color: #ef4444;">🗑️ Disabled Accounts (Recycle Bin)</h3>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Deleted By (Audit)</th><th>Actions</th></tr></thead>
            <tbody>
                <?php while($t = $trash->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $t['employee_id']; ?></td>
                    <td><strike style="color: #94a3b8;"><?php echo $t['employee_name']; ?></strike> (<?php echo $t['role']; ?>)</td>
                    <td>
                        <span class="audit-tag">👤 <?php echo $t['deleter_name'] ? $t['deleter_name'] : 'Unknown'; ?></span><br>
                        <small style="color: #64748b;"><?php echo $t['deleted_at'] ? date('d/m/Y H:i', strtotime($t['deleted_at'])) : '-'; ?></small>
                    </td>
                    <td>
                        <a href="?restore_id=<?php echo $t['employee_id']; ?>" class="btn btn-success" style="font-size:0.8em;">♻️ Restore</a>
                        <a href="?perm_del_id=<?php echo $t['employee_id']; ?>" class="btn btn-danger" style="font-size:0.8em;" onclick="return confirm('Delete Permanently?')">❌ Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <h2>👔 Add Employee</h2>
        <form method="POST">
            <label>Full Name:</label>
            <input type="text" name="name" required style="width:100%; margin-bottom:10px; padding:8px;">
            <label>Username (For Login):</label>
            <input type="text" name="username" required style="width:100%; margin-bottom:10px; padding:8px;">
            <label>Password:</label>
            <input type="text" name="password" required style="width:100%; margin-bottom:10px; padding:8px;">
            <label>Role:</label>
            <select name="role" style="width:100%; margin-bottom:15px; padding:8px;">
                <option value="Sales">Sales (ขายหน้าร้าน)</option>
                <option value="Inventory">Inventory (จัดการคลัง)</option>
                <option value="Manager">Manager (ผู้จัดการ)</option>
            </select>
            <button type="submit" name="add_employee" class="btn btn-primary" style="width:100%">Save Employee</button>
            <button type="button" onclick="closeModal('addModal')" class="btn btn-secondary" style="width:100%; margin-top:5px;">Cancel</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <h2>✏️ Edit Employee</h2>
        <form method="POST">
            <input type="hidden" name="employee_id" id="edit_id">
            <label>Full Name:</label>
            <input type="text" name="name" id="edit_name" required style="width:100%; margin-bottom:10px; padding:8px;">
            <label>Username:</label>
            <input type="text" name="username" id="edit_user" required style="width:100%; margin-bottom:10px; padding:8px;">
            <label>Password:</label>
            <input type="text" name="password" id="edit_pass" required style="width:100%; margin-bottom:10px; padding:8px;">
            <label>Role:</label>
            <select name="role" id="edit_role" style="width:100%; margin-bottom:15px; padding:8px;">
                <option value="Sales">Sales</option>
                <option value="Inventory">Inventory</option>
                <option value="Manager">Manager</option>
            </select>
            <button type="submit" name="edit_employee" class="btn btn-warning" style="width:100%">Update Changes</button>
            <button type="button" onclick="closeModal('editModal')" class="btn btn-secondary" style="width:100%; margin-top:5px;">Cancel</button>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function openEdit(data) {
    document.getElementById('edit_id').value = data.employee_id;
    document.getElementById('edit_name').value = data.employee_name;
    document.getElementById('edit_user').value = data.username;
    document.getElementById('edit_pass').value = data.password;
    document.getElementById('edit_role').value = data.role;
    openModal('editModal');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) { event.target.style.display = "none"; }
}
</script>

</body>
</html>