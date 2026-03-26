<?php
include 'db.php';

$order_id = $_GET['order_id'];

/* =========================
   DISCOUNT DATA (NEW)
========================= */


// Fetch order including discount
$order = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT discount_percent, discount_amount, grand_total 
     FROM orders WHERE id=$order_id"
));


$discountPercent = $order['discount_percent'] ?? 0;
$discountAmount  = $order['discount_amount'] ?? 0;
$grandTotal      = $order['grand_total'] ?? 0;

$items = mysqli_query($conn, "
SELECT oi.*, p.name
FROM order_items oi
JOIN products p ON oi.product_id=p.id
WHERE oi.order_id=$order_id
");
?>

<!DOCTYPE html>
<html>

<head>
    <title>Receipt</title>

    <style>
        body {
            font-family: monospace;
            width: 280px;
        }

        .center {
            text-align: center;
        }

        table {
            width: 100%;
            font-size: 12px;
        }

        @media print {
            body {
                width: 58mm;
            }

            .no-print {
                display: none;
            }
        }
    </style>

</head>


<!-- PRINT + REDIRECT -->

<body onload="startPrint()">

    <script>
        function startPrint() {

            window.print();

        }


        /* AFTER PRINT → REDIRECT */

        window.onafterprint = function() {

            window.location.href = "order.php";

        };
    </script>



    <div class="center">
        <h3>Botika Mo</h3>
        <p>Order Confirmation #<?= $order_id ?></p>
        <p><?= date("Y-m-d H:i") ?></p>
    </div>

    <hr>

    <table>
        <?php
        $total = 0;
        while ($row = mysqli_fetch_assoc($items)) {
            $subtotal = $row['quantity'] * $row['sell_price'];
            $total += $subtotal;
        ?>

            <tr>
                <td><?= $row['name'] ?></td>
            </tr>

            <tr>
                <td><?= $row['quantity'] ?> x <?= $row['sell_price'] ?></td>
                <td align="right"><?= number_format($subtotal, 2) ?></td>
            </tr>

        <?php } ?>
    </table>

    <hr>

    <hr>

    <table>
        <tr>
            <td>Subtotal</td>
            <td align="right">₱<?= number_format($total, 2) ?></td>
        </tr>

        <?php if ($discountPercent > 0): ?>

            <tr>
                <td>Discount (<?= $discountPercent ?>%)</td>
                <td align="right">- ₱<?= number_format($discountAmount, 2) ?></td>
            </tr>

            <tr>
                <td><strong>Total</strong></td>
                <td align="right"><strong>₱<?= number_format($grandTotal, 2) ?></strong></td>
            </tr>

        <?php else: ?>

            <tr>
                <td><strong>Total</strong></td>
                <td align="right"><strong>₱<?= number_format($total, 2) ?></strong></td>
            </tr>

        <?php endif; ?>

    </table>

    <p class="center">Thank you!</p>
    <p class="center">Union, Libertad, Antique!</p>


    <!-- ======================
         NEW BUTTON (added)
    ======================= -->

    <div class="center no-print" style="margin-top:10px;">

        <a href="order.php" style="padding:8px 12px; background:#0d6efd; color:white; text-decoration:none;">
            New Order
        </a>

    </div>


</body>

</html>