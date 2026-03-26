<?php
include '../db.php';

$id = $_GET['id'];

$q = $conn->query("
SELECT *
FROM z_reading
WHERE id = $id
");

$row = $q->fetch_assoc();
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

    Sales: <?= number_format($row['total_sales'], 2) ?>

    <hr>

    Discount: <?= number_format($row['total_discount'], 2) ?>

    Expected: <?= number_format($row['expected_cash'], 2) ?>

    <br>

    Closing: <?= number_format($row['closing_cash'], 2) ?>

    <hr>

    END OF REPORT

</body>

</html>