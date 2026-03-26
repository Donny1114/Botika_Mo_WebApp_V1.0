<?php
include 'header.php';
include 'db.php';

$cashier = $_SESSION['user_id'];

// get open shift
$shiftQuery = $conn->query("
    SELECT id, opening_cash, open_time
    FROM cashier_shift
    WHERE cashier_id=$cashier AND status='open'
");

if($shiftQuery->num_rows == 0){
    echo "<div class='alert alert-warning'>No open shift found.</div>";
    exit;
}

$shift = $shiftQuery->fetch_assoc();
$shift_id = $shift['id'];
$opening = $shift['opening_cash'];
$open_time = $shift['open_time'];

// sum paid orders during this shift
$orderQuery = $conn->query("
    SELECT 
        SUM(CASE WHEN payment_method='Cash' AND status='paid' THEN total ELSE 0 END) AS cash,
        SUM(CASE WHEN payment_method='GCash' AND status='paid' THEN total ELSE 0 END) AS gcash,
        SUM(CASE WHEN payment_method='Card' AND status='paid' THEN total ELSE 0 END) AS card,
        SUM(CASE WHEN status='paid' THEN total ELSE 0 END) AS total_sales,
        COUNT(CASE WHEN status='paid' THEN id END) AS total_orders
    FROM orders
    WHERE cashier_id=$cashier
    AND status='paid'
    AND created_at BETWEEN '$open_time' AND NOW()
")->fetch_assoc();

$expected = $opening + ($orderQuery['cash'] ?? 0);

// Handle form submission
if(isset($_POST['close_shift'])){
    $closing_cash = floatval($_POST['closing_cash']);

    $conn->query("
        UPDATE cashier_shift
        SET close_time=NOW(),
            cash_sales=".($orderQuery['cash'] ?? 0).",
            gcash_sales=".($orderQuery['gcash'] ?? 0).",
            card_sales=".($orderQuery['card'] ?? 0).",
            total_orders=".($orderQuery['total_orders'] ?? 0).",
            total_sales=".($orderQuery['total_sales'] ?? 0).",
            closing_cash=$closing_cash,
            status='closed'
        WHERE id=$shift_id
    ");

    echo "<div class='alert alert-success'>Shift closed successfully!</div>";
    echo "<a href='order.php' class='btn btn-primary'>Back to POS</a>";
    exit;
}

?>

<h3>Close Shift</h3>

<p>Opening Cash: ₱<?= number_format($opening,2) ?></p>
<p>Cash Sales: ₱<?= number_format($orderQuery['cash'] ?? 0,2) ?></p>
<p>GCash Sales: ₱<?= number_format($orderQuery['gcash'] ?? 0,2) ?></p>
<p>Card Sales: ₱<?= number_format($orderQuery['card'] ?? 0,2) ?></p>
<p>Total Orders: <?= $orderQuery['total_orders'] ?? 0 ?></p>
<p>Total Sales: ₱<?= number_format($orderQuery['total_sales'] ?? 0,2) ?></p>
<p>Expected Cash: ₱<?= number_format($expected,2) ?></p>

<form method="post">
    <label>Actual Cash in Drawer:</label>
    <input type="number" step="0.01" name="closing_cash" required>
    <button type="submit" name="close_shift">Close Shift</button>
</form>