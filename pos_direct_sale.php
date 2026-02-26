<?php
session_start();
include 'db_connect.php';

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$uid = $_SESSION['user_id'];
$emp_name = $_SESSION['user_name'];

// --- 2. ดึงข้อมูล Default ---
// หา Customer ID ของ General Customer (Walk-in)
$gen_cust = $conn->query("SELECT customer_id, contact_name FROM Customers WHERE customer_id = 1 OR contact_name LIKE '%General%' LIMIT 1")->fetch_assoc();
$cust_id = $gen_cust['customer_id'] ?? 1;
$cust_name = $gen_cust['contact_name'] ?? 'General Customer (Walk-in)';

// ดึงสินค้าที่มีสต็อก > 0
$products = $conn->query("SELECT * FROM Products WHERE is_deleted = 0 AND stock_qty > 0 ORDER BY product_name ASC");
$product_list = [];
while($p = $products->fetch_assoc()) {
    $product_list[] = $p;
}

// --- 3. Save Order (POS Logic) ---
if (isset($_POST['submit_pos'])) {
    $cart_data = json_decode($_POST['cart_data'], true);
    $total_amount = $_POST['total_hidden'];
    $po_ref = "POS-" . date('Ymd-Hi');

    if (!empty($cart_data)) {
        $conn->begin_transaction(); // เริ่ม Transaction เพื่อความปลอดภัยของข้อมูล

        try {
            // 1. สร้าง Order (สถานะ Shipped เลย เพราะลูกค้าหยิบของไปแล้ว, ประเภท Direct)
            $sql_order = "INSERT INTO Orders (po_ref, customer_id, employee_id, total_amount, discount_amount, net_total, status, order_type) 
                          VALUES ('$po_ref', $cust_id, $uid, $total_amount, 0, $total_amount, 'Shipped', 'Direct')";
            $conn->query($sql_order);
            $order_id = $conn->insert_id;

            // 2. ลูปรายการสินค้า (Order Details + ตัดสต็อก + เก็บ Log)
            foreach ($cart_data as $item) {
                $p_id = $item['id'];
                $qty = $item['qty'];
                $price = $item['price'];
                $subtotal = $item['row_total'];

                // Insert รายละเอียด
                $conn->query("INSERT INTO Order_Details (order_id, product_id, qty, unit_price, subtotal) 
                              VALUES ($order_id, $p_id, $qty, $price, $subtotal)");

                // ตัดสต็อก ✂️
                $conn->query("UPDATE Products SET stock_qty = stock_qty - $qty WHERE product_id = $p_id");

                // บันทึก Audit Trail (Stock Logs)
                $conn->query("INSERT INTO Stock_Logs (product_id, qty_change, log_type, employee_id, related_order_id) 
                              VALUES ($p_id, -$qty, 'Sale', $uid, $order_id)");
            }

            // 3. สร้าง Invoice ทันที (สถานะ Paid, ประเภท Direct)
            $sql_inv = "INSERT INTO Invoices (order_id, payment_status, issued_by, invoice_type) 
                        VALUES ($order_id, 'Paid', $uid, 'Direct')";
            $conn->query($sql_inv);

            $conn->commit(); // ยืนยันข้อมูลทั้งหมด
            echo "<script>alert('✅ Payment Successful! Stock Deducted.'); window.location='view_invoices.php';</script>";

        } catch (Exception $e) {
            $conn->rollback(); // ถ้า Error ให้ยกเลิกทั้งหมด (ของไม่หาย เงินไม่มั่ว)
            echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
        }
    } else {
        echo "<script>alert('Cart is empty!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS - Direct Sale</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .pos-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .total-display { background: #1e293b; color: #10b981; font-size: 3rem; font-weight: bold; text-align: right; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .readonly-field { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 10px; border-radius: 6px; color: #64748b; font-weight: bold; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand">SD Project</a>
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="pos_direct_sale.php" class="active">POS (หน้าร้าน)</a>
        <a href="create_sale_order.php">Sales Order (สั่งทำ)</a>
        <a href="manage_orders.php">Manage Orders</a>
    </div>
</nav>

<div class="container">
    <div style="margin-bottom: 20px;">
        <h1 style="color: #2563eb;">🛒 Direct Sale (POS)</h1>
        <p style="color: #64748b;">Fast Checkout - No Discount - Instant Stock Deduction</p>
    </div>

    <div class="pos-grid">
        <div class="card">
            <div class="form-grid" style="align-items: end; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px;">
                <div style="grid-column: span 2;">
                    <label>Select Product (Scan/Search):</label>
                    <select id="product_select" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                        <option value="">-- Choose Item --</option>
                        <?php foreach($product_list as $p): ?>
                            <option value="<?php echo $p['product_id']; ?>" 
                                    data-price="<?php echo $p['price']; ?>" 
                                    data-stock="<?php echo $p['stock_qty']; ?>"
                                    data-name="<?php echo htmlspecialchars($p['product_name']); ?>">
                                <?php echo $p['product_name']; ?> (฿<?php echo number_format($p['price'], 2); ?>) - On Hand: <?php echo $p['stock_qty']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Qty:</label>
                    <input type="number" id="qty_input" value="1" min="1" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1;">
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="addToCart()" style="width: 100%; padding: 10px;">+ Add</button>
                </div>
            </div>

            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="cart_body">
                    </tbody>
            </table>
        </div>

        <div class="card" style="border-top: 5px solid #10b981;">
            
            <div class="total-display">
                ฿<span id="disp_total">0.00</span>
            </div>

            <form method="POST" id="posForm" onsubmit="return validateCheckout()">
                
                <div style="margin-bottom: 15px;">
                    <label style="color: #64748b;">Cashier / Salesperson:</label>
                    <div class="readonly-field">👤 <?php echo $emp_name; ?></div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="color: #64748b;">Customer:</label>
                    <div class="readonly-field">👥 <?php echo $cust_name; ?></div>
                </div>

                <input type="hidden" name="cart_data" id="cart_data">
                <input type="hidden" name="total_hidden" id="total_hidden">

                <button type="submit" name="submit_pos" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 1.5rem; font-weight: bold;">
                    💵 Pay Cash
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let cart = [];

function addToCart() {
    const sel = document.getElementById('product_select');
    if (!sel.value) return;

    const opt = sel.options[sel.selectedIndex];
    const pID = sel.value;
    const name = opt.getAttribute('data-name');
    const price = parseFloat(opt.getAttribute('data-price'));
    const maxStock = parseInt(opt.getAttribute('data-stock'));
    const qty = parseInt(document.getElementById('qty_input').value);

    if (qty <= 0) return;

    // เช็คว่าเคยเพิ่มสินค้านี้ไปแล้วหรือยัง (ถ้าเคยให้บวก Qty เพิ่ม)
    let existingItem = cart.find(item => item.id === pID);
    let newQty = existingItem ? existingItem.qty + qty : qty;

    // เช็คสต็อก
    if (newQty > maxStock) {
        alert('❌ Not enough stock! Available: ' + maxStock);
        return;
    }

    if (existingItem) {
        existingItem.qty = newQty;
        existingItem.row_total = existingItem.qty * price;
    } else {
        cart.push({ id: pID, name: name, price: price, qty: qty, row_total: price * qty });
    }

    // Reset Input
    sel.value = "";
    document.getElementById('qty_input').value = 1;

    renderCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

function renderCart() {
    const tbody = document.getElementById('cart_body');
    tbody.innerHTML = "";
    let grandTotal = 0;

    cart.forEach((item, index) => {
        grandTotal += item.row_total;
        tbody.innerHTML += `
            <tr>
                <td><strong>${item.name}</strong></td>
                <td>฿${item.price.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td>${item.qty}</td>
                <td style="color:#2563eb; font-weight:bold;">฿${item.row_total.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td><button type="button" class="btn btn-danger" style="padding: 5px 10px; font-size:0.8rem;" onclick="removeFromCart(${index})">X</button></td>
            </tr>
        `;
    });

    document.getElementById('disp_total').innerText = grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    // อัปเดตข้อมูลลง Hidden Input เพื่อส่งไปหา PHP
    document.getElementById('cart_data').value = JSON.stringify(cart);
    document.getElementById('total_hidden').value = grandTotal;
}

function validateCheckout() {
    if (cart.length === 0) {
        alert("🛒 Cart is empty! Please add products.");
        return false;
    }
    return confirm("Confirm Payment?");
}
</script>

</body>
</html>