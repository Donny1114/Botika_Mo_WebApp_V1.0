<?php
include 'header.php';
include '../db.php';

$id = $_GET['id'];

$q = $conn->query("
SELECT *
FROM z_reading
WHERE id = $id
");

$row = $q->fetch_assoc();

/* ======================
CASHIER BREAKDOWN (FOR VIEW)
====================== */

$z_date = $row['date'];

$cashier_query = $conn->query("
SELECT 
cs.id,
cs.cashier_id,
u.name AS cashier_name,
cs.opening_cash,

SUM(CASE 
    WHEN o.payment_method = 'Cash' 
    THEN (oi.sell_price * oi.quantity) - ((oi.sell_price * oi.quantity) * (o.discount_percent / 100))
    ELSE 0 END
) as cash_sales,

SUM(CASE 
    WHEN o.payment_method = 'GCash' 
    THEN (oi.sell_price * oi.quantity) - ((oi.sell_price * oi.quantity) * (o.discount_percent / 100))
    ELSE 0 END
) as gcash_sales,

SUM(CASE 
    WHEN o.payment_method = 'Card' 
    THEN (oi.sell_price * oi.quantity) - ((oi.sell_price * oi.quantity) * (o.discount_percent / 100))
    ELSE 0 END
) as card_sales

FROM cashier_shift cs

LEFT JOIN users u 
ON u.id = cs.cashier_id

LEFT JOIN orders o 
ON o.cashier_id = cs.cashier_id 
AND DATE(o.created_at) = '$z_date'
AND o.status != 'Voided'

LEFT JOIN order_items oi 
ON oi.order_id = o.id

GROUP BY cs.id
");

?>

<div class="card p-3">

    <h4>Z Reading Details</h4>

    <a href="z_print.php?id=<?= $row['id'] ?>"
        class="btn btn-primary mb-2"
        target="_blank">

        Print

    </a>
    <a href="z_print.php?id=<?= $row['id'] ?>"
        class="btn btn-success mb-2"
        target="_blank">

        Save PDF

    </a>

    <p>Date: <?= $row['date'] ?></p>

    <hr>

    <p>Opening Cash: ₱<?= number_format($row['opening_cash'], 2) ?></p>

    <p>Cash Sales: ₱<?= number_format($row['cash_sales'], 2) ?></p>

    <p>GCash Sales: ₱<?= number_format($row['gcash_sales'], 2) ?></p>

    <p>Card Sales: ₱<?= number_format($row['card_sales'], 2) ?></p>

    <hr>

    <p>Total Orders: <?= $row['total_orders'] ?></p>

    <p>Total Sales: ₱<?= number_format($row['total_sales'], 2) ?></p>

    <p>Total Discount: ₱<?= number_format($row['total_discount'], 2) ?></p>

    <hr>

    <p>Expected Cash: ₱<?= number_format($row['expected_cash'], 2) ?></p>

    <p>Closing Cash: ₱<?= number_format($row['closing_cash'], 2) ?></p>

    <hr>
    <h5>Cashier Breakdown</h5>

    <?php while ($c = $cashier_query->fetch_assoc()):

        $cash_sales = $c['cash_sales'] ?? 0;
        $gcash_sales = $c['gcash_sales'] ?? 0;
        $card_sales = $c['card_sales'] ?? 0;

        $expected_cashier = $c['opening_cash'] + $cash_sales;
    ?>

        <div class="border p-2 mb-2">

            <strong><?= $c['cashier_name'] ?? 'Cashier' ?></strong><br>

            Opening: ₱<?= number_format($c['opening_cash'], 2) ?><br>

            Cash: ₱<?= number_format($cash_sales, 2) ?><br>
            GCash: ₱<?= number_format($gcash_sales, 2) ?><br>
            Card: ₱<?= number_format($card_sales, 2) ?><br>

            <b>Expected: ₱<?= number_format($expected_cashier, 2) ?></b>

        </div>

    <?php endwhile; ?>

</div>

<?php include 'footer.php'; ?>