<?php
session_start();
include 'db_connect.php';

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$uid = $_SESSION['user_id'];

// --- 2. Action Logic ---

// 2.1 🚚 กดยืนยันการจัดส่ง (Ship & Cut Stock & Invoice)
if (isset($_GET['ship_id'])) {
    $oid = (int)$_GET['ship_id'];

    $conn->begin_transaction(); // เริ่ม Transaction ป้องกันข้อมูลพังถ้าไฟดับกลางคัน
    
    try {
        // เช็คก่อนว่าสถานะเป็น Pending จริงไหม
        $order = $conn->query("SELECT status, order_type FROM Orders WHERE order_id = $oid FOR UPDATE")->fetch_assoc();
        
        if ($order['status'] == 'Pending') {
            
            // ก. ดึงรายการสินค้าในออเดอร์นี้มาเช็ค
            $items = $conn->query("SELECT product_id, qty FROM Order_Details WHERE order_id = $oid");
            
            while($item = $items->fetch_assoc()) {
                $pid = $item['product_id'];
                $qty_needed = $item['qty'];

                // เช็คสต็อกว่าพอส่งไหม
                $prod = $conn->query("SELECT product_name, stock_qty FROM Products WHERE product_id = $pid")->fetch_assoc();
                if ($prod['stock_qty'] < $qty_needed) {
                    throw new Exception("❌ Not enough stock for: " . $prod['product_name'] . " (Has: " . $prod['stock_qty'] . " / Need: " . $qty_needed . ")");
                }

                // ข. ตัดสต็อก ✂️
                $conn->query("UPDATE Products SET stock_qty = stock_qty - $qty_needed WHERE product_id = $pid");

                // ค. บันทึก Audit Trail (Stock Logs)
                $conn->query("INSERT INTO Stock_Logs (product_id, qty_change, log_type, employee_id, related_order_id) 
                              VALUES ($pid, -$qty_needed, 'Sale', $uid, $oid)");
            }

            // ง. อัปเดตสถานะออเดอร์เป็น 'Shipped'
            $conn->query("UPDATE Orders SET status = 'Shipped' WHERE order_id = $oid");

            // จ. สร้างใบแจ้งหนี้ (Invoice)
            $inv_type = $order['order_type']; // ดึงประเภทมาจาก Order (Standard)
            $conn->query("INSERT INTO Invoices (order_id, payment_status, issued_by, invoice_type) 
                          VALUES ($oid, 'Pending', $uid, '$inv_type')");

            $conn->commit(); // Save ทุกอย่างลง Database
            echo "<script>alert('✅ Order Shipped!\\nStock Deducted & Invoice Generated.'); window.location='manage_orders.php';</script>";
            
        } else {
            throw new Exception("Order is not in Pending status.");
        }
        
    } catch (Exception $e) {
        $conn->rollback(); // ยกเลิกการเปลี่ยนแปลงทั้งหมดถ้าเกิด Error
        echo "<script>alert('" . $e->getMessage() . "'); window.location='manage_orders.php';</script>";
    }
}

// 2.2 ❌ กดยกเลิกออเดอร์ (Cancel)
if (isset($_GET['cancel_id'])) {
    $oid = (int)$_GET['cancel_id'];
    // เปลี่ยนสถานะเป็น Cancelled (ยกเลิกได้เฉพาะตอน Pending เท่านั้น)
    $conn->query("UPDATE Orders SET status = 'Cancelled' WHERE order_id = $oid AND status = 'Pending'");
    header("Location: manage_orders.php");
}

// --- 3. Fetch Data ---
// ดึงข้อมูลออเดอร์ทั้งหมด พร้อมชื่อลูกค้าและพนักงาน
$sql = "SELECT o.*, c.contact_name, e.employee_name 
        FROM Orders o 
        LEFT JOIN Customers c ON o.customer_id = c.customer_id 
        LEFT JOIN Employees e ON o.employee_id = e.employee_id 
        ORDER BY o.order_id DESC";
$orders = $conn->query($sql);
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
        .badge-cancelled { background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 0.85em; text-decoration: line-through; }
        
        .type-std { border-left: 4px solid #3b82f6; padding-left: 8px; color: #3b82f6; font-weight: bold; }
        .type-dir { border-left: 4px solid #10b981; padding-left: 8px; color: #10b981; font-weight: bold; }
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
    </div>
</nav>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="color: #f59e0b;">📦 Order Management & Shipment</h1>
            <p style="color: #64748b;">Confirm shipments to deduct stock and generate invoices.</p>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Order Ref</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Net Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($o = $orders->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?php echo $o['po_ref']; ?></strong><br>
                        <small style="color: #94a3b8;">ID: #<?php echo $o['order_id']; ?></small>
                    </td>
                    <td>
                        <?php if($o['order_type'] == 'Standard'): ?>
                            <span class="type-std">Standard Order</span>
                        <?php else: ?>
                            <span class="type-dir">POS Direct</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo $o['contact_name']; ?></strong><br>
                        <small style="color: #64748b;">By: <?php echo $o['employee_name']; ?></small>
                    </td>
                    <td><?php echo date('d/m/Y H:i', strtotime($o['order_date'])); ?></td>
                    <td style="font-size: 1.1em; font-weight: bold; color: #2563eb;">
                        ฿<?php echo number_format($o['net_total'], 2); ?>
                    </td>
                    <td>
                        <?php 
                            if($o['status'] == 'Pending') echo '<span class="badge-pending">⏳ Pending</span>';
                            elseif($o['status'] == 'Shipped') echo '<span class="badge-shipped">✅ Shipped</span>';
                            else echo '<span class="badge-cancelled">❌ Cancelled</span>';
                        ?>
                    </td>
                    <td>
                        <?php if($o['status'] == 'Pending'): ?>
                            <a href="?ship_id=<?php echo $o['order_id']; ?>" class="btn btn-success" style="padding: 6px 12px; margin-bottom: 5px; display: inline-block;" onclick="return confirm('Confirm Shipment?\\n\\nThis will DEDUCT STOCK and CREATE INVOICE immediately.')">🚚 Ship & Invoice</a>
                            <br>
                            <a href="?cancel_id=<?php echo $o['order_id']; ?>" class="btn btn-danger" style="padding: 4px 8px; font-size: 0.8em;" onclick="return confirm('Cancel this order?')">❌ Cancel</a>
                        <?php elseif($o['status'] == 'Shipped'): ?>
                            <a href="view_invoices.php?highlight=<?php echo $o['order_id']; ?>" class="btn btn-primary" style="padding: 6px 12px;">📄 View Invoice</a>
                        <?php else: ?>
                            <span style="color: #94a3b8;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>