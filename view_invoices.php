<?php
session_start();
include 'db_connect.php';

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$uid = $_SESSION['user_id'];

// --- 2. Action Logic (รับชำระเงินผ่าน Modal) ---
if (isset($_POST['submit_payment'])) {
    $inv_id = (int)$_POST['invoice_id'];
    $pay_date = $conn->real_escape_string($_POST['payment_date']);
    $trans_id = $conn->real_escape_string($_POST['transaction_id']);
    $amount = (float)$_POST['amount_received'];
    
    $sql_update = "UPDATE Invoices SET 
                   payment_status = 'Paid', payment_method = 'Cash', 
                   payment_date = '$pay_date', transaction_id = '$trans_id', 
                   amount_received = $amount, received_by = $uid 
                   WHERE invoice_id = $inv_id";
                   
    if($conn->query($sql_update)){
        echo "<script>alert('✅ Payment Received & Recorded Successfully!'); window.location='view_invoices.php?tab=standard';</script>";
    }
}

// --- 3. Filter & Search Logic ---
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = "1=1";

// 3.1 กรองตาม Tab
if ($tab == 'pos') {
    $where_clause .= " AND i.invoice_type = 'Direct'";
} elseif ($tab == 'standard') {
    $where_clause .= " AND i.invoice_type = 'Standard'";
}

// 3.2 กรองตาม Search Box (หาจากชื่อลูกค้า, เลขออเดอร์, หรือเลขบิล)
if ($search !== '') {
    $safe_search = $conn->real_escape_string($search);
    $where_clause .= " AND (
                        c.contact_name LIKE '%$safe_search%' 
                        OR o.po_ref LIKE '%$safe_search%' 
                        OR i.invoice_id LIKE '%$safe_search%' 
                        OR CONCAT('INV-', LPAD(i.invoice_id, 5, '0')) LIKE '%$safe_search%'
                    )";
}

// --- 4. Fetch Data ---
$sql = "SELECT i.*, o.po_ref, o.net_total, c.contact_name, 
               e.employee_name as issuer_name, r.employee_name as receiver_name
        FROM Invoices i
        JOIN Orders o ON i.order_id = o.order_id
        JOIN Customers c ON o.customer_id = c.customer_id
        LEFT JOIN Employees e ON i.issued_by = e.employee_id
        LEFT JOIN Employees r ON i.received_by = r.employee_id
        WHERE $where_clause
        ORDER BY i.invoice_id DESC";

