<?php
include 'header.php';
include '../db.php';


/* =========================
   CASH SALES TODAY
========================= */
$cash_sales = $conn->query("
SELECT SUM(
(oi.sell_price * oi.quantity)
-
((oi.sell_price * oi.quantity) * (o.discount_percent / 100))
) as total
FROM order_items oi
JOIN orders o ON o.id = oi.order_id
WHERE o.payment_method='Cash'
AND o.status != 'Voided'
AND DATE(o.created_at)=CURDATE()
");

$rowCash = $cash_sales->fetch_assoc();
$cash = $rowCash['total'] ?? 0;


/* =========================
   GCASH SALES TODAY
========================= */
$gcash_sales = $conn->query("
SELECT SUM(
(oi.sell_price * oi.quantity)
-
((oi.sell_price * oi.quantity) * (o.discount_percent / 100))
) as total
FROM order_items oi
JOIN orders o ON o.id = oi.order_id
WHERE o.payment_method='GCash'
AND o.status != 'Voided'
AND DATE(o.created_at)=CURDATE()
");

$rowGCash = $gcash_sales->fetch_assoc();
$gcash = $rowGCash['total'] ?? 0;


/* =========================
   CARD SALES TODAY
========================= */
$card_sales = $conn->query("
SELECT SUM(
(oi.sell_price * oi.quantity)
-
((oi.sell_price * oi.quantity) * (o.discount_percent / 100))
) as total
FROM order_items oi
JOIN orders o ON o.id = oi.order_id
WHERE o.payment_method='Card'
AND o.status != 'Voided'
AND DATE(o.created_at)=CURDATE()
");
$rowCard = $card_sales->fetch_assoc();
$card = $rowCard['total'] ?? 0;


/* =========================
   TOTAL SALES + TOTAL ORDERS
========================= */

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
WHERE DATE(created_at)=CURDATE()
AND o.status != 'Voided'
");

$rowTotal = $total_query->fetch_assoc();

$totalOrders = $rowTotal['total_orders'] ?? 0;
$totalSales = $rowTotal['total_sales'] ?? 0;
$totalDiscount = $rowTotal['total_discount'] ?? 0;

/* =========================
   TOTAL OPENING CASH (FIXED)
========================= */

$opening_query = $conn->query("
SELECT SUM(opening_cash) as total_opening
FROM cashier_shift
WHERE status = 'open'
");

$rowOpening = $opening_query->fetch_assoc();
$opening = $rowOpening['total_opening'] ?? 0;


/* =========================
   EXPECTED CASH
========================= */

$expected_cash = $opening + $cash;

/* ======================
CASHIER BREAKDOWN (NEW)
====================== */

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
AND DATE(o.created_at)=CURDATE()
AND o.status != 'Voided'

LEFT JOIN order_items oi 
ON oi.order_id = o.id

GROUP BY cs.id
");

?>


<div class="card p-3 mb-3">

   <h4>X Reading (Today)</h4>

   <hr>

   <p>Opening Cash: ₱<?= number_format($opening, 2) ?></p>

   <p>Cash Sales: ₱<?= number_format($cash, 2) ?></p>

   <p>GCash Sales: ₱<?= number_format($gcash, 2) ?></p>

   <p>Card Sales: ₱<?= number_format($card, 2) ?></p>

   <hr>

   <p>Total Orders: <?= number_format($totalOrders) ?></p>

   <p>Total Discount: ₱<?= number_format($totalDiscount, 2) ?></p>

   <p>Total Sales (All Payments): ₱<?= number_format($totalSales, 2) ?></p>

   <hr>

   <h4>Expected Drawer Cash: ₱<?= number_format($expected_cash, 2) ?></h4>

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