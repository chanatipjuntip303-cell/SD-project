<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

if (!isset($_GET['id'])) {
    die("Error: Document ID not found.");
}

$inv_id = (int)$_GET['id'];

// ดึงข้อมูลทั้งหมด รวมถึงคนรับเงิน (Join Employees 2 รอบ: คนออกบิล กับ คนรับเงิน)
$sql = "SELECT i.*, o.*, 
               c.contact_name, c.address as cust_address, 
               e.employee_name as issuer_name,
               r.employee_name as receiver_name
        FROM Invoices i
        JOIN Orders o ON i.order_id = o.order_id
        JOIN Customers c ON o.customer_id = c.customer_id
        LEFT JOIN Employees e ON i.issued_by = e.employee_id
        LEFT JOIN Employees r ON i.received_by = r.employee_id
        WHERE i.invoice_id = $inv_id";

$result = $conn->query($sql);
if ($result->num_rows == 0) { die("Error: Document not found."); }
$inv = $result->fetch_assoc();

// ดึงรายการสินค้า
$items = $conn->query("SELECT d.*, p.product_name 
                       FROM Order_Details d 
                       JOIN Products p ON d.product_id = p.product_id 
                       WHERE d.order_id = {$inv['order_id']}");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Document #INV-<?php echo str_pad($inv_id, 5, '0', STR_PAD_LEFT); ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap'); /* ใช้ฟอนต์สารบรรณให้ดูเป็นทางการ */
        
        body { 
            font-family: 'Sarabun', sans-serif; 
            font-size: 14px; line-height: 1.5; color: #333; 
            background: #e2e8f0; padding: 20px; 
            margin: 0;
        }
        .document-container { 
            max-width: 800px; margin: auto; background: white; 
            padding: 50px; border-radius: 4px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
        }
        
        /* Header */
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #1e293b; padding-bottom: 20px; margin-bottom: 30px; }
        .company-name { font-size: 1.8rem; font-weight: bold; color: #1e293b; margin: 0 0 5px 0; }
        .doc-title { font-size: 1.5rem; font-weight: bold; color: #2563eb; letter-spacing: 1px; text-transform: uppercase;}
        
        /* Info Grid */
        .info-grid { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .info-box { width: 48%; }
        .info-label { font-weight: 700; color: #1e293b; font-size: 0.9rem; border-bottom: 1px solid #cbd5e1; margin-bottom: 5px; padding-bottom: 3px; }
        
        /* Table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f1f5f9; border-top: 2px solid #1e293b; border-bottom: 2px solid #1e293b; padding: 10px; text-align: left; font-size: 0.9rem;}
        td { padding: 10px; border-bottom: 1px dashed #cbd5e1; }
        
        /* Totals */
        .summary-container { display: flex; justify-content: space-between; margin-top: 20px; }
        .payment-details { width: 55%; background: #f8fafc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.9rem; }
        .totals-box { width: 40%; text-align: right; }
        .total-row { display: flex; justify-content: space-between; padding: 5px 0; }
        .grand-total { font-size: 1.4rem; font-weight: bold; color: #1e293b; border-top: 2px solid #1e293b; border-bottom: 2px double #1e293b; padding: 10px 0; margin-top: 5px; }
        
        /* Signatures */
        .signatures { display: flex; justify-content: space-between; margin-top: 70px; text-align: center; }
        .sig-line { width: 200px; border-top: 1px solid #333; margin: 0 auto 10px auto; }
        .sig-box { width: 30%; font-size: 0.9rem; }

        /* Print Controls */
        .no-print { text-align: right; margin-bottom: 20px; max-width: 800px; margin-left: auto; margin-right: auto; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .btn-print { background: #2563eb; color: white; }
        .btn-back { background: #64748b; color: white; margin-left: 10px; text-decoration: none; }

        @media print {
            body { background: white; padding: 0; }
            .document-container { box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn btn-print">Print / Save as PDF</button>
    <a href="view_invoices.php" class="btn btn-back">Back</a>
</div>

<div class="document-container">
    
    <div class="header">
        <div>
            <h1 class="company-name">SD PROJECT COMPANY LIMITED</h1>
            <div style="color: #475569; font-size: 0.9rem;">
                123 Business Road, Chiang Mai, Thailand 50000<br>
                Tax ID: 01055xxxxxxxx | Tel: 053-XXX-XXX
            </div>
        </div>
        <div style="text-align: right;">
            <div class="doc-title">
                <?php 
                    if($inv['invoice_type'] == 'Standard' && $inv['payment_status'] == 'Pending') {
                        echo "INVOICE / ใบแจ้งหนี้";
                    } else {
                        echo "RECEIPT / ใบเสร็จรับเงิน";
                    }
                ?>
            </div>
            <table style="margin-top: 15px; width: 250px; float: right; font-size: 0.9rem;">
                <tr><td style="padding: 2px; border:none; text-align:left;"><strong>Doc No:</strong></td><td style="padding: 2px; border:none; text-align:right;">INV-<?php echo str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT); ?></td></tr>
                <tr><td style="padding: 2px; border:none; text-align:left;"><strong>Date:</strong></td><td style="padding: 2px; border:none; text-align:right;"><?php echo date('d M Y', strtotime($inv['invoice_date'])); ?></td></tr>
                <tr><td style="padding: 2px; border:none; text-align:left;"><strong>Ref Order:</strong></td><td style="padding: 2px; border:none; text-align:right;"><?php echo $inv['po_ref']; ?></td></tr>
            </table>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div class="info-label">BILLED TO / นามลูกค้า:</div>
            <strong><?php echo $inv['contact_name']; ?></strong><br>
            <div style="color: #475569; margin-top: 5px;">
                <?php echo nl2br($inv['cust_address']); ?>
            </div>
        </div>
        <div class="info-box">
            <div class="info-label">DOCUMENT STATUS / สถานะ:</div>
            <?php if($inv['payment_status'] == 'Paid'): ?>
                <h2 style="color: #166534; margin: 5px 0 0 0; letter-spacing: 2px;">PAID</h2>
            <?php else: ?>
                <h2 style="color: #ea580c; margin: 5px 0 0 0; letter-spacing: 2px;">PENDING</h2>
            <?php endif; ?>
            <div style="color: #475569; margin-top: 5px; font-size: 0.9rem;">
                <strong>Salesperson:</strong> <?php echo $inv['issuer_name'] ? $inv['issuer_name'] : 'System Auto'; ?>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No.</th>
                <th style="width: 50%;">Description / รายการ</th>
                <th style="width: 15%; text-align: center;">Qty</th>
                <th style="width: 15%; text-align: right;">Unit Price</th>
                <th style="width: 15%; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            while($item = $items->fetch_assoc()): 
            ?>
            <tr>
                <td style="text-align: center;"><?php echo $i++; ?></td>
                <td><?php echo $item['product_name']; ?></td>
                <td style="text-align: center;"><?php echo $item['qty']; ?></td>
                <td style="text-align: right;"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td style="text-align: right;"><?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="summary-container">
        
        <div class="payment-details">
            <div class="info-label" style="border-bottom:none; color:#2563eb;">PAYMENT DETAILS / ข้อมูลการชำระเงิน</div>
            <?php if($inv['payment_status'] == 'Paid'): ?>
                <table style="margin-bottom:0; font-size:0.9rem;">
                    <tr><td style="padding:3px; border:none; width: 40%;"><strong>Method:</strong></td><td style="padding:3px; border:none;"><?php echo $inv['payment_method']; ?></td></tr>
                    <tr><td style="padding:3px; border:none;"><strong>Date/Time:</strong></td><td style="padding:3px; border:none;"><?php echo date('d M Y, H:i', strtotime($inv['payment_date'])); ?></td></tr>
                    <tr><td style="padding:3px; border:none;"><strong>Transaction ID:</strong></td><td style="padding:3px; border:none;"><?php echo $inv['transaction_id']; ?></td></tr>
                    <tr><td style="padding:3px; border:none;"><strong>Amount Rcvd:</strong></td><td style="padding:3px; border:none; font-weight:bold; color:#166534;">฿<?php echo number_format($inv['amount_received'], 2); ?></td></tr>
                </table>
            <?php else: ?>
                <div style="color: #94a3b8; margin-top: 10px; font-style: italic;">
                    No payment received yet. Please make payment referring to Document No. INV-<?php echo str_pad($inv['invoice_id'], 5, '0', STR_PAD_LEFT); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="totals-box">
            <div class="total-row">
                <span style="color:#64748b;">Subtotal:</span>
                <span>฿<?php echo number_format($inv['total_amount'], 2); ?></span>
            </div>
            <div class="total-row" style="color: #ea580c;">
                <span>Discount:</span>
                <span>-฿<?php echo number_format($inv['discount_amount'], 2); ?></span>
            </div>
            <div class="total-row grand-total">
                <span>Net Total:</span>
                <span>฿<?php echo number_format($inv['net_total'], 2); ?></span>
            </div>
            <div style="text-align: center; color: #64748b; font-size: 0.8rem; margin-top: 5px;">
                ( All prices are in THB )
            </div>
        </div>

    </div>

    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line"></div>
            <strong>Prepared By</strong><br>
            <span style="color:#64748b;"><?php echo $inv['issuer_name'] ? $inv['issuer_name'] : 'System Auto'; ?></span>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <strong>Received By (Cashier)</strong><br>
            <span style="color:#64748b;"><?php echo $inv['receiver_name'] ? $inv['receiver_name'] : '_____________________'; ?></span>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <strong>Authorized Signature</strong><br>
            <span style="color:#64748b;">Manager / Director</span>
        </div>
    </div>

</div>

</body>
</html>