$invoices = $conn->query($sql);
$pending_count = $conn->query("SELECT COUNT(*) as cnt FROM Invoices WHERE payment_status = 'Pending'")->fetch_assoc()['cnt'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoices & Payments</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .header-controls { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .tab-menu { display: flex; gap: 10px; }
        .tab-btn { padding: 10px 20px; border-radius: 8px 8px 0 0; font-weight: bold; color: #64748b; background: transparent; transition: 0.2s; text-decoration: none;}
        .tab-btn:hover { background: #f1f5f9; color: #1e293b; }
        .tab-btn.active { background: #2563eb; color: white; }

        .search-box { display: flex; gap: 10px; }
        .search-input { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; width: 250px; }

        .badge-pending { background: #fef08a; color: #854d0e; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 0.85em; }
        .badge-paid { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 0.85em; }
        .type-std { border-left: 4px solid #3b82f6; padding-left: 8px; color: #3b82f6; font-weight: bold; }
        .type-dir { border-left: 4px solid #10b981; padding-left: 8px; color: #10b981; font-weight: bold; }
        .audit-tag { font-size: 0.75em; color: #64748b; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; border: 1px solid #e2e8f0;}
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;}
        .modal-content { background: white; width: 400px; margin: 80px auto; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
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
        <h1 style="color: #2563eb;">Invoices & Payments</h1>
        <p style="color: #64748b;">Manage billing, collect payments, and search transactions.</p>
    </div>

    <div class="header-controls">
        <div class="tab-menu">
            <a href="?tab=all&search=<?php echo urlencode($search); ?>" class="tab-btn <?php echo $tab == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?tab=pos&search=<?php echo urlencode($search); ?>" class="tab-btn <?php echo $tab == 'pos' ? 'active' : ''; ?>">POS</a>
            <a href="?tab=standard&search=<?php echo urlencode($search); ?>" class="tab-btn <?php echo $tab == 'standard' ? 'active' : ''; ?>">
                Standard 
                <?php if($pending_count > 0): ?>
                    <span style="background: #ef4444; color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.75rem; margin-left: 5px;"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <form method="GET" class="search-box">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
            <input type="text" name="search" class="search-input" placeholder="Search Customer, Ref, or INV..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary" style="padding: 10px 15px;">Search</button>
            <?php if($search != ''): ?>
                <a href="view_invoices.php?tab=<?php echo $tab; ?>" class="btn btn-secondary" style="padding: 10px 15px; text-decoration: none;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <table style="width: 100%; text-align: left;">
            <thead>
                <tr>
                    <th>Invoice No.</th>
                    <th>Type</th>
                    <th>Reference No.</th> <th>Customer</th>
                    <th>Amount Due</th>
                    <th>Status & Audit</th>
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
                            <?php if($inv['invoice_type'] == 'Standard'): ?>
                                <small style="color: #94a3b8;">PO Ref:</small><br>
                                <span style="color: #475569; font-weight: bold;"><?php echo $inv['po_ref']; ?></span>
                            <?php else: ?>
                                <small style="color: #94a3b8;">Receipt No:</small><br>
                                <span style="color: #475569; font-weight: bold;"><?php echo $inv['po_ref']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo $inv['contact_name']; ?></strong><br>
                            <small style="color: #94a3b8;">Issued By: <?php echo $inv['issuer_name'] ? $inv['issuer_name'] : 'System Auto'; ?></small>
                        </td>
                        <td style="font-size: 1.1em; font-weight: bold; color: #1e293b;">
                            ฿<?php echo number_format($inv['net_total'], 2); ?>
                        </td>
                        <td>
                            <?php if($inv['payment_status'] == 'Pending'): ?>
                                <span class="badge-pending">Pending Payment</span>
                            <?php else: ?>
                                <span class="badge-paid">Paid</span><br>
                                <?php if($inv['receiver_name']): ?>
                                    <div class="audit-tag">Rcvd by: <?php echo $inv['receiver_name']; ?></div><br>
                                    <small style="color: #94a3b8;">Ref: <?php echo $inv['transaction_id']; ?></small>
                                <?php else: ?>
                                    <div class="audit-tag">Rcvd by: POS (Auto)</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($inv['payment_status'] == 'Pending'): ?>
                                <button onclick="openPaymentModal(<?php echo $inv['invoice_id']; ?>, <?php echo $inv['net_total']; ?>, 'INV-<?php echo str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT); ?>')" class="btn btn-success" style="padding: 6px 12px; margin-bottom: 5px;">Receive Payment</button>
                                <br>
                            <?php else: ?>
                                <a href="print_receipt.php?id=<?php echo $inv['invoice_id']; ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 12px; display: inline-block;">Print Receipt</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center; color: #64748b; padding: 30px;">
                        <?php echo ($search != '') ? 'No invoices found matching "<b>' . htmlspecialchars($search) . '</b>"' : 'No invoices found in this category.'; ?>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="paymentModal" class="modal">
    <div class="modal-content">
        <h2 style="color: #166534; border-bottom: 2px solid #dcfce7; padding-bottom: 10px;">💰 Receive Payment</h2>
        <p style="color: #64748b; font-size: 0.9rem;">Invoice: <strong id="disp_inv_no"></strong></p>
        <form method="POST">
            <input type="hidden" name="invoice_id" id="pay_inv_id">
            <label>Payment Method:</label>
            <input type="text" value="Cash" readonly style="width:100%; margin-bottom:15px; padding:10px; background:#f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; color: #475569; font-weight: bold; cursor: not-allowed;">
            <label>Payment Date & Time:</label>
            <input type="datetime-local" name="payment_date" required style="width:100%; margin-bottom:15px; padding:10px; border: 1px solid #cbd5e1; border-radius: 6px;">
            <label>Transaction ID / Ref No:</label>
            <input type="text" name="transaction_id" required placeholder="e.g. REC-00123" style="width:100%; margin-bottom:15px; padding:10px; border: 1px solid #cbd5e1; border-radius: 6px;">
            <label>Amount Received (฿):</label>
            <input type="number" step="0.01" name="amount_received" id="pay_amount" readonly style="width:100%; margin-bottom:20px; padding:10px; background:#f0fdf4; border: 1px solid #86efac; border-radius: 6px; font-weight:bold; color:#166534; font-size: 1.2rem; cursor: not-allowed;">
            <button type="submit" name="submit_payment" class="btn btn-success" style="width:100%; padding: 12px; font-size: 1.1rem;" onclick="return confirm('Confirm payment details?')">Confirm Payment</button>
            <button type="button" onclick="closeModal('paymentModal')" class="btn btn-secondary" style="width:100%; margin-top:10px; padding: 12px;">Cancel</button>
        </form>
    </div>
</div>

<script>
function openPaymentModal(inv_id, amount, inv_no) {
    document.getElementById('pay_inv_id').value = inv_id; document.getElementById('disp_inv_no').innerText = inv_no; document.getElementById('pay_amount').value = amount.toFixed(2);
    let now = new Date(); now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementsByName('payment_date')[0].value = now.toISOString().slice(0,16);
    document.getElementById('paymentModal').style.display = 'block';
}
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = function(event) { if (event.target.classList.contains('modal')) { event.target.style.display = "none"; } }
</script>

</body>
</html>