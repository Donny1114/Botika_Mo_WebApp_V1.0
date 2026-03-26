<?php

include 'header.php';
include 'db.php';

// Validate order_id from URL
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    echo "<p>Invalid order ID.</p>";
    include 'footer.php';
    exit;
}

$order_id = (int)$_GET['order_id'];

// Fetch order details
$stmt = mysqli_prepare($conn, "SELECT id, customer_name, delivery_address, created_at, payment_method FROM orders WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$orderResult = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($orderResult);

if (!$order) {
    echo "<p>Order not found.</p>";
    include 'footer.php';
    exit;
}

// Fetch ordered items
$stmtItems = mysqli_prepare(
    $conn,
    "SELECT oi.quantity, oi.sell_price, p.name 
     FROM order_items oi
     JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = ?"
);
mysqli_stmt_bind_param($stmtItems, "i", $order_id);
mysqli_stmt_execute($stmtItems);
$itemsResult = mysqli_stmt_get_result($stmtItems);
?>

<div class="container mt-4">
    <h2>Order Confirmation</h2>
    <p>Thank you, <strong><?= htmlspecialchars($order['customer_name']) ?></strong>, for your order!</p>
    <p><strong>Order Number:</strong> <?= $order['id'] ?></p>
    <p><strong>Delivery Address:</strong> <?= htmlspecialchars($order['delivery_address']) ?></p>
    <p><strong>Order Date:</strong> <?= date('F j, Y, g:i A', strtotime($order['created_at'])) ?></p>
    <hr>

    <h2>Thank you for your order, <?= htmlspecialchars($order['customer_name']) ?>!</h2>
    <p>Your order has been placed successfully.</p>

    <!-- For online only -->
    <!-- <?php if ($order['payment_method'] === 'GCash'): ?>
        <div class="alert alert-info">
            <h4>Pay via GCash</h4>
            <p>Please scan the QR code below to complete your payment:</p>
            <img src="assets/images/gcash_qr 1.png" alt="GCash QR Code" style="max-width:200px;">
            <p>Include your Order Number <strong>#<?= $order_id ?></strong> as payment reference.</p>
            <p>Once paid, please wait for confirmation.</p>
        </div>
    <?php endif; ?> -->


</div>

<h4>Order Details</h4>
<table class="table table-bordered">
    <thead>
        <tr>
            <th>Product</th>
            <th>Quantity</th>
            <th>Price each</th>
            <th>Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $total = 0;
        while ($item = mysqli_fetch_assoc($itemsResult)):
            $subtotal = $item['sell_price'] * $item['quantity'];
            $total += $subtotal;
        ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>₱<?= number_format($item['sell_price'], 2) ?></td>
                <td>₱<?= number_format($subtotal, 2) ?></td>
            </tr>
        <?php endwhile; ?>
        <tr class="table-secondary">
            <td colspan="3" class="text-end fw-bold">Total</td>
            <td class="fw-bold">₱<?= number_format($total, 2) ?></td>
        </tr>
    </tbody>
</table>

<a href="order.php" class="btn btn-primary">Shop More Products</a>
<a href="index.php" class="btn btn-secondary ms-2">Back to Home</a>
</div>

<?php include 'footer.php'; ?>