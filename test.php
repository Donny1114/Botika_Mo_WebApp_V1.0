<?php

$cash_sales = $conn->query("
SELECT SUM(total_amount) as total
FROM orders
WHERE payment_method='Cash'
AND DATE(created_at)=CURDATE()
");

$cash = $cash_sales->fetch_assoc()['total'] ?? 0;
?>