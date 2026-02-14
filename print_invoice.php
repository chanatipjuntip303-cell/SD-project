<?php
include 'db_connect.php';

if (!isset($_GET['id'])) die("Invoice ID not found.");
$inv_id = $_GET['id'];

// 1. ดึงข้อมูลหัวบิล (Header)
$sql_head = "SELECT i.*, o.PO_reference, o.order_date, c.contact_name, c.address, e.employee_name
             FROM Invoices i
             JOIN Orders o ON i.Orders_Order_ID = o.Order_ID
             JOIN Customers c ON o.Customers_customer_id = c.customer_id
             JOIN Employees e ON o.Employees_employee_ID = e.employee_ID
             WHERE i.Invoice_id = $inv_id";
$head = $conn->query($sql_head)->fetch_assoc();

// 2. ดึงรายการสินค้า (Items)
$sql_items = "SELECT d.*, p.product_name 
              FROM Order_details d
              JOIN Products p ON d.Products_product_ID = p.product_ID
              WHERE d.Orders_Order_ID = " . $head['Orders_Order_ID'];
$items = $conn->query($sql_items);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $inv_id; ?></title>
    <style>
        body { font-family: 'Helvetica', sans-serif; background: #555; padding: 20px; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; background: white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); }
        .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .company-info h2 { margin: 0; color: #333; }
        .invoice-details { text-align: right; }
        
        table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; margin-top: 20px; }
        table th { background: #eee; border-bottom: 2px solid #ddd; padding: 10px; }
        table td { padding: 10px; border-bottom: 1px solid #eee; }
        
        .total-section { margin-top: 30px; text-align: right; }
        .total-row { display: flex; justify-content: flex-end; margin-bottom: 5px; }
        .total-label { width: 200px; font-weight: bold; }
        .total-value { width: 150px; }
        
        .footer { margin-top: 50px; text-align: center; color: #777; font-size: 0.8em; border-top: 1px solid #eee; padding-top: 20px; }
        
        @media print {
            body { background: white; }
            .no-print { display: none; }
            .invoice-box { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>

<div class="invoice-box">
    <div class="header">
        <div class="company-info">
            <h2>SD Project Shop Co., Ltd.</h2>
            <p>123 University Road, Bangkok<br>Tax ID: 010555555555</p>
        </div>
        <div class="invoice-details">
            <h1>INVOICE / RECEIPT</h1>
            <p><strong>Invoice #:</strong> <?php echo str_pad($head['Invoice_id'], 5, '0', STR_PAD_LEFT); ?></p>
            <p><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($head['billing_date'])); ?></p>
            <p><strong>PO Ref:</strong> <?php echo $head['PO_reference']; ?></p>
        </div>
    </div>

    <hr>

    <div style="display: flex; justify-content: space-between; margin: 20px 0;">
        <div>
            <strong>Bill To:</strong><br>
            <?php echo $head['contact_name']; ?><br>
            <?php echo $head['address']; ?>
        </div>
        <div style="text-align: right;">
            <strong>Salesperson:</strong> <?php echo $head['employee_name']; ?><br>
            <strong>Payment Method:</strong> Cash Only
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item Description</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Unit Price</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $subtotal_calc = 0;
            while($item = $items->fetch_assoc()): 
                $row_total = $item['quantity'] * $item['unit_price'];
                $subtotal_calc += $row_total;
            ?>
            <tr>
                <td><?php echo $item['product_name']; ?></td>
                <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                <td style="text-align: right;">฿<?php echo number_format($item['unit_price'], 2); ?></td>
                <td style="text-align: right;">฿<?php echo number_format($row_total, 2); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-row">
            <span class="total-label">Subtotal:</span>
            <span class="total-value">฿<?php echo number_format($subtotal_calc, 2); ?></span>
        </div>
        
        <?php if($head['discount_special'] > 0): ?>
        <div class="total-row" style="color: #e74c3c;">
            <span class="total-label">Special Discount (10%):</span>
            <span class="total-value">-฿<?php echo number_format($head['discount_special'], 2); ?></span>
        </div>
        <?php endif; ?>

        <?php if($head['discount_member'] > 0): ?>
        <div class="total-row" style="color: #8e44ad;">
            <span class="total-label">Member Discount (5%):</span>
            <span class="total-value">-฿<?php echo number_format($head['discount_member'], 2); ?></span>
        </div>
        <?php endif; ?>

        <div class="total-row" style="font-size: 1.2em; margin-top: 10px;">
            <span class="total-label">Grand Total:</span>
            <span class="total-value" style="border-bottom: 3px double #333;">฿<?php echo number_format($head['grand_total'], 2); ?></span>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for your business!</p>
        <br><br>
        __________________________<br>
        Authorized Signature
    </div>

    <center class="no-print" style="margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #2c3e50; color: white; cursor: pointer;">Print Invoice</button>
    </center>
</div>

</body>
</html>