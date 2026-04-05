<?php
include 'db.php';
session_start();

$order_id = $_SESSION['order_id'] ?? 0;

$product_id = (int)$_POST['product_id'];
$discount = (float)$_POST['discount'];

$conn->query("
    UPDATE order_items
    SET discount_percent = $discount
    WHERE order_id = $order_id
    AND product_id = $product_id
");

header("Location: order.php");
exit;