<?php
include 'header.php';
include '../db.php';


/* ======================
TOTAL SALES / ORDERS
====================== */

$total_query = $conn->query("
SELECT 
COUNT(DISTINCT oi.order_id) AS total_orders,

SUM(
(oi.sell_price * oi.quantity)
-
((oi.sell_price * oi.quantity) * (o.discount_percent / 100))
) as total_sales,

SUM(
(oi.sell_price * oi.quantity) * (o.discount_percent / 100)
) as total_discount

FROM order_items oi
JOIN orders o ON o.id = oi.order_id
WHERE DATE(o.created_at)=CURDATE()
AND o.status != 'Voided'
");


$rowTotal = $total_query->fetch_assoc();

$totalOrders = $rowTotal['total_orders'] ?? 0;
$totalSales = $rowTotal['total_sales'] ?? 0;
$totalDiscount = $rowTotal['total_discount'] ?? 0;

/* ======================
PAYMENT SALES
====================== */

function getTotal($method, $conn)
{
    $q = $conn->query("
    SELECT SUM(
    (oi.sell_price * oi.quantity)
    -
    ((oi.sell_price * oi.quantity) * (o.discount_percent / 100))
    ) as total

    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.payment_method='$method'
    AND o.status != 'Voided'
    AND DATE(o.created_at)=CURDATE()
    ");

    $r = $q->fetch_assoc();
    return $r['total'] ?? 0;
}

$cash  = getTotal("Cash", $conn);
$gcash = getTotal("GCash", $conn);
$card  = getTotal("Card", $conn);


/* ======================
OPENING CASH
====================== */

$opening_query = $conn->query("
SELECT opening_cash
FROM cashier_shift
WHERE status = 'open'

");

$rowOpening = $opening_query->fetch_assoc();
$opening = $rowOpening['opening_cash'] ?? 0;


/* ======================
EXPECTED CASH
====================== */

$expected = $opening + $cash;


/* ======================
SAVE Z READING
====================== */

if (isset($_POST['save_z'])) {

    // check if already has Z today
    $check = $conn->query("
    SELECT id FROM z_reading
    WHERE date = CURDATE()
    ");

    if ($check->num_rows > 0) {
        echo "<div class='alert alert-danger'>
        Z Reading already saved today!
        </div>";
    } else {

        $closing = $_POST['closing_cash'];

        $stmt = $conn->prepare("
        INSERT INTO z_reading
        (date,total_orders,total_sales,total_discount,
        cash_sales,gcash_sales,card_sales,
        opening_cash,expected_cash,closing_cash)

        VALUES
        (CURDATE(),?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            "idddddddd",
            $totalOrders,
            $totalSales,
            $totalDiscount,
            $cash,
            $gcash,
            $card,
            $opening,
            $expected,
            $closing
        );

        $stmt->execute();

        echo "<div class='alert alert-success'>
        Z Reading Saved
        </div>";
    }
}
?>


<div class="card p-3">

    <h4>Z Reading (End of Day)</h4>

    <a href="z_history.php" class="btn btn-secondary mb-2">
        View Z Reading History
    </a>

    <hr>

    <p>Opening Cash: ₱<?= number_format($opening, 2) ?></p>

    <p>Cash Sales: ₱<?= number_format($cash, 2) ?></p>

    <p>GCash Sales: ₱<?= number_format($gcash, 2) ?></p>

    <p>Card Sales: ₱<?= number_format($card, 2) ?></p>

    <hr>

    <p>Total Orders: <?= $totalOrders ?></p>

    <p>Total Discount: ₱<?= number_format($totalDiscount, 2) ?></p>
    
    <p>Total Sales: ₱<?= number_format($totalSales, 2) ?></p>

    <hr>

    <p>Expected Cash: ₱<?= number_format($expected, 2) ?></p>


    <form method="post">

        <label>Closing Cash (actual drawer)</label>

        <input type="number"
            name="closing_cash"
            step="0.01"
            class="form-control"
            required>

        <br>

        <button name="save_z" class="btn btn-danger">
            Save Z Reading
        </button>

    </form>

</div>


<?php include 'footer.php'; ?>