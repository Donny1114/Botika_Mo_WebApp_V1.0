<?php

include '../db.php';

foreach ($_POST['price'] as $id => $price) {

    $sku    = $_POST['sku'][$id];
    $cost   = $_POST['cost'][$id];
    $stock  = $_POST['stock'][$id];
    $expiry = $_POST['expiry'][$id];

    $stmt = $conn->prepare("
        UPDATE products
        SET sku = ?, cost = ?, price = ?, stock = ?, expiry_date = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        "sddisi",
        $sku, $cost, $price, $stock, $expiry, $id
    );
    $stmt->execute();
}

header("Location: admin_products.php?updated=1");
exit;
