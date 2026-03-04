<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$uid = $_SESSION['user_id'];

// --- 1. 🚚 กดยืนยันการจัดส่งผ่าน Pop-up Modal ---
if (isset($_POST['submit_shipment'])) {
    $oid = (int)$_POST['ship_order_id'];
    $shipping_comp = $conn->real_escape_string($_POST['shipping_company']);
    $tracking_no = $conn->real_escape_string($_POST['tracking_number']);

    $conn->begin_transaction(); 
    try {
        $order = $conn->query("SELECT status, order_type FROM Orders WHERE order_id = $oid FOR UPDATE")->fetch_assoc();
        if ($order['status'] == 'Pending') {
            // เช็คและตัดสต็อก
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

            // อัปเดตสถานะออเดอร์ + บันทึกข้อมูลขนส่ง
            $conn->query("UPDATE Orders SET 
                          status = 'Shipped', 
                          shipping_company = '$shipping_comp', 
                          tracking_number = '$tracking_no' 
                          WHERE order_id = $oid");

            // สร้าง Invoice
            $inv_type = $order['order_type'];
            $conn->query("INSERT INTO Invoices (order_id, payment_status, issued_by, invoice_type) VALUES ($oid, 'Pending', $uid, '$inv_type')");
            
            $conn->commit();
            echo "<script>alert('Order Shipped Successfully!\\nInvoice Generated.'); window.location='manage_orders.php';</script>";
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('" . $e->getMessage() . "'); window.location='manage_orders.php';</script>";
    }
}

// 🗑️ Soft Delete
if (isset($_GET['delete_id'])) {
    $oid = (int)$_GET['delete_id'];
    $check = $conn->query("SELECT status FROM Orders WHERE order_id = $oid")->fetch_assoc();
    if ($check['status'] == 'Shipped') {
        echo "<script>alert('Cannot delete Shipped orders!'); window.location='manage_orders.php';</script>";
    } else {
        $conn->query("UPDATE Orders SET is_deleted = 1, deleted_by = $uid, deleted_at = NOW() WHERE order_id = $oid");
        header("Location: manage_orders.php");
    }
}

//Restore
if (isset($_GET['restore_id'])) {
    $oid = (int)$_GET['restore_id'];
    $conn->query("UPDATE Orders SET is_deleted = 0 WHERE order_id = $oid");
    header("Location: manage_orders.php");
}

//ลบถาวร
if (isset($_GET['perm_del_id'])) {
    $oid = (int)$_GET['perm_del_id'];
    $conn->query("DELETE FROM Order_Details WHERE order_id = $oid");
    $conn->query("DELETE FROM Orders WHERE order_id = $oid");
    header("Location: manage_orders.php");
}

// --- 3. Fetch Data ---
$sql = "SELECT o.*, c.contact_name, c.address as cust_address,
               e.employee_name as creator_name, u.employee_name as updater_name 
        FROM Orders o 
        LEFT JOIN Customers c ON o.customer_id = c.customer_id 
        LEFT JOIN Employees e ON o.employee_id = e.employee_id 
        LEFT JOIN Employees u ON o.updated_by = u.employee_id 
        WHERE o.is_deleted = 0 ORDER BY o.order_id DESC";
$orders = $conn->query($sql);

$sql_trash = "SELECT o.*, c.contact_name, d.employee_name as deleter_name 
              FROM Orders o 
              LEFT JOIN Customers c ON o.customer_id = c.customer_id 
              LEFT JOIN Employees d ON o.deleted_by = d.employee_id 
              WHERE o.is_deleted = 1 ORDER BY o.deleted_at DESC";
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
        
        /* Modal Style */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;}
        .modal-content { background: white; width: 450px; margin: 80px auto; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
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
        <p style="color: #64748b;">Manage shipments, input tracking numbers, and edit pending orders.</p>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Order Ref</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Audit Trail</th>
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
                            <span class="audit-tag">Edited by: <?php echo $o['updater_name']; ?></span>
                        <?php endif; ?>
                        <?php if($o['tracking_number']): ?>
                            <div class="audit-tag" style="background:#e0f2fe; color:#0369a1; margin-top:4px;"><?php echo $o['shipping_company']; ?>: <?php echo $o['tracking_number']; ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 1.1em; font-weight: bold; color: #2563eb;">฿<?php echo number_format($o['net_total'], 2); ?></td>
                    <td><?php echo ($o['status'] == 'Pending') ? '<span class="badge-pending">Pending</span>' : '<span class="badge-shipped">Shipped</span>'; ?></td>
                    <td>
                        <?php if($o['status'] == 'Pending'): ?>
                            <button type="button" class="btn btn-success" style="padding: 4px 8px; font-size: 0.85em; display:block; margin-bottom: 4px;" 
                                    data-address="<?php echo htmlspecialchars($o['cust_address']); ?>" 
                                    onclick="openShipModal(this, <?php echo $o['order_id']; ?>, '<?php echo $o['po_ref']; ?>')">
                                Ship
                            </button>
                            <a href="edit_sale_order.php?id=<?php echo $o['order_id']; ?>" class="btn btn-warning" style="padding: 4px 8px; font-size: 0.85em;">Edit</a>
                            <a href="?delete_id=<?php echo $o['order_id']; ?>" class="btn btn-danger" style="padding: 4px 8px; font-size: 0.85em;" onclick="return confirm('Move to trash?')">Del</a>
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
        <h3 style="color: #ef4444;">Recycle Bin</h3>
        <table>
            <thead><tr><th>Order Ref</th><th>Customer</th><th>Deleted By</th><th>Action</th></tr></thead>
            <tbody>
                <?php while($t = $trash->fetch_assoc()): ?>
                <tr>
                    <td><strike><?php echo $t['po_ref']; ?></strike></td>
                    <td><?php echo $t['contact_name']; ?></td>
                    <td><span class="audit-del"><?php echo $t['deleter_name'] ? $t['deleter_name'] : 'Unknown'; ?></span></td>
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

<div id="shipModal" class="modal">
    <div class="modal-content">
        <h2 style="color: #0369a1; border-bottom: 2px solid #e0f2fe; padding-bottom: 10px;">Shipping Details</h2>
        <p style="color: #64748b; font-size: 0.9rem;">Order: <strong id="disp_ship_ref"></strong></p>
        
        <form method="POST">
            <input type="hidden" name="ship_order_id" id="modal_order_id">
            
            <label>Customer Address (Ship-To):</label>
            <textarea id="modal_address" readonly style="width:100%; margin-bottom:15px; padding:10px; background:#f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; color: #475569; resize:none; height: 60px;"></textarea>

            <label>Shipping Company:</label>
            <select name="shipping_company" required style="width:100%; margin-bottom:15px; padding:10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                <option value="">-- Select Courier --</option>
                <option value="Kerry Express">Kerry Express</option>
                <option value="Flash Express">Flash Express</option>
                <option value="J&T Express">J&T Express</option>
                <option value="Thailand Post (EMS)">Thailand Post (EMS)</option>
                <option value="Shopee Express">Shopee Express</option>
                <option value="Ninja Van">Ninja Van</option>
                <option value="Lalamove">Lalamove</option>
                <option value="Company Truck (ส่งเอง)">Company Truck (พนักงานบริษัทส่งเอง)</option>
                <option value="Pick up at store (รับหน้าร้าน)">Pick up at store (ลูกค้ารับเอง)</option>
            </select>
            
            <label>Tracking Number / Vehicle No:</label>
            <input type="text" name="tracking_number" required placeholder="e.g. TH0123456789 หรือ ทะเบียนรถ" style="width:100%; margin-bottom:20px; padding:10px; border: 1px solid #cbd5e1; border-radius: 6px;">
            
            <button type="submit" name="submit_shipment" class="btn btn-success" style="width:100%; padding: 12px; font-size: 1.1rem;" onclick="return confirm('Confirm Shipment?\\nThis will deduct stock and generate an invoice.')">Confirm & Ship</button>
            <button type="button" onclick="closeModal('shipModal')" class="btn btn-secondary" style="width:100%; margin-top:10px; padding: 12px;">Cancel</button>
        </form>
    </div>
</div>

<script>
function openShipModal(btnElement, order_id, ref_no) {
    // ดึงที่อยู่มาจาก Attribute data-address ของปุ่มที่กด
    var address = btnElement.getAttribute('data-address');
    
    document.getElementById('modal_order_id').value = order_id;
    document.getElementById('disp_ship_ref').innerText = ref_no;
    
    // ใส่ที่อยู่ลงใน Textarea
    if(address && address.trim() !== '') {
        document.getElementById('modal_address').value = address;
    } else {
        document.getElementById('modal_address').value = "No address provided.";
    }

    document.getElementById('shipModal').style.display = 'block';
}

function closeModal(id) { 
    document.getElementById(id).style.display = 'none'; 
}

// ปิดเมื่อกดข้างนอก Modal
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = "none";
    }
}
</script>

</body>
</html>