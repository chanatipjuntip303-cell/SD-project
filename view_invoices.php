<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

// --- Logic จัดการ Soft Delete / Restore / Permanent Delete ---
if (isset($_GET['soft_delete'])) {
    $id = $_GET['soft_delete'];
    // บันทึกคนลบ และเวลาที่ลบ (NOW())
    $conn->query("UPDATE Invoices SET is_deleted = 1, updated_by = $current_user_id, updated_at = NOW() WHERE Invoice_id = $id");
    header("Location: view_invoices.php");
    exit();
}

if (isset($_GET['restore'])) {
    $id = $_GET['restore'];
    // บันทึกคนกู้คืน และเวลาที่กู้คืน
    $conn->query("UPDATE Invoices SET is_deleted = 0, updated_by = $current_user_id, updated_at = NOW() WHERE Invoice_id = $id");
    header("Location: view_invoices.php");
    exit();
}

if (isset($_GET['perm_delete'])) {
    $id = $_GET['perm_delete'];
    $conn->query("DELETE FROM Invoices WHERE Invoice_id = $id");
    header("Location: view_invoices.php");
    exit();
}

// 1. ดึงบิลปกติ (Active) - JOIN หาคนสร้าง(พนักงานขาย) และคนแก้(updater)
$sql_active = "SELECT i.*, o.PO_reference, c.contact_name, 
                      e.employee_name AS creator_name, 
                      u.employee_name AS updater_name 
               FROM Invoices i
               JOIN Orders o ON i.Orders_Order_ID = o.Order_ID
               JOIN Customers c ON o.Customers_customer_id = c.customer_id
               JOIN Employees e ON o.Employees_employee_ID = e.employee_ID
               LEFT JOIN Employees u ON i.updated_by = u.employee_ID
               WHERE i.is_deleted = 0
               ORDER BY i.Invoice_id DESC";
$active_invoices = $conn->query($sql_active);

// 2. ดึงบิลในถังขยะ (Trash)
$sql_trash = "SELECT i.*, o.PO_reference, c.contact_name, 
                     e.employee_name AS creator_name, 
                     u.employee_name AS updater_name 
              FROM Invoices i
              JOIN Orders o ON i.Orders_Order_ID = o.Order_ID
              JOIN Customers c ON o.Customers_customer_id = c.customer_id
              JOIN Employees e ON o.Employees_employee_ID = e.employee_ID
              LEFT JOIN Employees u ON i.updated_by = u.employee_ID
              WHERE i.is_deleted = 1
              ORDER BY i.Invoice_id DESC";
$trash_invoices = $conn->query($sql_trash);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice List & Recycle Bin</title>
    <link rel="stylesheet" href="style.css"> 
    <style>
        .audit-tag { font-size: 0.8em; color: #64748b; background: #f8fafc; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0; display: inline-block; margin-bottom: 2px;}
        .status-badge { padding: 4px 8px; border-radius: 12px; font-weight: bold; font-size: 0.8em; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef08a; color: #854d0e; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
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
        <h1>📄 Invoice Management</h1>
        <a href="create_order.php" class="btn btn-success">+ New Sale Order</a>
    </div>

    <div class="card">
        <h2>Active Invoices</h2>
        <table>
            <thead>
                <tr>
                    <th>Inv. ID</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Audit Trail (Logs)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $active_invoices->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo str_pad($row['Invoice_id'], 5, '0', STR_PAD_LEFT); ?><br><small><?php echo date('d/m/Y', strtotime($row['billing_date'])); ?></small></td>
                    <td><strong><?php echo $row['contact_name']; ?></strong><br><small>PO: <?php echo $row['PO_reference']; ?></small></td>
                    <td>฿<?php echo number_format($row['grand_total'], 2); ?></td>
                    <td>
                        <?php 
                            $status_class = 'status-pending';
                            if($row['payment_status'] == 'Paid' || $row['payment_status'] == 'Cash') $status_class = 'status-paid';
                            if($row['payment_status'] == 'Cancelled') $status_class = 'status-cancelled';
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $row['payment_status']; ?></span>
                    </td>
                    <td>
                        <div class="audit-tag">✨ Created: <?php echo $row['creator_name']; ?></div><br>
                        <?php if($row['updater_name']): ?>
                            <div class="audit-tag" style="color: #d97706; border-color: #fde68a; background: #fef3c7;">
                                ✏️ Edited: <?php echo $row['updater_name']; ?> <br>
                                ⏱️ On: <?php echo date('d/m/Y H:i', strtotime($row['updated_at'])); ?>
                            </div>
                        <?php else: ?>
                            <div class="audit-tag" style="color: #94a3b8;">✏️ Edited: -</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="invoice_detail.php?id=<?php echo $row['Invoice_id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8em;">👁️ Detail / Edit</a>
                        <a href="?soft_delete=<?php echo $row['Invoice_id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8em;" onclick="return confirm('Move to Trash?')">🗑️ Trash</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="border-top: 5px solid #ef4444;">
        <h3 style="color: #ef4444;">🗑️ Recycle Bin (Deleted Invoices)</h3>
        <table>
            <thead>
                <tr>
                    <th>Inv. ID</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Deleted By</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($trash_invoices->num_rows == 0): ?>
                    <tr><td colspan="5" style="text-align:center; color:#999;">Trash is empty</td></tr>
                <?php endif; ?>
                
                <?php while($row = $trash_invoices->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $row['Invoice_id']; ?></td>
                    <td><?php echo $row['contact_name']; ?></td>
                    <td>฿<?php echo number_format($row['grand_total'], 2); ?></td>
                    <td>
                        <span class="audit-tag" style="color: #dc2626; border-color: #fca5a5; background: #fee2e2;">
                            🗑️ By: <?php echo $row['updater_name'] ? $row['updater_name'] : '-'; ?><br>
                            ⏱️ On: <?php echo $row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-'; ?>
                        </span>
                    </td>
                    <td>
                        <a href="?restore=<?php echo $row['Invoice_id']; ?>" class="btn btn-success" style="padding: 5px 10px; font-size: 0.8em;">♻️ Restore</a>
                        <a href="?perm_delete=<?php echo $row['Invoice_id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8em;" onclick="return confirm('Delete Permanently?')">❌ Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>