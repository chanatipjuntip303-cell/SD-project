<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];
$id = $_GET['id'];
$edit_mode = isset($_GET['mode']) && $_GET['mode'] == 'edit';

// --- Logic Soft Delete จากหน้า Detail ---
if (isset($_GET['action']) && $_GET['action'] == 'soft_delete') {
    $conn->query("UPDATE Invoices SET is_deleted = 1, updated_by = $current_user_id, updated_at = NOW() WHERE Invoice_id = $id");
    header("Location: view_invoices.php"); 
    exit;
}

// --- Logic Update (เมื่อกด Save) ---
if (isset($_POST['update_invoice'])) {
    $new_date = $_POST['billing_date'];
    $new_status = $_POST['payment_status'];
    $new_po = $_POST['po_reference'];
    $new_total = $_POST['grand_total'];

    // อัปเดตข้อมูล พร้อมบันทึก updated_by และ updated_at = NOW()
    $conn->query("UPDATE Invoices SET billing_date='$new_date', payment_status='$new_status', grand_total=$new_total, updated_by=$current_user_id, updated_at=NOW() WHERE Invoice_id=$id");
    
    // อัปเดต PO Reference ในตาราง Orders
    $oid = $_POST['order_id'];
    $conn->query("UPDATE Orders SET PO_reference='$new_po' WHERE Order_ID=$oid");

    header("Location: invoice_detail.php?id=$id");
    exit();
}

// --- ดึงข้อมูล Header ---
$sql_head = "SELECT i.*, o.Order_ID, o.PO_reference, c.contact_name, c.address, 
                    e.employee_name AS sales_name, 
                    u.employee_name AS updater_name 
             FROM Invoices i
             JOIN Orders o ON i.Orders_Order_ID = o.Order_ID
             JOIN Customers c ON o.Customers_customer_id = c.customer_id
             JOIN Employees e ON o.Employees_employee_ID = e.employee_ID
             LEFT JOIN Employees u ON i.updated_by = u.employee_ID
             WHERE i.Invoice_id = $id";
$head = $conn->query($sql_head)->fetch_assoc();

if(!$head) die("Invoice not found or deleted.");

// --- ดึงข้อมูล Items ---
$sql_items = "SELECT d.*, p.product_name 
              FROM Order_details d
              JOIN Products p ON d.Products_product_ID = p.product_ID
              WHERE d.Orders_Order_ID = " . $head['Order_ID'];
