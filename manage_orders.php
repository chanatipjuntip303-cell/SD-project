<?php
session_start();
include 'db_connect.php';

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$uid = $_SESSION['user_id'];

// --- 2. Action Logic ---

//  กดยืนยันการจัดส่ง (Ship)
if (isset($_GET['ship_id'])) {
    $oid = (int)$_GET['ship_id'];
    $conn->begin_transaction(); 
    try {
        $order = $conn->query("SELECT status, order_type FROM Orders WHERE order_id = $oid FOR UPDATE")->fetch_assoc();
        if ($order['status'] == 'Pending') {
            $items = $conn->query("SELECT product_id, qty FROM Order_Details WHERE order_id = $oid");
            while($item = $items->fetch_assoc()) {
                $pid = $item['product_id']; $qty_needed = $item['qty'];
                $prod = $conn->query("SELECT product_name, stock_qty FROM Products WHERE product_id = $pid")->fetch_assoc();
                if ($prod['stock_qty'] < $qty_needed) {
                    throw new Exception(" Not enough stock for: " . $prod['product_name']);
                }
                $conn->query("UPDATE Products SET stock_qty = stock_qty - $qty_needed WHERE product_id = $pid");
                $conn->query("INSERT INTO Stock_Logs (product_id, qty_change, log_type, employee_id, related_order_id) VALUES ($pid, -$qty_needed, 'Sale', $uid, $oid)");
            }
            $conn->query("UPDATE Orders SET status = 'Shipped' WHERE order_id = $oid");
            $inv_type = $order['order_type'];
            $conn->query("INSERT INTO Invoices (order_id, payment_status, issued_by, invoice_type) VALUES ($oid, 'Pending', $uid, '$inv_type')");
            $conn->commit();
            echo "<script>alert(' Order Shipped! Invoice Generated.'); window.location='manage_orders.php';</script>";
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('" . $e->getMessage() . "'); window.location='manage_orders.php';</script>";
    }
}

//  Soft Delete (ย้ายลงถังขยะ + บันทึกคนลบ)
if (isset($_GET['delete_id'])) {
    $oid = (int)$_GET['delete_id'];
    // เช็คว่าถ้าส่งของแล้ว ห้ามลบเด็ดขาด!
    $check = $conn->query("SELECT status FROM Orders WHERE order_id = $oid")->fetch_assoc();
    if ($check['status'] == 'Shipped') {
        echo "<script>alert(' Cannot delete Shipped orders! They are locked for accounting.'); window.location='manage_orders.php';</script>";
    } else {
        $conn->query("UPDATE Orders SET is_deleted = 1, deleted_by = $uid, deleted_at = NOW() WHERE order_id = $oid");
        header("Location: manage_orders.php");
    }
}

// กู้คืนออเดอร์ (Restore)
if (isset($_GET['restore_id'])) {
    $oid = (int)$_GET['restore_id'];
    $conn->query("UPDATE Orders SET is_deleted = 0 WHERE order_id = $oid");
    header("Location: manage_orders.php");
}

// ลบถาวร (Permanent Delete)
if (isset($_GET['perm_del_id'])) {
    $oid = (int)$_GET['perm_del_id'];
    $conn->query("DELETE FROM Order_Details WHERE order_id = $oid"); // ลบรายการลูกก่อน
    $conn->query("DELETE FROM Orders WHERE order_id = $oid"); // ค่อยลบหัวบิล
    header("Location: manage_orders.php");
}

// --- 3. Fetch Data ---
// รายการปกติ (Join หาคนอัปเดตด้วย)
$sql = "SELECT o.*, c.contact_name, 
               e.employee_name as creator_name, 
               u.employee_name as updater_name 
        FROM Orders o 
        LEFT JOIN Customers c ON o.customer_id = c.customer_id 
        LEFT JOIN Employees e ON o.employee_id = e.employee_id 
        LEFT JOIN Employees u ON o.updated_by = u.employee_id 
        WHERE o.is_deleted = 0 
        ORDER BY o.order_id DESC";
$orders = $conn->query($sql);

// รายการในถังขยะ (Join หาคนลบ)
$sql_trash = "SELECT o.*, c.contact_name, d.employee_name as deleter_name 
              FROM Orders o 
              LEFT JOIN Customers c ON o.customer_id = c.customer_id 
              LEFT JOIN Employees d ON o.deleted_by = d.employee_id 
              WHERE o.is_deleted = 1 
              ORDER BY o.deleted_at DESC";
$trash = $conn->query($sql_trash);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .badge-pending { background: #fef08a; color: #854d0e; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 0.85em; }
        .badge-shipped { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 0.85em; }
        .type-std { border-left: 4px solid #3b82f6; padding-left: 8px; color: #3b82f6; font-weight: bold; }
        .type-dir { border-left: 4px solid #10b981; padding-left: 8px; color: #10b981; font-weight: bold; }
        .audit-tag { font-size: 0.75em; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; }
        .audit-del { font-size: 0.75em; color: #dc2626; background: #fee2e2; padding: 2px 6px; border-radius: 4px; display: inline-block; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand">SD Project</a>
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="pos_direct_sale.php">POS</a>
        <a href="create_sale_order.php">Sales Order</a>
        <a href="manage_orders.php" class="active">Manage Orders</a>
        <a href="view_invoices.php">Invoices</a>
    </div>
</nav>

<div class="container">
    <div style="margin-bottom: 20px;">
        <h1 style="color: #f59e0b;">Order Management</h1>
        <p style="color: #64748b;">Manage shipments and edit pending orders.</p>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Order Ref</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Audit Trail (Edit Log)</th>
                    <th>Net Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($o = $orders->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo $o['po_ref']; ?></strong><br><small>#<?php echo $o['order_id']; ?></small></td>
                    <td><?php echo ($o['order_type'] == 'Standard') ? '<span class="type-std">Standard</span>' : '<span class="type-dir">Direct</span>'; ?></td>
                    <td><strong><?php echo $o['contact_name']; ?></strong><br><small>By: <?php echo $o['creator_name']; ?></small></td>
                    <td>
                        <?php echo date('d/m/Y H:i', strtotime($o['order_date'])); ?><br>
                        <?php if($o['updated_by']): ?>
                            <span class="audit-tag"> Edited by: <?php echo $o['updater_name']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 1.1em; font-weight: bold; color: #2563eb;">฿<?php echo number_format($o['net_total'], 2); ?></td>
                    <td><?php echo ($o['status'] == 'Pending') ? '<span class="badge-pending">Pending</span>' : '<span class="badge-shipped"> Shipped</span>'; ?></td>
                    <td>
                        <?php if($o['status'] == 'Pending'): ?>
                            <a href="?ship_id=<?php echo $o['order_id']; ?>" class="btn btn-success" style="padding: 4px 8px; font-size: 0.85em; display:block; margin-bottom: 4px;" onclick="return confirm('Confirm Shipment?')">Ship</a>
                            <a href="edit_sale_order.php?id=<?php echo $o['order_id']; ?>" class="btn btn-warning" style="padding: 4px 8px; font-size: 0.85em;"> Edit</a>
                            <a href="?delete_id=<?php echo $o['order_id']; ?>" class="btn btn-danger" style="padding: 4px 8px; font-size: 0.85em;" onclick="return confirm('Move to trash?')"> Del</a>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 0.8em;">Locked</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if($trash->num_rows > 0): ?>
    <div class="card" style="margin-top: 30px; border-top: 4px solid #ef4444;">
        <h3 style="color: #ef4444;">Recycle Bin (Deleted Orders)</h3>
        <table>
            <thead><tr><th>Order Ref</th><th>Customer</th><th>Deleted By (Audit)</th><th>Action</th></tr></thead>
            <tbody>
                <?php while($t = $trash->fetch_assoc()): ?>
                <tr>
                    <td><strike><?php echo $t['po_ref']; ?></strike></td>
                    <td><?php echo $t['contact_name']; ?></td>
                    <td>
                        <span class="audit-del"><?php echo $t['deleter_name'] ? $t['deleter_name'] : 'Unknown'; ?></span><br>
                        <small style="color: #64748b;"><?php echo date('d/m/Y H:i', strtotime($t['deleted_at'])); ?></small>
                    </td>
                    <td>
                        <a href="?restore_id=<?php echo $t['order_id']; ?>" class="btn btn-success" style="font-size:0.8em;">Restore</a>
                        <a href="?perm_del_id=<?php echo $t['order_id']; ?>" class="btn btn-danger" style="font-size:0.8em;" onclick="return confirm('Delete Permanently?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</body>
</html>