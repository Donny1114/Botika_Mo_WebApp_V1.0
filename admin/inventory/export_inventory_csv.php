<?php
include
include '../../db.php';

$from = $_GET['from'];
$to   = $_GET['to'];

if (ob_get_length()) ob_end_clean();
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=inventory.csv');

$output = fopen("php://output","w");

fputcsv($output,[
"Product",
"SKU",
"Stock",
"Sold"
]);

$q = $conn->query("
SELECT 
p.name,
p.sku,
p.stock,
IFNULL(SUM(oi.quantity),0) sold

FROM products p

LEFT JOIN order_items oi 
ON oi.product_id=p.id

LEFT JOIN orders o
ON o.id=oi.order_id
AND DATE(o.created_at)
BETWEEN '$from' AND '$to'

GROUP BY p.id
");

while($r=$q->fetch_assoc())
{
fputcsv($output,$r);
}

fclose($output);