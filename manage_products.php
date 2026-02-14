<?php
session_start();
include 'db_connect.php';

// --- 1. ตรวจสอบสิทธิ์การเข้าถึง ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['user_role'] != 'Manager' && $_SESSION['user_role'] != 'Inventory Control') {
    echo "<script>alert('Access Denied! Sales staff cannot access inventory.'); window.location='index.php';</script>";
    exit();
}

$current_user_id = $_SESSION['user_id'];

// --- 2. Logic จัดการข้อมูล (CRUD & Restock) ---

// เติมสต็อก (Restock)
if (isset($_POST['restock_product'])) {
    $pid = $_POST['product_id'];
    $qty = $_POST['qty_added'];

    if ($qty > 0) {
        $conn->query("UPDATE Products SET amount = amount + $qty WHERE product_ID = $pid");
        $conn->query("INSERT INTO Stock_Logs (qty_added, Products_product_ID, Employees_employee_ID) 
                      VALUES ($qty, $pid, $current_user_id)");
        header("Location: manage_products.php");
        exit();
    }
}

// สร้างสินค้าใหม่
if (isset($_POST['add_product'])) {
    $name = $_POST['product_name'];
    $desc = $_POST['description'];
    $price = $_POST['price_per_unit'];
    $cost = $_POST['cost'];
    $amount = $_POST['amount'];

    $sql = "INSERT INTO Products (product_name, description, price_per_unit, cost, amount, created_by) 
            VALUES ('$name', '$desc', $price, $cost, $amount, $current_user_id)";
    $conn->query($sql);
    header("Location: manage_products.php");
    exit();
}

// ย้ายลงถังขยะ (Soft Delete)
if (isset($_GET['soft_delete'])) {
    $conn->query("UPDATE Products SET is_deleted = 1 WHERE product_ID = " . $_GET['soft_delete']);
    header("Location: manage_products.php");
    exit();
}

// กู้คืน (Restore)
if (isset($_GET['restore'])) {
    $conn->query("UPDATE Products SET is_deleted = 0 WHERE product_ID = " . $_GET['restore']);
    header("Location: manage_products.php");
    exit();
}

// ลบถาวร (Permanent Delete)
if (isset($_GET['perm_delete'])) {
    // หมายเหตุ: การลบถาวรอาจจะ Error ถ้าสินค้านั้นเคยถูกขายไปแล้ว (ติด Foreign Key ใน Order_details)
    // ในโปรเจกต์ส่งอาจารย์ นิยมใช้แค่ Soft Delete ก็พอครับ แต่ใส่คำสั่งลบถาวรไว้ให้เป็น Option
    $conn->query("DELETE FROM Products WHERE product_ID = " . $_GET['perm_delete']);
    header("Location: manage_products.php");
    exit();
}

// --- 3. ดึงข้อมูลมาแสดงผล ---
$products = $conn->query("SELECT * FROM Products WHERE is_deleted = 0");
$deleted_products = $conn->query("SELECT * FROM Products WHERE is_deleted = 1"); // ข้อมูลในถังขยะ
$logs = $conn->query("SELECT l.*, p.product_name, e.employee_name 
                      FROM Stock_Logs l 
                      JOIN Products p ON l.Products_product_ID = p.product_ID
                      JOIN Employees e ON l.Employees_employee_ID = e.employee_ID
                      ORDER BY l.log_date DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>
    <link rel="stylesheet" href="style.css"> 
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand">SD Project Shop</a>
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="create_order.php">Sell</a>
        <a href="manage_products.php" class="active">Inventory</a>
    </div>
</nav>

<div class="container">
    
    <div class="dashboard-grid">
        <div class="card" style="border-top: 5px solid #2ecc71;">
            <h2>📦 Quick Restock</h2>
            <form method="POST">
                <label>Select Product:</label>
                <select name="product_id" required>
                    <option value="">-- Choose Product --</option>
                    <?php 
                    $products->data_seek(0); 
                    while($p = $products->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $p['product_ID']; ?>">
                            <?php echo $p['product_name']; ?> (Current: <?php echo $p['amount']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>

                <label>Restocked By:</label>
                <input type="text" value="<?php echo $_SESSION['user_name']; ?>" readonly style="background: #e2e8f0; cursor:not-allowed;">

                <label>Quantity to Add:</label>
                <input type="number" name="qty_added" min="1" placeholder="e.g. 50" required>

                <button type="submit" name="restock_product" class="btn btn-success" style="width:100%">+ Add Stock</button>
            </form>
        </div>

        <div class="card" style="border-top: 5px solid #3498db;">
            <h2>✨ New Product</h2>
            <form method="POST">
                <input type="text" name="product_name" placeholder="Product Name" required>
                <input type="text" name="description" placeholder="Description">
                <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                    <input type="number" step="0.01" name="cost" placeholder="Cost" required>
                    <input type="number" step="0.01" name="price_per_unit" placeholder="Price" required>
                </div>
                <input type="number" name="amount" placeholder="Initial Stock" required>
                <button type="submit" name="add_product" class="btn btn-primary" style="width:100%">Create Product</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h2>📊 Current Inventory</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $products->data_seek(0);
                while($row = $products->fetch_assoc()): 
                ?>
                <tr>
                    <td><?php echo $row['product_ID']; ?></td>
                    <td><?php echo $row['product_name']; ?></td>
                    <td>฿<?php echo number_format($row['price_per_unit'], 2); ?></td>
                    <td style="font-weight:bold; color: <?php echo ($row['amount'] < 10) ? '#e74c3c' : '#2c3e50'; ?>">
                        <?php echo $row['amount']; ?>
                    </td>
                    <td>
                        <a href="?soft_delete=<?php echo $row['product_ID']; ?>" class="btn btn-warning" style="padding: 5px 10px; font-size: 0.8em;" onclick="return confirm('Move to Trash?')">🗑️ Trash</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>🕒 Recent Restock History</h2>
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Product</th>
                    <th>Added By</th>
                    <th>Qty Added</th>
                </tr>
            </thead>
            <tbody>
                <?php while($log = $logs->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($log['log_date'])); ?></td>
                    <td><?php echo $log['product_name']; ?></td>
                    <td><?php echo $log['employee_name']; ?></td>
                    <td style="color: #27ae60; font-weight: bold;">+<?php echo $log['qty_added']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="border-top: 5px solid #e74c3c;">
        <h2 style="color: #e74c3c;">🗑️ Product Recycle Bin</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($deleted_products->num_rows == 0): ?>
                    <tr><td colspan="3" style="text-align:center; color:#999;">Trash is empty</td></tr>
                <?php endif; ?>

                <?php while($row = $deleted_products->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['product_ID']; ?></td>
                    <td><strike><?php echo $row['product_name']; ?></strike></td>
                    <td>
                        <a href="?restore=<?php echo $row['product_ID']; ?>" class="btn btn-success" style="padding: 5px 10px; font-size: 0.8em;">♻️ Restore</a>
                        <a href="?perm_delete=<?php echo $row['product_ID']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8em;" onclick="return confirm('Delete permanently? This action cannot be undone.')">❌ Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>