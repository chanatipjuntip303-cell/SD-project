<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

if (!isset($_GET['id'])) {
    die("Error: Invoice ID not found.");
}

$inv_id = (int)$_GET['id'];

// ดึงข้อมูล Invoice + Order + Customer + Employee
$sql = "SELECT i.*, o.*, c.contact_name, c.address as cust_address, e.employee_name 
        FROM Invoices i
        JOIN Orders o ON i.order_id = o.order_id
        JOIN Customers c ON o.customer_id = c.customer_id
        LEFT JOIN Employees e ON i.issued_by = e.employee_id
        WHERE i.invoice_id = $inv_id";

$result = $conn->query($sql);
if ($result->num_rows == 0) { die("Error: Invoice not found."); }
$inv = $result->fetch_assoc();

// ดึงรายการสินค้าในบิลนี้
$items = $conn->query("SELECT d.*, p.product_name 
                       FROM Order_Details d 
                       JOIN Products p ON d.product_id = p.product_id 
                       WHERE d.order_id = {$inv['order_id']}");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Invoice #<?php echo $inv_id; ?></title>
    <style>
        body { font-family: 'Tahoma', sans-serif; font-size: 14px; line-height: 1.6; color: #333; background: #f5f5f5; padding: 20px; }
        .receipt-box { max-width: 800px; margin: auto; background: white; padding: 40px; border: 1px solid #ddd; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 20px; }
        .company-info h2 { color: #2563eb; margin: 0; }
        
        /* ✅ ป้ายบอกประเภทบิล */
        .inv-type-tag { display: inline-block; padding: 5px 15px; border-radius: 5px; font-weight: bold; font-size: 1.2rem; margin-top: 10px; }
        .type-std { background: #dbeafe; color: #1e40af; border: 1px solid #1e40af; }
        .type-dir { background: #dcfce7; color: #166534; border: 1px solid #166534; }

        .info-section { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 30px; }
        .info-title { font-weight: bold; color: #64748b; text-transform: uppercase; font-size: 0.8rem; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
        
        .total-section { float: right; width: 300px; }
        .total-row { display: flex; justify-content: space-between; padding: 5px 0; }
        .grand-total { border-top: 2px solid #2563eb; margin-top: 10px; padding-top: 10px; font-size: 1.5rem; font-weight: bold; color: #2563eb; }
        
        .footer { clear: both; margin-top: 50px; text-align: center; color: #94a3b8; font-size: 0.8rem; }

        @media print {
            body { background: white; padding: 0; }
            .receipt-box { border: none; box-shadow: none; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print" style="max-width: 800px; margin: 0 auto 20px; text-align: right;">
    <button onclick="window.print()" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">🖨️ Print Receipt / PDF</button>
    <button onclick="window.history.back()" style="padding: 10px 20px; background: #64748b; color: white; border: none; border-radius: 5px; cursor: pointer;">Back</button>
</div>

<div class="receipt-box">
    <div class="header">
        <div class="company-info">
            <h2>SD PROJECT SHOP</h2>
            <p>123 Store Address Road, City, 10xxx<br>Phone: 02-xxx-xxxx</p>
        </div>
        <div style="text-align: right;">
            <div class="inv-type-tag <?php echo ($inv['invoice_type'] == 'Standard') ? 'type-std' : 'type-dir'; ?>">
                <?php echo ($inv['invoice_type'] == 'Standard') ? 'STANDARD INVOICE' : 'DIRECT SALE RECEIPT'; ?>
            </div>
            <p style="margin-top: 10px;">
                <strong>Invoice No:</strong> #INV-<?php echo str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT); ?><br>
                <strong>Date:</strong> <?php echo date('d F Y', strtotime($inv['invoice_date'])); ?>
            </p>
        </div>
    </div>

    <div class="info-section">
        <div>
            <div class="info-title">Bill To:</div>
            <strong><?php echo $inv['contact_name']; ?></strong><br>
            <?php echo nl2br($inv['cust_address']); ?>
        </div>
        <div style="text-align: right;">
            <div class="info-title">Payment Status:</div>
            <strong style="color: <?php echo ($inv['payment_status'] == 'Paid') ? '#10b981' : '#ea580c'; ?>; font-size: 1.2rem;">
                <?php echo strtoupper($inv['payment_status']); ?>
            </strong><br>
            <small>Ref Order: <?php echo $inv['po_ref']; ?></small><br>
            <small>Salesperson: <?php echo $inv['employee_name']; ?></small>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item Description</th>
                <th style="text-align: right;">Price</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php while($item = $items->fetch_assoc()): ?>
            <tr>
                <td><?php echo $item['product_name']; ?></td>
                <td style="text-align: right;">฿<?php echo number_format($item['unit_price'], 2); ?></td>
                <td style="text-align: center;"><?php echo $item['qty']; ?></td>
                <td style="text-align: right;">฿<?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>฿<?php echo number_format($inv['total_amount'], 2); ?></span>
        </div>
        <div class="total-row" style="color: #ea580c;">
            <span>Discount:</span>
            <span>-฿<?php echo number_format($inv['discount_amount'], 2); ?></span>
        </div>
        <div class="total-row grand-total">
            <span>Amount Paid:</span>
            <span>฿<?php echo number_format($inv['net_total'], 2); ?></span>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>This is a computer-generated document.</p>
    </div>
</div>

</body>
</html>