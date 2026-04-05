<?php
include 'header.php';
include '../db.php';


/* ======================
TOTAL SALES / ORDERS
====================== */

$total_query = $conn->query("
SELECT 
COUNT(DISTINCT oi.order_id) AS total_orders,

COALESCE(SUM(
(oi.sell_price * oi.quantity)
-
((oi.sell_price * oi.quantity) * (oi.discount_percent / 100))
),0) as total_sales,

COALESCE(SUM(
(oi.sell_price * oi.quantity) * (oi.discount_percent / 100)
),0) as total_discount

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
    $stmt = $conn->prepare("
    SELECT COALESCE(SUM(
        (oi.sell_price * oi.quantity)
        -
        ((oi.sell_price * oi.quantity) * (oi.discount_percent / 100))
    ),0) as total

    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.payment_method = ?
    AND o.status != 'Voided'
    AND DATE(o.created_at)=CURDATE()
    ");

    $stmt->bind_param("s", $method);
    $stmt->execute();

    $res = $stmt->get_result()->fetch_assoc();

    return $res['total'] ?? 0;
}

$cash  = getTotal("Cash", $conn);
$gcash = getTotal("GCash", $conn);
$card  = getTotal("Card", $conn);

/* ======================
CASHIER BREAKDOWN (FIXED)
====================== */

$cashier_query = $conn->query("
SELECT 
cs.id,
cs.cashier_id,
COALESCE(u.name, CONCAT('Cashier #', cs.cashier_id)) AS cashier_name,
cs.opening_cash,

COALESCE(SUM(CASE 
    WHEN o.payment_method = 'Cash' 
    THEN (oi.sell_price * oi.quantity) 
         - ((oi.sell_price * oi.quantity) * (oi.discount_percent / 100))
    ELSE 0 END
),0) as cash_sales,

COALESCE(SUM(CASE 
    WHEN o.payment_method = 'GCash' 
    THEN (oi.sell_price * oi.quantity) 
         - ((oi.sell_price * oi.quantity) * (oi.discount_percent / 100))
    ELSE 0 END
),0) as gcash_sales,

COALESCE(SUM(CASE 
    WHEN o.payment_method = 'Card' 
    THEN (oi.sell_price * oi.quantity) 
         - ((oi.sell_price * oi.quantity) * (oi.discount_percent / 100))
    ELSE 0 END
),0) as card_sales

FROM cashier_shift cs

LEFT JOIN users u 
ON u.id = cs.cashier_id

LEFT JOIN orders o 
ON o.cashier_id = cs.cashier_id 
AND DATE(o.created_at)=CURDATE()
AND o.status != 'Voided'

LEFT JOIN order_items oi 
ON oi.order_id = o.id

WHERE DATE(cs.open_time) = CURDATE()  -- only include shifts opened today

GROUP BY cs.id
");
/* ======================
TOTAL OPENING CASH (FIXED)
====================== */

$opening_query = $conn->query("
SELECT SUM(opening_cash) as total_opening
FROM cashier_shift
WHERE status = 'open'
");

$rowOpening = $opening_query->fetch_assoc();
$opening = $rowOpening['total_opening'] ?? 0;


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

        /* ======================
   AUTO CLOSE SHIFT (NEW)
====================== */

        // close all open shifts
        $conn->query("
        UPDATE cashier_shift
        SET status='closed', close_time=NOW()
        WHERE status='open'
    ");

        // clear current order session (optional but recommended)
        unset($_SESSION['order_id']);

        /* ======================
         SUCCESS + REDIRECT
        ====================== */
        //  window.location.href = '../shift_start.php' after alert;
        echo "<script>
        alert('Z Reading Saved. Shift Closed.');
        window.location.href = 'z_history.php';
        </script>";
        exit;
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

    <p>Gross Sales: ₱<?= number_format($totalSales + $totalDiscount, 2) ?></p>

    <p>Total Discount: ₱<?= number_format($totalDiscount, 2) ?></p>

    <p><strong>Net Sales: ₱<?= number_format($totalSales, 2) ?></strong></p>

    <hr>

    <p><strong>Expected Total Cash (All Cashiers): ₱<?= number_format($expected, 2) ?></strong></p>

    <hr>
    <h5>Cashier Breakdown</h5>

    <?php while ($c = $cashier_query->fetch_assoc()):

        $cash_sales = $c['cash_sales'] ?? 0;
        $gcash_sales = $c['gcash_sales'] ?? 0;
        $card_sales = $c['card_sales'] ?? 0;

        $expected_cashier = $c['opening_cash'] + $cash_sales;
    ?>

        <div class="border p-2 mb-2">

            <strong><?= htmlspecialchars($c['cashier_name']) ?></strong><br>

            Opening: ₱<?= number_format($c['opening_cash'], 2) ?><br>

            Cash: ₱<?= number_format($cash_sales, 2) ?><br>
            GCash: ₱<?= number_format($gcash_sales, 2) ?><br>
            Card: ₱<?= number_format($card_sales, 2) ?><br>

            <b>Expected: ₱<?= number_format($expected_cashier, 2) ?></b>

        </div>

    <?php endwhile; ?>

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