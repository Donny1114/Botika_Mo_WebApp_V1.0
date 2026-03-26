<?php

include 'db.php';
session_start();

if (!isset($_SESSION['order_id'])) {
    header("Location: order.php");
    exit;
}

$order_id = $_SESSION['order_id'];


$items = $conn->query("
    SELECT product_id, quantity
    FROM order_items
    WHERE order_id=$order_id
");

while ($row = $items->fetch_assoc()) {

    $pid = (int)$row['product_id'];
    $qty = (int)$row['quantity'];

    $conn->query("
        UPDATE products
        SET stock = stock + $qty
        WHERE id=$pid
    ");
}

$conn->query("DELETE FROM order_items WHERE order_id=$order_id");

$conn->query("UPDATE orders SET status='cancelled' WHERE id=$order_id");

unset($_SESSION['order_id']);

header("Location: order.php");