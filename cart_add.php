<?php
include 'db.php';
session_start();

// Get product and quantity from POST
$product_id = (int)($_POST['product_id'] ?? 0);
$qty = (int)($_POST['quantity'] ?? 1);

if ($product_id <= 0 || $qty <= 0) {
    header("Location: order.php");
    exit;
}

// Make sure the cashier is logged in
$cashier_id = $_SESSION['user_id'] ?? 0;
if (!$cashier_id) {
    header("Location: login.php");
    exit;
}

// =============================
// Ensure an order exists
// =============================
if (!isset($_SESSION['order_id'])) {
    $conn->query("
        INSERT INTO orders 
        (cashier_id, customer_name, delivery_address, status, order_type, created_at)
        VALUES ($cashier_id,'Walk-in','','open','pos',NOW())
    ");
    $_SESSION['order_id'] = $conn->insert_id;
}

$order_id = $_SESSION['order_id'];

// Double-check order exists in DB
$orderCheck = $conn->query("
    SELECT id 
    FROM orders 
    WHERE id=$order_id 
    AND status='open'
");

if ($orderCheck->num_rows == 0) {

    $conn->query("
        INSERT INTO orders 
        (cashier_id, customer_name, delivery_address, status, order_type, created_at)
        VALUES ($cashier_id,'Walk-in','','open','pos',NOW())
    ");

    $_SESSION['order_id'] = $conn->insert_id;
    $order_id = $_SESSION['order_id'];
}

// =============================
// Get product details (PRICE + COST)
// =============================
$stmt = $conn->prepare("
    SELECT name, price, cost_price , stock
    FROM products 
    WHERE id=?
");

$stmt->bind_param("i", $product_id);
$stmt->execute();

$p = $stmt->get_result()->fetch_assoc();

if (!$p) {
    header("Location: order.php");
    exit;
}

$name       = $p['name'];
$price      = $p['price'];
$cost_price = $p['cost_price'];   // ✅ IMPORTANT
$stock = (int)$p['stock'];

// =============================
// Check if product already in cart
// =============================
$stmt = $conn->prepare("
    SELECT id, quantity 
    FROM order_items 
    WHERE order_id=? 
    AND product_id=?
");

$stmt->bind_param("ii", $order_id, $product_id);
$stmt->execute();

$check = $stmt->get_result();


if ($check->num_rows > 0) {

    $row = $check->fetch_assoc();

    $currentQty = (int)$row['quantity'];

    $newQty = $currentQty + $qty;

    // how much stock change needed
    $diff = $newQty - $currentQty;

    if ($diff > 0 && $diff > $stock) {
        header("Location: order.php?error=nostock");
        exit;
    }

    // update qty + price + cost
    $stmt = $conn->prepare("
        UPDATE order_items 
        SET quantity=?, sell_price=?, cost_price=? 
        WHERE id=?
    ");

    $stmt->bind_param(
        "iddi",
        $newQty,
        $price,
        $cost_price,
        $row['id']
    );

    $stmt->execute();
} else {

    // insert new item with cost_price
    $stmt = $conn->prepare("
        INSERT INTO order_items
        (order_id, product_id, quantity, sell_price, cost_price)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iiidd",
        $order_id,
        $product_id,
        $qty,
        $price,
        $cost_price
    );

    $stmt->execute();
}
// ✅ DEDUCT / RETURN STOCK SAFELY

if (isset($diff)) {

    if ($diff > 0) {

        $updateStock = mysqli_prepare(
            $conn,
            "UPDATE products SET stock = stock - ? WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $updateStock,
            "ii",
            $diff,
            $product_id
        );

        mysqli_stmt_execute($updateStock);
    }
} else {

    // new item

    if ($qty > $stock) {
        header("Location: order.php?error=nostock");
        exit;
    }

    $updateStock = mysqli_prepare(
        $conn,
        "UPDATE products SET stock = stock - ? WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $updateStock,
        "ii",
        $qty,
        $product_id
    );

    mysqli_stmt_execute($updateStock);
}


// Redirect back to order page
header("Location: order.php");
exit;