$items = $conn->query($sql_items);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice Detail #<?php echo $id; ?></title>
    <link rel="stylesheet" href="style.css"> 
    <style>
        .info-group { margin-bottom: 15px; }
        .info-group label { color: #64748b; font-size: 0.9em; margin-bottom: 2px;}
        .value { font-size: 1.1em; font-weight: 500; color: #1e293b; }
        .audit-box { background: #f8fafc; border: 1px dashed #cbd5e1; padding: 10px; border-radius: 6px; margin-top: 20px; font-size: 0.9em; color: #64748b; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand">SD Project Shop</a>
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="create_order.php">Sell</a>
        <a href="view_invoices.php" class="active">History</a>
    </div>
</nav>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <a href="view_invoices.php" style="text-decoration:none; color:#3498db; font-weight: bold;">← Back to List</a>
        <?php if(!$edit_mode): ?>
            <a href="print_invoice.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-primary">🖨️ Print Invoice</a>
        <?php endif; ?>
    </div>

    <div class="card" style="border-top: 5px solid <?php echo $edit_mode ? '#f59e0b' : '#3b82f6'; ?>;">
        <h1 style="margin-top:0;">
            <?php echo $edit_mode ? "✏️ Editing Invoice #" : "📄 Invoice Details #"; ?>
            <?php echo str_pad($id, 5, '0', STR_PAD_LEFT); ?>
        </h1>

        <form method="POST">
            <input type="hidden" name="order_id" value="<?php echo $head['Order_ID']; ?>">
            
            <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 40px;">
                <div>
                    <h3 style="color: #2563eb; border-bottom: 2px solid #bfdbfe; padding-bottom: 5px;">Customer Info</h3>
                    <div class="info-group"><label>Customer:</label><div class="value"><?php echo $head['contact_name']; ?></div></div>
                    <div class="info-group"><label>Address:</label><div class="value"><?php echo $head['address']; ?></div></div>
                    <div class="info-group"><label>Salesperson (Created By):</label><div class="value">✨ <?php echo $head['sales_name']; ?></div></div>
                </div>

                <div>
                    <h3 style="color: #2563eb; border-bottom: 2px solid #bfdbfe; padding-bottom: 5px;">Billing Details</h3>
                    <div class="info-group">
                        <label>Billing Date:</label>
                        <?php if($edit_mode): ?> <input type="date" name="billing_date" value="<?php echo $head['billing_date']; ?>">
                        <?php else: ?> <div class="value"><?php echo date('d/m/Y', strtotime($head['billing_date'])); ?></div> <?php endif; ?>
                    </div>
                    <div class="info-group">
                        <label>PO Reference:</label>
                        <?php if($edit_mode): ?> <input type="text" name="po_reference" value="<?php echo $head['PO_reference']; ?>">
                        <?php else: ?> <div class="value"><?php echo $head['PO_reference']; ?></div> <?php endif; ?>
                    </div>
                    <div class="info-group">
                        <label>Payment Status:</label>
                        <?php if($edit_mode): ?>
                            <select name="payment_status">
                                <option value="Cash" <?php if($head['payment_status']=='Cash') echo 'selected'; ?>>Cash</option>
                                <option value="Paid" <?php if($head['payment_status']=='Paid') echo 'selected'; ?>>Paid</option>
                                <option value="Pending" <?php if($head['payment_status']=='Pending') echo 'selected'; ?>>Pending</option>
                                <option value="Cancelled" <?php if($head['payment_status']=='Cancelled') echo 'selected'; ?>>Cancelled</option>
                            </select>
                        <?php else: ?>
                            <div class="value" style="color: #16a34a; font-weight: bold;"><?php echo $head['payment_status']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if($head['updater_name']): ?>
            <div class="audit-box">
                <strong>📝 Last Updated By:</strong> <?php echo $head['updater_name']; ?> <br>
                <strong>⏱️ Timestamp:</strong> <?php echo date('d/m/Y - H:i:s', strtotime($head['updated_at'])); ?>
            </div>
            <?php endif; ?>

            <h3 style="margin-top: 30px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">🛒 Order Items</h3>
            <table>
                <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
                <tbody>
                    <?php while($item = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $item['product_name']; ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>฿<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td>฿<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div style="text-align: right; margin-top: 20px; font-size: 1.2em;">
                <label style="display:inline; color: #475569;">Grand Total:</label>
                <?php if($edit_mode): ?>
                    <input type="number" step="0.01" name="grand_total" value="<?php echo $head['grand_total']; ?>" style="width: 200px; text-align:right; font-size: 1.2em; font-weight: bold; color: #16a34a;">
                <?php else: ?>
                    <span style="font-size: 1.5em; font-weight: bold; color: #16a34a; margin-left: 10px;">฿<?php echo number_format($head['grand_total'], 2); ?></span>
                <?php endif; ?>
            </div>

            <hr style="margin: 30px 0; border: 0; border-top: 1px solid #e2e8f0;">

            <div style="display: flex; justify-content: space-between;">
                <div>
                    <?php if(!$edit_mode): ?>
                        <a href="?id=<?php echo $id; ?>&action=soft_delete" class="btn btn-danger" onclick="return confirm('Move this invoice to Trash?')">🗑️ Move to Trash</a>
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <?php if($edit_mode): ?>
                        <a href="invoice_detail.php?id=<?php echo $id; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="update_invoice" class="btn btn-success">💾 Save Changes</button>
                    <?php else: ?>
                        <a href="invoice_detail.php?id=<?php echo $id; ?>&mode=edit" class="btn btn-warning">✏️ Edit Invoice Info</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

</body>
</html>