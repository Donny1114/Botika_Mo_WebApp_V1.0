<?php
session_start();
include 'db.php';

if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

$name    = trim($_POST['customer_name']);
$phone   = trim($_POST['phone']);
$address = trim($_POST['address']);

$cart = $_SESSION['cart'];

// Calculate total
$total = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['quantity'];
}

// START TRANSACTION
mysqli_begin_transaction($conn);

try {
    // 1. Insert order
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO orders (customer_name, phone, address, total)
         VALUES (?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "sssd", $name, $phone, $address, $total);
    mysqli_stmt_execute($stmt);

    $order_id = mysqli_insert_id($conn);

    // 2. Insert order items + update stock
    foreach ($cart as $item) {

        // Insert order item
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO order_items (order_id, product_id, price, quantity,cashier_id)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            "iidi",
            $order_id,
            $item['id'],
            $item['price'],
            $item['quantity'],
            $_SESSION['cashier_id']
        );
        mysqli_stmt_execute($stmt);

        // Reduce stock
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE products SET stock = stock - ? WHERE id = ?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $item['quantity'],
            $item['id']
        );
        mysqli_stmt_execute($stmt);
    }

    // COMMIT
    mysqli_commit($conn);

    // Clear cart
    unset($_SESSION['cart']);

    header("Location: order_success.php");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Order failed. Please try again.");
}
