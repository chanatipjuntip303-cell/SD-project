<?php
session_start();
include 'db_connect.php';

// เช็คว่า Login หรือยัง และสิทธิ์ถูกต้องไหม
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// กันไม่ให้แผนกคลังสินค้าเข้ามาขายของ
if ($_SESSION['user_role'] == 'Inventory Control') {
    echo "<script>alert('Access Denied! Inventory staff cannot sell.'); window.location='index.php';</script>";
    exit();
}

// --- 1. เตรียมข้อมูล Master Data ---
$customers = [];
$cust_query = $conn->query("SELECT * FROM Customers WHERE is_deleted = 0");
while($row = $cust_query->fetch_assoc()) $customers[] = $row;

// Map ลูกค้าสำหรับ JS
$customer_data_js = [];
foreach ($customers as $c) {
    $customer_data_js[$c['customer_id']] = $c['membership_level'];
}

// สินค้า
$product_data = []; 
$products_list = []; 
$prod_query = $conn->query("SELECT * FROM Products WHERE is_deleted = 0");
while($row = $prod_query->fetch_assoc()) {
    $products_list[] = $row;
    $product_data[$row['product_ID']] = $row;
}

// --- 2. Logic การบันทึกข้อมูล (Save Order Multi-Items) ---
if (isset($_POST['submit_order'])) {
    
    // 💡 แก้ไข Error ตรงนี้: ดึง ID พนักงานจาก Session ทันที ไม่ใช้ $_POST แล้ว
    $emp_id = $_SESSION['user_id']; 
    
    $cust_id = $_POST['customer_id'];
    $po_ref = $_POST['po_reference'];
    $order_date = date('Y-m-d');
    
    $cart_json = $_POST['cart_data']; 
    $cart_items = json_decode($cart_json, true);

    if (empty($cart_items)) {
        echo "<script>alert('Please add at least one product!');</script>";
    } else {
        $grand_total_server = $_POST['grand_total_hidden']; 
        $total_before = $_POST['total_hidden'];

        // 1. สร้าง Order หลัก 1 ครั้ง
        $sql_order = "INSERT INTO Orders (PO_reference, order_date, total_before_discount, Employees_employee_ID, Customers_customer_id) 
                      VALUES ('$po_ref', '$order_date', $total_before, $emp_id, $cust_id)";
        
        if ($conn->query($sql_order)) {
            $last_order_id = $conn->insert_id;

            // 2. วนลูปบันทึกสินค้าลง Order_details และตัดสต็อก ✂️
            foreach ($cart_items as $item) {
                $p_id = $item['id'];
                $qty = $item['qty'];
                $u_price = $item['price'];
                
                // บันทึกรายละเอียดการขาย
                $conn->query("INSERT INTO Order_details (quantity, unit_price, Orders_Order_ID, Products_product_ID) 
                              VALUES ($qty, $u_price, $last_order_id, $p_id)");

                // ตัดสต็อกสินค้า
                $conn->query("UPDATE Products SET amount = amount - $qty WHERE product_ID = $p_id");
            }

            // 3. สร้าง Invoice 1 ใบ
            $discount_member = $_POST['discount_member_hidden'];
            $discount_special = $_POST['discount_special_hidden'];
            
            $sql_invoice = "INSERT INTO Invoices (billing_date, discount_member, discount_special, grand_total, payment_status, Orders_Order_ID) 
                            VALUES ('$order_date', $discount_member, $discount_special, $grand_total_server, 'Cash', $last_order_id)";
            $conn->query($sql_invoice);

            echo "<script>alert('Order Saved Successfully!'); window.location='index.php';</script>";
        } else {
            // ถ้ามี Error จะเด้งบอกว่าเกิดจากอะไร
            echo "<script>alert('Error: " . $conn->error . "');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Sale Order</title>
    <link rel="stylesheet" href="style.css"> 
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="nav-brand">SD Project Shop</a>
    <div class="nav-menu">
        <a href="index.php">Dashboard</a>
        <a href="create_order.php" class="active">Sell</a>
        <a href="view_invoices.php">History</a>
    </div>
</nav>

<div class="container">
    <div class="card">
        <h1>🛒 New Sale Order</h1>

        <form method="POST" id="orderForm" onsubmit="return validateCart()">
            <div class="form-grid">
                <div>
                    <label>Sales Employee:</label>
                    <input type="text" value="<?php echo $_SESSION['user_name']; ?> (<?php echo $_SESSION['user_role']; ?>)" readonly style="background: #e2e8f0; cursor: not-allowed; color: #64748b;">
                </div>
                <div>
                    <label>Customer:</label>
                    <select name="customer_id" id="customer_id" onchange="recalculateAll()" required>
                        <option value="">-- Select Customer --</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?php echo $c['customer_id']; ?>">
                                <?php echo $c['contact_name']; ?> (<?php echo $c['membership_level']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>PO Reference:</label>
                    <input type="text" name="po_reference" placeholder="e.g. PO-9999" required>
                </div>
            </div>

            <hr style="border: 0; height: 1px; background: #e2e8f0; margin: 20px 0;">

            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <div class="form-grid" style="align-items: end;">
                    <div style="grid-column: span 2;">
                        <label>Select Product:</label>
                        <select id="product_select">
                            <option value="">-- Choose Product --</option>
                            <?php foreach($products_list as $p): ?>
                                <option value="<?php echo $p['product_ID']; ?>">
                                    <?php echo $p['product_name']; ?> (฿<?php echo number_format($p['price_per_unit'], 2); ?>) - Stock: <?php echo $p['amount']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Qty:</label>
                        <input type="number" id="qty_input" value="1" min="1">
                    </div>
                    <div>
                        <button type="button" class="btn btn-success" style="width: 100%; height: 48px; margin-bottom: 15px;" onclick="addToCart()">+ Add Item</button>
                    </div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="cart_body">
                    </tbody>
            </table>

            <div style="background: #eef2ff; padding: 20px; border-radius: 8px; margin-top: 20px; text-align: right;">
                <div style="margin-bottom: 5px; color: #64748b;">Subtotal: <span id="disp_sub">฿0.00</span></div>
                <div style="color: #ea580c; margin-bottom: 5px;">Special Discount (10% > 10k): <span id="disp_special">฿0.00</span></div>
                <div style="color: #9333ea; margin-bottom: 5px;">Member Discount (5%): <span id="disp_member">฿0.00</span></div>
                <div style="font-size: 1.5em; font-weight: bold; color: #16a34a; margin-top: 10px; border-top: 2px solid #c7d2fe; padding-top: 10px;">
                    Grand Total: <span id="disp_grand">฿0.00</span>
                </div>
            </div>

            <input type="hidden" name="cart_data" id="cart_data">
            <input type="hidden" name="total_hidden" id="total_hidden">
            <input type="hidden" name="discount_special_hidden" id="discount_special_hidden">
            <input type="hidden" name="discount_member_hidden" id="discount_member_hidden">
            <input type="hidden" name="grand_total_hidden" id="grand_total_hidden">

            <button type="submit" name="submit_order" class="btn btn-primary" style="width: 100%; font-size: 1.2em; padding: 15px; margin-top: 20px;">Confirm & Create Invoice</button>
        </form>
    </div>
</div>

<script>
const productsDB = <?php echo json_encode($product_data); ?>;
const customersDB = <?php echo json_encode($customer_data_js); ?>;
let cart = [];

function addToCart() {
    const pID = document.getElementById('product_select').value;
    const qty = parseInt(document.getElementById('qty_input').value);

    if (!pID || qty <= 0) {
        alert("Please select a product and valid quantity.");
        return;
    }

    const product = productsDB[pID];
    
    // เช็คสต็อกว่าพอไหม
    if (qty > product.amount) {
        alert("Not enough stock! Current stock is " + product.amount);
        return;
    }

    cart.push({
        id: pID,
        name: product.product_name,
        price: parseFloat(product.price_per_unit),
        qty: qty,
        row_total: parseFloat(product.price_per_unit) * qty
    });

    renderCart();
    recalculateAll();
    
    document.getElementById('qty_input').value = 1;
    document.getElementById('product_select').value = "";
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
    recalculateAll();
}

function renderCart() {
    const tbody = document.getElementById('cart_body');
    tbody.innerHTML = ""; 

    cart.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.name}</td>
            <td>฿${item.price.toLocaleString()}</td>
            <td>${item.qty}</td>
            <td>฿${item.row_total.toLocaleString()}</td>
            <td><button type="button" class="btn btn-danger" style="padding: 5px 10px;" onclick="removeFromCart(${index})">X</button></td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('cart_data').value = JSON.stringify(cart);
}

function recalculateAll() {
    let subtotal = 0;
    cart.forEach(item => subtotal += item.row_total);

    let specialDisc = (subtotal > 10000) ? subtotal * 0.10 : 0;
    let memberDisc = 0;
    const cID = document.getElementById('customer_id').value;
    
    if (cID && customersDB[cID]) {
        if (customersDB[cID].toLowerCase().trim() === 'premium') {
            memberDisc = subtotal * 0.05;
        }
    }

    const grandTotal = subtotal - specialDisc - memberDisc;
    const fmt = (num) => '฿' + num.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    document.getElementById('disp_sub').innerText = fmt(subtotal);
    document.getElementById('disp_special').innerText = '-' + fmt(specialDisc);
    document.getElementById('disp_member').innerText = '-' + fmt(memberDisc);
    document.getElementById('disp_grand').innerText = fmt(grandTotal);

    document.getElementById('total_hidden').value = subtotal.toFixed(2);
    document.getElementById('discount_special_hidden').value = specialDisc.toFixed(2);
    document.getElementById('discount_member_hidden').value = memberDisc.toFixed(2);
    document.getElementById('grand_total_hidden').value = grandTotal.toFixed(2);
}

function validateCart() {
    if (cart.length === 0) {
        alert("Your cart is empty!");
        return false;
    }
    return true;
}
</script>
</body>
</html>