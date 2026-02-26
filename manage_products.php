<?php
session_start();
include 'db_connect.php';

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] == 'Sales') { echo "<script>alert('Access Denied!'); window.location='index.php';</script>"; exit(); }
$uid = $_SESSION['user_id'];

// --- 2. Action Logic ---

// Add
if (isset($_POST['add_product'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $cost = $_POST['cost']; $price = $_POST['price']; $qty = $_POST['qty'];
    $sql = "INSERT INTO Products (product_name, cost, price, stock_qty) VALUES ('$name', $cost, $price, $qty)";
    if ($conn->query($sql)) {
        $pid = $conn->insert_id;
        $conn->query("INSERT INTO Stock_Logs (product_id, qty_change, log_type, employee_id) VALUES ($pid, $qty, 'Restock', $uid)");
        header("Location: manage_products.php");
    }
}

// Restock
if (isset($_POST['restock_submit'])) {
    $pid = $_POST['product_id']; $qty_add = $_POST['qty_add'];
    if ($qty_add > 0) {
        $conn->query("UPDATE Products SET stock_qty = stock_qty + $qty_add WHERE product_id = $pid");
        $conn->query("INSERT INTO Stock_Logs (product_id, qty_change, log_type, employee_id) VALUES ($pid, $qty_add, 'Restock', $uid)");
        header("Location: manage_products.php");
    }
}

// Edit
if (isset($_POST['edit_product'])) {
    $pid = $_POST['product_id'];
    $name = $conn->real_escape_string($_POST['name']);
    $cost = $_POST['cost']; $price = $_POST['price'];
    $conn->query("UPDATE Products SET product_name='$name', cost=$cost, price=$price WHERE product_id=$pid");
    header("Location: manage_products.php");
}

// ✅ Soft Delete (บันทึกคนลบ + เวลาลบ)
if (isset($_GET['delete_id'])) {
    $pid = $_GET['delete_id'];
    $conn->query("UPDATE Products SET is_deleted = 1, deleted_by = $uid, deleted_at = NOW() WHERE product_id = $pid");
    header("Location: manage_products.php");
}

// Restore
if (isset($_GET['restore_id'])) {
    $pid = $_GET['restore_id'];
    $conn->query("UPDATE Products SET is_deleted = 0 WHERE product_id = $pid");
    header("Location: manage_products.php");
}

// Permanent Delete
if (isset($_GET['perm_del_id'])) {
    $pid = $_GET['perm_del_id'];
    $conn->query("DELETE FROM Products WHERE product_id = $pid");
    header("Location: manage_products.php");
}

// --- 3. Fetch Data ---
$products = $conn->query("SELECT * FROM Products WHERE is_deleted = 0 ORDER BY product_id DESC");

// ✅ ดึงข้อมูลถังขยะ + ชื่อคนลบ (JOIN Employees)
$trash = $conn->query("SELECT p.*, e.employee_name as deleter_name 
                       FROM Products p 
                       LEFT JOIN Employees e ON p.deleted_by = e.employee_id 
                       WHERE p.is_deleted = 1 
                       ORDER BY p.deleted_at DESC");

$logs = $conn->query("SELECT l.*, p.product_name, e.employee_name FROM Stock_Logs l JOIN Products p ON l.product_id = p.product_id JOIN Employees e ON l.employee_id = e.employee_id ORDER BY l.log_date DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Inventory</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .badge-low { background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; font-weight: bold; }
        .badge-ok { background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; font-weight: bold; }
        .audit-tag { font-size: 0.75em; color: #dc2626; background: #fee2e2; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 2px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; width: 400px; margin: 100px auto; padding: 20px; border-radius: 8px; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand">SD Project</a>
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="manage_products.php" class="active">Inventory</a>
    </div>
</nav>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>📦 Inventory Management</h1>
        <button onclick="openModal('addModal')" class="btn btn-primary">+ Add New Product</button>
    </div>

    <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr;">
        <div class="card">
            <h3>Current Stock</h3>
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Cost/Price</th><th>Stock</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php while($p = $products->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $p['product_id']; ?></td>
                        <td><strong><?php echo $p['product_name']; ?></strong></td>
                        <td><small>Cost: <?php echo $p['cost']; ?></small><br><strong style="color:#2563eb;"><?php echo $p['price']; ?></strong></td>
                        <td><?php echo ($p['stock_qty'] < 30) ? "<span class='badge-low'>Low: {$p['stock_qty']}</span>" : "<span class='badge-ok'>{$p['stock_qty']}</span>"; ?></td>
                        <td>
                            <button onclick="openRestock(<?php echo $p['product_id']; ?>, '<?php echo $p['product_name']; ?>')" class="btn btn-success" style="font-size:0.8em;">+ Stock</button>
                            <button onclick="openEdit(<?php echo htmlspecialchars(json_encode($p)); ?>)" class="btn btn-warning" style="font-size:0.8em;">Edit</button>
                            <a href="?delete_id=<?php echo $p['product_id']; ?>" class="btn btn-danger" style="font-size:0.8em;" onclick="return confirm('Trash this product?')">Del</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>🕒 Recent Movements</h3>
            <ul style="list-style: none; padding: 0;">
                <?php while($l = $logs->fetch_assoc()): ?>
                <li style="border-bottom: 1px solid #e2e8f0; padding: 10px 0;">
                    <div style="display: flex; justify-content: space-between;">
                        <strong><?php echo $l['product_name']; ?></strong>
                        <span style="<?php echo $l['qty_change'] > 0 ? 'color:green':'color:red'; ?>"><?php echo $l['qty_change'] > 0 ? '+'.$l['qty_change'] : $l['qty_change']; ?></span>
                    </div>
                    <div style="font-size: 0.8em; color: #64748b;"><?php echo $l['log_type']; ?> by <?php echo $l['employee_name']; ?><br><?php echo date('d/M H:i', strtotime($l['log_date'])); ?></div>
                </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>

    <?php if($trash->num_rows > 0): ?>
    <div class="card" style="margin-top: 30px; border-top: 4px solid #ef4444;">
        <h3 style="color: #ef4444;">🗑️ Recycle Bin (Deleted Products)</h3>
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Deleted By (Audit)</th><th>Actions</th></tr></thead>
            <tbody>
                <?php while($t = $trash->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $t['product_id']; ?></td>
                    <td><strike style="color: #94a3b8;"><?php echo $t['product_name']; ?></strike></td>
                    <td>
                        <span class="audit-tag">
                            👤 <?php echo $t['deleter_name'] ? $t['deleter_name'] : 'Unknown'; ?>
                        </span><br>
                        <small style="color: #64748b;"><?php echo $t['deleted_at'] ? date('d/m/Y H:i', strtotime($t['deleted_at'])) : '-'; ?></small>
                    </td>
                    <td>
                        <a href="?restore_id=<?php echo $t['product_id']; ?>" class="btn btn-success" style="font-size:0.8em;">♻️ Restore</a>
                        <a href="?perm_del_id=<?php echo $t['product_id']; ?>" class="btn btn-danger" style="font-size:0.8em;" onclick="return confirm('Delete Permanently?')">❌ Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<div id="addModal" class="modal"><div class="modal-content"><h2>✨ New Product</h2><form method="POST"><label>Name:</label><input type="text" name="name" required style="width:100%; margin-bottom:10px;"><label>Cost:</label><input type="number" step="0.01" name="cost" required style="width:100%; margin-bottom:10px;"><label>Price:</label><input type="number" step="0.01" name="price" required style="width:100%; margin-bottom:10px;"><label>Stock:</label><input type="number" name="qty" value="0" required style="width:100%; margin-bottom:10px;"><button type="submit" name="add_product" class="btn btn-primary" style="width:100%">Save</button><button type="button" onclick="closeModal('addModal')" class="btn btn-secondary" style="width:100%; margin-top:5px;">Cancel</button></form></div></div>
<div id="restockModal" class="modal"><div class="modal-content"><h2>📦 Add Stock</h2><p>Product: <strong id="res_name"></strong></p><form method="POST"><input type="hidden" name="product_id" id="res_id"><input type="number" name="qty_add" min="1" required style="width:100%; margin-bottom:10px;"><button type="submit" name="restock_submit" class="btn btn-success" style="width:100%">Confirm</button><button type="button" onclick="closeModal('restockModal')" class="btn btn-secondary" style="width:100%; margin-top:5px;">Cancel</button></form></div></div>
<div id="editModal" class="modal"><div class="modal-content"><h2>✏️ Edit Product</h2><form method="POST"><input type="hidden" name="product_id" id="edit_id"><label>Name:</label><input type="text" name="name" id="edit_name" required style="width:100%; margin-bottom:10px;"><label>Cost:</label><input type="number" step="0.01" name="cost" id="edit_cost" required style="width:100%; margin-bottom:10px;"><label>Price:</label><input type="number" step="0.01" name="price" id="edit_price" required style="width:100%; margin-bottom:10px;"><button type="submit" name="edit_product" class="btn btn-warning" style="width:100%">Update</button><button type="button" onclick="closeModal('editModal')" class="btn btn-secondary" style="width:100%; margin-top:5px;">Cancel</button></form></div></div>

<script>
function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
function openRestock(id, name) { document.getElementById('res_id').value = id; document.getElementById('res_name').innerText = name; openModal('restockModal'); }
function openEdit(product) { document.getElementById('edit_id').value = product.product_id; document.getElementById('edit_name').value = product.product_name; document.getElementById('edit_cost').value = product.cost; document.getElementById('edit_price').value = product.price; openModal('editModal'); }
window.onclick = function(event) { if (event.target.classList.contains('modal')) event.target.style.display = "none"; }
</script>
</body>
</html>