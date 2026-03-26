<?php
include '../db.php';

$from=$_GET['from'];
$to=$_GET['to'];

if (ob_get_length()) ob_end_clean();
header("Content-Type:text/csv");
header("Content-Disposition:attachment;filename=inventory.csv");

$out=fopen("php://output","w");

fputcsv($out,[
"Product",
"SKU",
"IN",
"OUT",
"ADJUST",
"STOCK"
]);

$q=$conn->query("
SELECT 

p.name,
p.sku,

SUM(CASE WHEN m.type='IN' THEN qty ELSE 0 END) stock_in,

SUM(CASE WHEN m.type='OUT' THEN qty ELSE 0 END) stock_out,

SUM(CASE WHEN m.type='ADJUST' THEN qty ELSE 0 END) adjust,

p.stock

FROM products p

LEFT JOIN inventory_movements m
ON m.product_id=p.id
AND DATE(m.created_at)
BETWEEN '$from' AND '$to'

GROUP BY p.id
");

while($r=$q->fetch_assoc())
{
fputcsv($out,$r);
}

fclose($out);