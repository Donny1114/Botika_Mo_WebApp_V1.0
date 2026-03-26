<?php

include 'db.php';
session_start();

$id = (int)$_GET['id'];
$order_id = $_SESSION['order_id'];


// =============================
// GET QUANTITY FIRST (NEW)
// =============================

$res = $conn->query("
    SELECT quantity
    FROM order_items
    WHERE order_id=$order_id
    AND product_id=$id
");

if ($row = $res->fetch_assoc()) {

    $qty = (int)$row['quantity'];

    // =============================
    // RETURN STOCK (NEW)
    // =============================

    $conn->query("
        UPDATE products
        SET stock = stock + $qty
        WHERE id=$id
    ");
}


// =============================
// DELETE ITEM (original code)
// =============================

$conn->query("
DELETE FROM order_items
WHERE order_id=$order_id
AND product_id=$id
");


header("Location: order.php");