<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] == 'Inventory') { echo "<script>alert('Access Denied!'); window.location='index.php';</script>"; exit(); }
$uid = $_SESSION['user_id'];

// Actions
if (isset($_POST['add_customer'])) {
    $name = $conn->real_escape_string($_POST['name']); $address = $conn->real_escape_string($_POST['address']); $level = $_POST['level'];
    $conn->query("INSERT INTO Customers (contact_name, address, membership_level, created_by) VALUES ('$name', '$address', '$level', $uid)");
    header("Location: manage_customers.php");
}
if (isset($_POST['edit_customer'])) {
    $cid = $_POST['customer_id']; $name = $conn->real_escape_string($_POST['name']); $address = $conn->real_escape_string($_POST['address']); $level = $_POST['level'];
    $conn->query("UPDATE Customers SET contact_name='$name', address='$address', membership_level='$level' WHERE customer_id=$cid");
    header("Location: manage_customers.php");
}
// ✅ Soft Delete (บันทึกคนลบ)
if (isset($_GET['delete_id'])) {
    $cid = $_GET['delete_id'];
    if ($cid == 1) { echo "<script>alert('Cannot delete General Customer!'); window.location='manage_customers.php';</script>"; } 
    else { 
        $conn->query("UPDATE Customers SET is_deleted = 1, deleted_by = $uid, deleted_at = NOW() WHERE customer_id = $cid"); 
        header("Location: manage_customers.php"); 
    }
}
// Restore
if (isset($_GET['restore_id'])) {
    $cid = $_GET['restore_id'];
    $conn->query("UPDATE Customers SET is_deleted = 0 WHERE customer_id = $cid");
    header("Location: manage_customers.php");
}
// Permanent Delete
if (isset($_GET['perm_del_id'])) {
    $cid = $_GET['perm_del_id'];
    if ($cid == 1) { echo "<script>alert('Cannot delete General Customer!'); window.location='manage_customers.php';</script>"; }
    else { $conn->query("DELETE FROM Customers WHERE customer_id = $cid"); header("Location: manage_customers.php"); }
}

// Fetch
$customers = $conn->query("SELECT c.*, e.employee_name as creator_name FROM Customers c LEFT JOIN Employees e ON c.created_by = e.employee_id WHERE c.is_deleted = 0 ORDER BY c.customer_id DESC");
$recent = $conn->query("SELECT c.*, e.employee_name FROM Customers c LEFT JOIN Employees e ON c.created_by = e.employee_id WHERE c.is_deleted = 0 ORDER BY c.created_at DESC LIMIT 5");

