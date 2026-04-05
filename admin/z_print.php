<?php
include '../db.php';

$id = (int)$_GET['id'];

$stmt = $conn->prepare("
SELECT *
FROM z_reading
WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

/* ======================
CASHIER BREAKDOWN (PRINT)
====================== */

$z_date = $row['date'];

$cashier_query = $conn->query("
SELECT 
cs.id,
cs.cashier_id,
u.name AS cashier_name,
cs.opening_cash,

COALESCE(SUM(CASE 
    WHEN o.payment_method = 'Cash' 
    THEN (oi.sell_price * oi.quantity) - ((oi.sell_price * oi.quantity) * (oi.discount_percent / 100))
    ELSE 0 END
),0) as cash_sales,

COALESCE(SUM(CASE 
    WHEN o.payment_method = 'GCash' 
    THEN (oi.sell_price * oi.quantity) - ((oi.sell_price * oi.quantity) * (oi.discount_percent / 100))
    ELSE 0 END
),0) as gcash_sales,

COALESCE(SUM(CASE 
    WHEN o.payment_method = 'Card' 
    THEN (oi.sell_price * oi.quantity) - ((oi.sell_price * oi.quantity) * (oi.discount_percent / 100))
    ELSE 0 END
),0) as card_sales

FROM cashier_shift cs

LEFT JOIN users u 
ON u.id = cs.cashier_id

LEFT JOIN orders o 
ON o.cashier_id = cs.cashier_id 
AND DATE(o.created_at) = '$z_date'
AND o.status != 'Voided'

LEFT JOIN order_items oi 
ON oi.order_id = o.id

WHERE DATE(cs.open_time) = '$z_date'  -- only include shifts opened on the Z reading date

GROUP BY cs.id
");

?>

<html>

<head>

    <title>Z Report</title>

    <style>
        body {
            font-family: monospace;
            width: 300px;
        }

        hr {
            border: 1px dashed black;
        }
    </style>

</head>

<body onload="window.print()">

    <h3>Z READING</h3>

    Date: <?= $row['date'] ?>

    <hr>

    Opening: <?= number_format($row['opening_cash'], 2) ?>

    <br>

    Cash: <?= number_format($row['cash_sales'], 2) ?>

    <br>

    GCash: <?= number_format($row['gcash_sales'], 2) ?>

    <br>

    Card: <?= number_format($row['card_sales'], 2) ?>

    <hr>

    Orders: <?= $row['total_orders'] ?>

    <br>

    Gross: <?= number_format($row['total_sales'] + $row['total_discount'], 2) ?><br>

    Discount: <?= number_format($row['total_discount'], 2) ?><br>

    <strong>Net: <?= number_format($row['total_sales'], 2) ?></strong>
    Expected: <?= number_format($row['expected_cash'], 2) ?>

    <br>

    Closing: <?= number_format($row['closing_cash'], 2) ?>

    <hr>

    END OF REPORT

    <hr>

    CASHIER BREAKDOWN

    <hr>

    <?php while ($c = $cashier_query->fetch_assoc()):

        $cash_sales = $c['cash_sales'] ?? 0;
        $gcash_sales = $c['gcash_sales'] ?? 0;
        $card_sales = $c['card_sales'] ?? 0;

        $expected_cashier = $c['opening_cash'] + $cash_sales;
    ?>

        <?= strtoupper($c['cashier_name'] ?? 'CASHIER') ?><br>

        Opening: <?= number_format($c['opening_cash'], 2) ?><br>
        Cash: <?= number_format($cash_sales, 2) ?><br>
        GCash: <?= number_format($gcash_sales, 2) ?><br>
        Card: <?= number_format($card_sales, 2) ?><br>

        Expected: <?= number_format($expected_cashier, 2) ?><br>

        <hr>

    <?php endwhile; ?>

</body>

</html>