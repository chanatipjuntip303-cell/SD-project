<?php
session_start();
include 'db_connect.php';

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$uid = $_SESSION['user_id'];
$emp_name = $_SESSION['user_name'];

// --- 2. Fetch Data ---
// ดึงข้อมูลลูกค้า (เอาไว้เลือก และเอาไว้เช็ค Premium ใน JS)
$customers = $conn->query("SELECT * FROM Customers WHERE is_deleted = 0 ORDER BY contact_name ASC");
$cust_list = [];
$cust_js_map = []; // เอาไว้ส่งไปให้ JavaScript เช็ค Level
while($c = $customers->fetch_assoc()) {
    $cust_list[] = $c;
    $cust_js_map[$c['customer_id']] = $c['membership_level'];
}

// ดึงสินค้า
$products = $conn->query("SELECT * FROM Products WHERE is_deleted = 0 ORDER BY product_name ASC");
$product_list = [];
while($p = $products->fetch_assoc()) {
    $product_list[] = $p;
}

// --- 3. Save Order Logic (Standard Order - Not cutting stock yet) ---
if (isset($_POST['save_order'])) {
    $cust_id = $_POST['customer_id'];
    $po_ref = $_POST['po_ref'] ? $conn->real_escape_string($_POST['po_ref']) : "SO-" . date('Ymd-Hi');
    
    $cart_data = json_decode($_POST['cart_data'], true);
    $total_amount = $_POST['total_hidden']; // ยอดก่อนลด
    $discount_amount = $_POST['discount_hidden']; // ยอดส่วนลด
    $net_total = $_POST['net_hidden']; // ยอดสุทธิ
    
    if (!empty($cart_data)) {
        // 1. สร้าง Order (สถานะ 'Pending', ประเภท 'Standard')
        $sql_order = "INSERT INTO Orders (po_ref, customer_id, employee_id, total_amount, discount_amount, net_total, status, order_type) 
                      VALUES ('$po_ref', $cust_id, $uid, $total_amount, $discount_amount, $net_total, 'Pending', 'Standard')";
        
        if ($conn->query($sql_order)) {
            $order_id = $conn->insert_id;

            // 2. บันทึกรายการสินค้า (Order Details) -> ยังไม่ตัดสต็อกนะ!
            foreach ($cart_data as $item) {
                $p_id = $item['id'];
                $qty = $item['qty'];
                $price = $item['price'];
                $subtotal = $item['row_total'];

                $conn->query("INSERT INTO Order_Details (order_id, product_id, qty, unit_price, subtotal) 
                              VALUES ($order_id, $p_id, $qty, $price, $subtotal)");
            }

            echo "<script>alert('✅ Sales Order Saved! (Status: Pending)\\nPlease go to Manage Orders to ship.'); window.location='manage_orders.php';</script>";
        } else {
            echo "<script>alert('Error: " . $conn->error . "');</script>";
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
    <title>Create Sales Order</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .summary-box { background: #f8fafc; padding: 20px; border-radius: 8px; margin-top: 20px; text-align: right; border: 1px solid #e2e8f0; }
        .summary-row { display: flex; justify-content: flex-end; align-items: center; margin-bottom: 10px; font-size: 1.1rem; }
        .summary-label { width: 250px; color: #64748b; }
        .summary-value { width: 150px; font-weight: bold; }
        .discount-tag { background: #fef08a; color: #854d0e; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; margin-right: 10px; display: none; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand">SD Project</a>
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="pos_direct_sale.php">POS (หน้าร้าน)</a>
        <a href="create_sale_order.php" class="active">Sales Order (สั่งทำ)</a>
        <a href="manage_orders.php">Manage Orders</a>
    </div>
</nav>

<div class="container">
    <div class="card" style="border-top: 5px solid #2563eb;">
        <h1 style="color: #2563eb;">📝 Create Sales Order</h1>
        <p style="color: #64748b;">Record customer order (Stock will be deducted upon shipment)</p>
        
        <form method="POST" onsubmit="return validateOrder()">
            
            <div class="form-grid" style="background: #f1f5f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <div>
                    <label>Customer (Select to apply discount):</label>
                    <select name="customer_id" id="customer_id" required style="width: 100%; padding: 10px;" onchange="recalculate()">
                        <option value="">-- Choose Customer --</option>
                        <?php foreach($cust_list as $c): ?>
                            <option value="<?php echo $c['customer_id']; ?>">
                                <?php echo $c['contact_name']; ?> 
                                <?php echo ($c['membership_level'] == 'Premium') ? '(💎 Premium)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>PO Ref / Note (Optional):</label>
                    <input type="text" name="po_ref" placeholder="e.g., PO-2026-001" style="width: 100%; padding: 10px;">
                </div>
                <div>
                    <label>Salesperson:</label>
                    <input type="text" value="<?php echo $emp_name; ?>" readonly style="width: 100%; padding: 10px; background: #e2e8f0; border: none;">
                </div>
            </div>

            <div class="form-grid" style="align-items: end; margin-bottom: 20px;">
                <div style="grid-column: span 2;">
                    <label>Product:</label>
                    <select id="product_select" style="width: 100%; padding: 10px;">
                        <option value="">-- Select Product --</option>
                        <?php foreach($product_list as $p): ?>
                            <option value="<?php echo $p['product_id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($p['product_name']); ?>" 
                                    data-price="<?php echo $p['price']; ?>">
                                <?php echo $p['product_name']; ?> (฿<?php echo number_format($p['price'], 2); ?>) - Stock: <?php echo $p['stock_qty']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Qty:</label><input type="number" id="qty_input" value="1" min="1" style="width: 100%; padding: 10px;"></div>
                <div><button type="button" class="btn btn-secondary" onclick="addToCart()" style="width: 100%; padding: 10px;">+ Add Item</button></div>
            </div>

            <table style="width: 100%; margin-bottom: 20px;">
                <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th><th>Action</th></tr></thead>
                <tbody id="cart_body"></tbody>
            </table>

            <div class="summary-box">
                <div class="summary-row">
                    <div class="summary-label">Total Qty:</div>
                    <div class="summary-value" id="disp_total_qty" style="color: #2563eb;">0</div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Subtotal:</div>
                    <div class="summary-value" id="disp_subtotal">฿0.00</div>
                </div>
                <div class="summary-row" style="color: #ea580c;">
                    <div class="summary-label">
                        <span id="discount_badge" class="discount-tag">10% OFF</span>
                        Discount:
                    </div>
                    <div class="summary-value" id="disp_discount">-฿0.00</div>
                </div>
                <div class="summary-row" style="font-size: 1.5rem; color: #10b981; border-top: 2px solid #cbd5e1; padding-top: 10px; margin-top: 10px;">
                    <div class="summary-label">Net Total:</div>
                    <div class="summary-value" id="disp_net">฿0.00</div>
                </div>
            </div>

            <input type="hidden" name="cart_data" id="cart_data">
            <input type="hidden" name="total_hidden" id="total_hidden">
            <input type="hidden" name="discount_hidden" id="discount_hidden">
            <input type="hidden" name="net_hidden" id="net_hidden">

            <button type="submit" name="save_order" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 1.2rem; margin-top: 20px;">
                💾 Save Order (Pending)
            </button>
        </form>
    </div>
</div>

<script>
// ดึงข้อมูล Level ลูกค้าจาก PHP มาไว้ใน JS
const customersDB = <?php echo json_encode($cust_js_map); ?>;
let cart = [];

function addToCart() {
    const sel = document.getElementById('product_select');
    if (!sel.value) return;

    const opt = sel.options[sel.selectedIndex];
    const pID = sel.value;
    const name = opt.getAttribute('data-name');
    const price = parseFloat(opt.getAttribute('data-price'));
    const qty = parseInt(document.getElementById('qty_input').value);

    if (qty <= 0) return;

    let existingItem = cart.find(item => item.id === pID);
    if (existingItem) {
        existingItem.qty += qty;
        existingItem.row_total = existingItem.qty * price;
    } else {
        cart.push({ id: pID, name: name, price: price, qty: qty, row_total: price * qty });
    }

    sel.value = "";
    document.getElementById('qty_input').value = 1;

    recalculate(); // คำนวณใหม่ทุกครั้งที่หยิบของ
}

function removeFromCart(index) {
    cart.splice(index, 1);
    recalculate(); // คำนวณใหม่ทุกครั้งที่ลบของ
}

// 🧠 หัวใจสำคัญ: Logic การคำนวณส่วนลด
function recalculate() {
    const tbody = document.getElementById('cart_body');
    tbody.innerHTML = "";
    
    let subtotal = 0;
    let totalQty = 0;

    // Render Table & หาผลรวม
    cart.forEach((item, index) => {
        subtotal += item.row_total;
        totalQty += item.qty;
        tbody.innerHTML += `
            <tr>
                <td>${item.name}</td>
                <td>฿${item.price.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td>${item.qty}</td>
                <td>฿${item.row_total.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td><button type="button" class="btn btn-danger" style="padding: 2px 8px;" onclick="removeFromCart(${index})">X</button></td>
            </tr>
        `;
    });

    // --- ตรวจสอบเงื่อนไขส่วนลด ---
    const custId = document.getElementById('customer_id').value;
    let isPremium = false;
    if (custId && customersDB[custId] === 'Premium') {
        isPremium = true;
    }

    let discountPercent = 0;
    let badgeText = "";

    // 1. Premium & > 29 items = 15%
    if (isPremium && totalQty > 29) {
        discountPercent = 15;
        badgeText = "💎 Premium + Bulk (15%)";
    } 
    // 2. Buy > 29 items = 10%
    else if (totalQty > 29) {
        discountPercent = 10;
        badgeText = "📦 Bulk Order (10%)";
    } 
    // 3. Premium Customer = 5%
    else if (isPremium) {
        discountPercent = 5;
        badgeText = "💎 Premium (5%)";
    }

    // คำนวณเงินส่วนลด
    let discountAmount = subtotal * (discountPercent / 100);
    let netTotal = subtotal - discountAmount;

    // อัปเดตหน้าจอ (UI)
    document.getElementById('disp_total_qty').innerText = totalQty;
    document.getElementById('disp_subtotal').innerText = '฿' + subtotal.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('disp_discount').innerText = '-฿' + discountAmount.toLocaleString(undefined, {minimumFractionDigits: 2});
    document.getElementById('disp_net').innerText = '฿' + netTotal.toLocaleString(undefined, {minimumFractionDigits: 2});

    // จัดการป้าย (Badge) โชว์เหตุผลส่วนลด
    const badge = document.getElementById('discount_badge');
    if (discountPercent > 0) {
        badge.innerText = badgeText;
        badge.style.display = 'inline-block';
    } else {
        badge.style.display = 'none';
    }

    // อัปเดต Hidden inputs สำหรับส่งไป PHP
    document.getElementById('cart_data').value = JSON.stringify(cart);
    document.getElementById('total_hidden').value = subtotal.toFixed(2);
    document.getElementById('discount_hidden').value = discountAmount.toFixed(2);
    document.getElementById('net_hidden').value = netTotal.toFixed(2);
}

function validateOrder() {
    if (cart.length === 0) {
        alert("Cart is empty!");
        return false;
    }
    return true;
}
</script>

</body>
</html>