// ✅ ดึงข้อมูลถังขยะ + คนลบ
$trash = $conn->query("SELECT c.*, e.employee_name as deleter_name 
                       FROM Customers c 
                       LEFT JOIN Employees e ON c.deleted_by = e.employee_id 
                       WHERE c.is_deleted = 1 
                       ORDER BY c.deleted_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Customers</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .badge-std { background: #e2e8f0; color: #475569; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .badge-prm { background: #f3e8ff; color: #7e22ce; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; border: 1px solid #d8b4fe; }
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
        <a href="manage_customers.php" class="active">Customers</a>
    </div>
</nav>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>👥 Customer Management</h1>
        <button onclick="openModal('addModal')" class="btn btn-primary">+ New Customer</button>
    </div>

    <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
        <div class="card">
            <h3>All Customers</h3>
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Level</th><th>Info</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php while($c = $customers->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $c['customer_id']; ?></td>
                        <td><strong><?php echo $c['contact_name']; ?></strong><?php if($c['customer_id'] == 1) echo "<small> (Default)</small>"; ?></td>
                        <td><?php echo ($c['membership_level'] == 'Premium') ? '<span class="badge-prm">💎 Premium</span>' : '<span class="badge-std">Standard</span>'; ?></td>
                        <td><small><?php echo $c['address']; ?></small><br><small style="color:#94a3b8;">By: <?php echo $c['creator_name'] ? $c['creator_name'] : '-'; ?></small></td>
                        <td>
                            <button onclick="openEdit(<?php echo htmlspecialchars(json_encode($c)); ?>)" class="btn btn-warning" style="font-size:0.8em;">Edit</button>
                            <?php if($c['customer_id'] != 1): ?>
                            <a href="?delete_id=<?php echo $c['customer_id']; ?>" class="btn btn-danger" style="font-size:0.8em;" onclick="return confirm('Delete this customer?')">Del</a>
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
                        <strong><?php echo $r['contact_name']; ?></strong>
                        <span style="font-size:0.8em;"><?php echo $r['membership_level']; ?></span>
                    </div>
                    <div style="font-size: 0.8em; color: #64748b;">Added by: <?php echo $r['employee_name']; ?><br><?php echo date('d/M H:i', strtotime($r['created_at'])); ?></div>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>

    <?php if($trash->num_rows > 0): ?>
    <div class="card" style="margin-top: 30px; border-top: 4px solid #ef4444;">
        <h3 style="color: #ef4444;">🗑️ Recycle Bin (Deleted Customers)</h3>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Deleted By (Audit)</th><th>Actions</th></tr></thead>
            <tbody>
                <?php while($t = $trash->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $t['customer_id']; ?></td>
                    <td><strike style="color: #94a3b8;"><?php echo $t['contact_name']; ?></strike></td>
                    <td>
                        <span class="audit-tag">
                            👤 <?php echo $t['deleter_name'] ? $t['deleter_name'] : 'Unknown'; ?>
                        </span><br>
                        <small style="color: #64748b;"><?php echo $t['deleted_at'] ? date('d/m/Y H:i', strtotime($t['deleted_at'])) : '-'; ?></small>
                    </td>
                    <td>
                        <a href="?restore_id=<?php echo $t['customer_id']; ?>" class="btn btn-success" style="font-size:0.8em;">♻️ Restore</a>
                        <a href="?perm_del_id=<?php echo $t['customer_id']; ?>" class="btn btn-danger" style="font-size:0.8em;" onclick="return confirm('Delete Permanently?')">❌ Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<div id="addModal" class="modal"><div class="modal-content"><h2>👤 Add Customer</h2><form method="POST"><label>Name:</label><input type="text" name="name" required style="width:100%; margin-bottom:10px;"><label>Address:</label><input type="text" name="address" required style="width:100%; margin-bottom:10px;"><label>Level:</label><select name="level" style="width:100%; margin-bottom:15px;"><option value="Standard">Standard</option><option value="Premium">Premium</option></select><button type="submit" name="add_customer" class="btn btn-primary" style="width:100%">Save</button><button type="button" onclick="closeModal('addModal')" class="btn btn-secondary" style="width:100%; margin-top:5px;">Cancel</button></form></div></div>
<div id="editModal" class="modal"><div class="modal-content"><h2>✏️ Edit Customer</h2><form method="POST"><input type="hidden" name="customer_id" id="edit_id"><label>Name:</label><input type="text" name="name" id="edit_name" required style="width:100%; margin-bottom:10px;"><label>Address:</label><input type="text" name="address" id="edit_address" required style="width:100%; margin-bottom:10px;"><label>Level:</label><select name="level" id="edit_level" style="width:100%; margin-bottom:15px;"><option value="Standard">Standard</option><option value="Premium">Premium</option></select><button type="submit" name="edit_customer" class="btn btn-warning" style="width:100%">Update</button><button type="button" onclick="closeModal('editModal')" class="btn btn-secondary" style="width:100%; margin-top:5px;">Cancel</button></form></div></div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function openEdit(data) { document.getElementById('edit_id').value = data.customer_id; document.getElementById('edit_name').value = data.contact_name; document.getElementById('edit_address').value = data.address; document.getElementById('edit_level').value = data.membership_level; openModal('editModal'); }
window.onclick = function(event) { if (event.target.classList.contains('modal')) event.target.style.display = "none"; }
</script>
</body>
</html>