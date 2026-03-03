<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$uid = $_SESSION['user_id'];

if (!isset($_GET['id'])) { die("Order ID missing."); }
$order_id = (int)$_GET['id'];

// ดึงข้อมูล Order หัวบิล
$order = $conn->query("SELECT * FROM Orders WHERE order_id = $order_id AND status = 'Pending' AND is_deleted = 0")->fetch_assoc();
if (!$order) { die("Order not found, already shipped, or deleted."); }

// ดึงรายการสินค้าเดิมในตะกร้า (ส่งไปแปลงเป็น JSON ให้ JS)
$details = $conn->query("SELECT d.product_id as id, p.product_name as name, d.unit_price as price, d.qty, d.subtotal as row_total 
                         FROM Order_Details d 
                         JOIN Products p ON d.product_id = p.product_id 
                         WHERE d.order_id = $order_id");
$cart_initial = [];
while ($row = $details->fetch_assoc()) {
    $cart_initial[] = $row;
}

// ดึงข้อมูล Master Data
$cust_js_map = [];
$customers = $conn->query("SELECT * FROM Customers WHERE is_deleted = 0");
$cust_list = [];
while($c = $customers->fetch_assoc()) {
    $cust_list[] = $c;
    $cust_js_map[$c['customer_id']] = $c['membership_level'];
}
$products = $conn->query("SELECT * FROM Products WHERE is_deleted = 0 AND stock_qty > 0");
$product_list = [];
while($p = $products->fetch_assoc()) { $product_list[] = $p; }

// --- Save Edited Order ---
if (isset($_POST['update_order'])) {
    $cust_id = $_POST['customer_id'];
    $po_ref = $conn->real_escape_string($_POST['po_ref']);
    $cart_data = json_decode($_POST['cart_data'], true);
    $total_amount = $_POST['total_hidden'];
    $discount_amount = $_POST['discount_hidden'];
    $net_total = $_POST['net_hidden'];
    
    if (!empty($cart_data)) {
        $conn->begin_transaction();
        try {
            // 1. อัปเดตหัวบิล (และบันทึก Audit Trail)
            $conn->query("UPDATE Orders SET 
                          customer_id = $cust_id, po_ref = '$po_ref', 
                          total_amount = $total_amount, discount_amount = $discount_amount, net_total = $net_total,
                          updated_by = $uid, updated_at = NOW() 
                          WHERE order_id = $order_id");

            // 2. ลบของเก่าทิ้งให้หมด แล้ว Insert ของใหม่เข้าไป
            $conn->query("DELETE FROM Order_Details WHERE order_id = $order_id");
            foreach ($cart_data as $item) {
                $p_id = $item['id']; $qty = $item['qty']; $price = $item['price']; $subtotal = $item['row_total'];
                $conn->query("INSERT INTO Order_Details (order_id, product_id, qty, unit_price, subtotal) VALUES ($order_id, $p_id, $qty, $price, $subtotal)");
            }
            $conn->commit();
            echo "<script>alert(' Order Updated Successfully! (Audit Log Saved)'); window.location='manage_orders.php';</script>";
        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Order #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="style.css">
    <style> /* ...สไตล์เดิมเหมือนหน้า Create... */ 
        .summary-box { background: #f8fafc; padding: 20px; border-radius: 8px; margin-top: 20px; text-align: right; border: 1px solid #e2e8f0; }
        .summary-row { display: flex; justify-content: flex-end; align-items: center; margin-bottom: 10px; font-size: 1.1rem; }
        .summary-label { width: 250px; color: #64748b; } .summary-value { width: 150px; font-weight: bold; }
        .discount-tag { background: #fef08a; color: #854d0e; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; margin-right: 10px; display: none; }
    </style>
</head>
<body>

<nav class="navbar"><a href="index.php" class="nav-brand">SD Project</a></nav>

<div class="container">
    <div class="card" style="border-top: 5px solid #f59e0b;">
        <h1 style="color: #f59e0b;">Edit Sales Order #<?php echo $order_id; ?></h1>
        <form method="POST">
            <div class="form-grid" style="background: #f1f5f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <div>
                    <label>Customer:</label>
                    <select name="customer_id" id="customer_id" required style="width: 100%; padding: 10px;" onchange="recalculate()">
                        <?php foreach($cust_list as $c): ?>
                            <option value="<?php echo $c['customer_id']; ?>" <?php if($c['customer_id'] == $order['customer_id']) echo 'selected'; ?>>
                                <?php echo $c['contact_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>PO Ref:</label><input type="text" name="po_ref" value="<?php echo $order['po_ref']; ?>" style="width: 100%; padding: 10px;"></div>
            </div>

            <div class="form-grid" style="align-items: end; margin-bottom: 20px;">
                <div style="grid-column: span 2;">
                    <select id="product_select" style="width: 100%; padding: 10px;">
                        <option value="">-- Add Product --</option>
                        <?php foreach($product_list as $p): ?>
                            <option value="<?php echo $p['product_id']; ?>" data-name="<?php echo htmlspecialchars($p['product_name']); ?>" data-price="<?php echo $p['price']; ?>"><?php echo $p['product_name']; ?> (฿<?php echo number_format($p['price'], 2); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><input type="number" id="qty_input" value="1" min="1" style="width: 100%; padding: 10px;"></div>
                <div><button type="button" class="btn btn-secondary" onclick="addToCart()" style="width: 100%; padding: 10px;">+ Add Item</button></div>
            </div>

            <table style="width: 100%; margin-bottom: 20px;">
                <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th><th>Action</th></tr></thead>
                <tbody id="cart_body"></tbody>
            </table>

            <div class="summary-box">
                <div class="summary-row"><div class="summary-label">Total Qty:</div><div class="summary-value" id="disp_total_qty">0</div></div>
                <div class="summary-row"><div class="summary-label">Subtotal:</div><div class="summary-value" id="disp_subtotal">฿0.00</div></div>
                <div class="summary-row" style="color: #ea580c;">
                    <div class="summary-label"><span id="discount_badge" class="discount-tag"></span> Discount:</div>
                    <div class="summary-value" id="disp_discount">-฿0.00</div>
                </div>
                <div class="summary-row" style="font-size: 1.5rem; color: #10b981; border-top: 2px solid #cbd5e1; margin-top: 10px; padding-top: 10px;">
                    <div class="summary-label">Net Total:</div><div class="summary-value" id="disp_net">฿0.00</div>
                </div>
            </div>

            <input type="hidden" name="cart_data" id="cart_data">
            <input type="hidden" name="total_hidden" id="total_hidden">
            <input type="hidden" name="discount_hidden" id="discount_hidden">
            <input type="hidden" name="net_hidden" id="net_hidden">

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="update_order" class="btn btn-warning" style="flex: 1; padding: 15px; font-size: 1.2rem;">Save Changes</button>
                <a href="manage_orders.php" class="btn btn-secondary" style="padding: 15px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const customersDB = <?php echo json_encode($cust_js_map); ?>;
let cart = <?php echo json_encode($cart_initial); ?>; // โหลดตะกร้าเดิม

function addToCart() {
    const sel = document.getElementById('product_select');
    if (!sel.value) return;
    const opt = sel.options[sel.selectedIndex];
    const pID = sel.value; const name = opt.getAttribute('data-name'); const price = parseFloat(opt.getAttribute('data-price')); const qty = parseInt(document.getElementById('qty_input').value);
    
    let existingItem = cart.find(item => item.id == pID);
    if (existingItem) { existingItem.qty += qty; existingItem.row_total = existingItem.qty * price; } 
    else { cart.push({ id: pID, name: name, price: price, qty: qty, row_total: price * qty }); }
    
    sel.value = ""; document.getElementById('qty_input').value = 1;
    recalculate();
}

function removeFromCart(index) { cart.splice(index, 1); recalculate(); }

function recalculate() {
    const tbody = document.getElementById('cart_body'); tbody.innerHTML = "";
    let subtotal = 0; let totalQty = 0;

    cart.forEach((item, index) => {
        subtotal += parseFloat(item.row_total); totalQty += parseInt(item.qty);
        tbody.innerHTML += `<tr><td>${item.name}</td><td>฿${parseFloat(item.price).toLocaleString()}</td><td>${item.qty}</td><td>฿${parseFloat(item.row_total).toLocaleString()}</td><td><button type="button" class="btn btn-danger" onclick="removeFromCart(${index})">X</button></td></tr>`;
    });

    const custId = document.getElementById('customer_id').value;
    let isPremium = (customersDB[custId] === 'Premium');
    let discountPercent = 0; let badgeText = "";

    if (isPremium && totalQty > 29) { discountPercent = 15; badgeText = "Premium + Bulk (15%)"; } 
    else if (totalQty > 29) { discountPercent = 10; badgeText = "Bulk Order (10%)"; } 
    else if (isPremium) { discountPercent = 5; badgeText = "Premium (5%)"; }

    let discountAmount = subtotal * (discountPercent / 100); let netTotal = subtotal - discountAmount;

    document.getElementById('disp_total_qty').innerText = totalQty;
    document.getElementById('disp_subtotal').innerText = '฿' + subtotal.toLocaleString();
    document.getElementById('disp_discount').innerText = '-฿' + discountAmount.toLocaleString();
    document.getElementById('disp_net').innerText = '฿' + netTotal.toLocaleString();
    
    const badge = document.getElementById('discount_badge');
    if (discountPercent > 0) { badge.innerText = badgeText; badge.style.display = 'inline-block'; } else { badge.style.display = 'none'; }

    document.getElementById('cart_data').value = JSON.stringify(cart);
    document.getElementById('total_hidden').value = subtotal; document.getElementById('discount_hidden').value = discountAmount; document.getElementById('net_hidden').value = netTotal;
}

// โหลดครั้งแรกตอนเปิดหน้า
window.onload = recalculate;
</script>
</body>
</html>