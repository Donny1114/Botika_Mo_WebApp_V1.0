<?php
include '../db.php';

$from=$_GET['from'];
$to=$_GET['to'];

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
?>

<h3>Inventory Movement</h3>

<table border="1" cellpadding="5">

<tr>

<th>Product</th>
<th>SKU</th>
<th>IN</th>
<th>OUT</th>
<th>ADJUST</th>
<th>Stock</th>

</tr>

<?php while($r=$q->fetch_assoc()): ?>

<tr>

<td><?= $r['name'] ?></td>
<td><?= $r['sku'] ?></td>

<td><?= $r['stock_in'] ?></td>
<td><?= $r['stock_out'] ?></td>
<td><?= $r['adjust'] ?></td>

<td><?= $r['stock'] ?></td>

</tr>

<?php endwhile; ?>

</table>

<script>
window.print();
</script>