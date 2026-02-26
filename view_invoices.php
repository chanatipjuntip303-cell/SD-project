<?php
session_start();
include 'db_connect.php';

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$uid = $_SESSION['user_id'];

// --- 2. Action Logic (รับชำระเงิน) ---
if (isset($_GET['mark_paid'])) {
    $inv_id = (int)$_GET['mark_paid'];
    
    // เปลี่ยนสถานะบิลเป็น Paid (จ่ายแล้ว)
    $conn->query("UPDATE Invoices SET payment_status = 'Paid' WHERE invoice_id = $inv_id");
    
    echo "<script>alert('✅ Payment Received Successfully!'); window.location='view_invoices.php?tab=standard';</script>";
}

// --- 3. Filter Logic (ระบบหมวดหมู่) ---
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$where_clause = "1=1"; // ค่าเริ่มต้น: ดึงทั้งหมด

if ($tab == 'pos') {
    $where_clause = "i.invoice_type = 'Direct'";
} elseif ($tab == 'standard') {
    $where_clause = "i.invoice_type = 'Standard'";
}

// --- 4. Fetch Data ---
$sql = "SELECT i.*, 
               o.po_ref, o.net_total, 
               c.contact_name, 
               e.employee_name as issuer_name
        FROM Invoices i
        JOIN Orders o ON i.order_id = o.order_id
        JOIN Customers c ON o.customer_id = c.customer_id
        LEFT JOIN Employees e ON i.issued_by = e.employee_id
        WHERE $where_clause
        ORDER BY i.invoice_id DESC";

$invoices = $conn->query($sql);

// นับจำนวนบิลที่รอจ่ายเงิน (เฉพาะ Standard) เพื่อโชว์แจ้งเตือน
$pending_count = $conn->query("SELECT COUNT(*) as cnt FROM Invoices WHERE payment_status = 'Pending'")->fetch_assoc()['cnt'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoices</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* สไตล์สำหรับ Tabs หมวดหมู่ */
        .tab-menu { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .tab-btn { padding: 10px 20px; border-radius: 8px 8px 0 0; font-weight: bold; color: #64748b; background: transparent; transition: 0.2s; }
        .tab-btn:hover { background: #f1f5f9; color: #1e293b; }
        .tab-btn.active { background: #2563eb; color: white; }

        /* สไตล์ป้ายต่างๆ */
        .badge-pending { background: #fef08a; color: #854d0e; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 0.85em; }
        .badge-paid { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 0.85em; }
        
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
        <a href="manage_orders.php">Manage Orders</a>
        <a href="view_invoices.php" class="active">Invoices</a>
    </div>
</nav>

<div class="container">
    <div style="margin-bottom: 20px;">
        <h1 style="color: #2563eb;">📄 Invoices & Payments</h1>
        <p style="color: #64748b;">Manage billing and collect payments for shipped orders.</p>
    </div>

    <div class="tab-menu">
        <a href="?tab=all" class="tab-btn <?php echo $tab == 'all' ? 'active' : ''; ?>">
            📂 All Invoices
        </a>
        <a href="?tab=pos" class="tab-btn <?php echo $tab == 'pos' ? 'active' : ''; ?>">
            🛒 POS Invoices
        </a>
        <a href="?tab=standard" class="tab-btn <?php echo $tab == 'standard' ? 'active' : ''; ?>">
            📦 Standard Invoices 
            <?php if($pending_count > 0): ?>
                <span style="background: #ef4444; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Invoice No.</th>
                    <th>Type</th>
                    <th>Ref Order</th>
                    <th>Customer</th>
                    <th>Amount Due</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($invoices->num_rows > 0): ?>
                    <?php while($inv = $invoices->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong>INV-<?php echo str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT); ?></strong><br>
                            <small style="color: #94a3b8;"><?php echo date('d/m/Y H:i', strtotime($inv['invoice_date'])); ?></small>
                        </td>
                        <td>
                            <?php if($inv['invoice_type'] == 'Standard'): ?>
                                <span class="type-std">Standard</span>
                            <?php else: ?>
                                <span class="type-dir">POS Direct</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color: #64748b;"><?php echo $inv['po_ref']; ?></span>
                        </td>
                        <td>
                            <strong><?php echo $inv['contact_name']; ?></strong><br>
                            <small style="color: #94a3b8;">Issued By: <?php echo $inv['issuer_name'] ? $inv['issuer_name'] : 'Auto System'; ?></small>
                        </td>
                        <td style="font-size: 1.1em; font-weight: bold; color: #1e293b;">
                            ฿<?php echo number_format($inv['net_total'], 2); ?>
                        </td>
                        <td>
                            <?php if($inv['payment_status'] == 'Pending'): ?>
                                <span class="badge-pending">⏳ Pending Payment</span>
                            <?php else: ?>
                                <span class="badge-paid">✅ Paid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($inv['payment_status'] == 'Pending'): ?>
                                <a href="?mark_paid=<?php echo $inv['invoice_id']; ?>" class="btn btn-success" style="padding: 6px 12px; margin-bottom: 5px; display: inline-block;" onclick="return confirm('Confirm receipt of payment for INV-<?php echo str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT); ?>?')">💰 Receive Payment</a>
                                <br>
                            <?php else: ?>
                                <a href="print_receipt.php?id=<?php echo $inv['invoice_id']; ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 12px; display: inline-block;">🖨️ Print Receipt</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #64748b; padding: 20px;">No invoices found in this category.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>