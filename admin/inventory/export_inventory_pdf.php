<?php
include '../../db.php';

$from = $_GET['from'];
$to   = $_GET['to'];

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

?>

<h3>Inventory Movement</h3>

<table border="1" cellspacing="0" cellpadding="5">

<tr>
<th>Product</th>
<th>SKU</th>
<th>Stock</th>
<th>Sold</th>
</tr>

<?php while($r=$q->fetch_assoc()): ?>

<tr>

<td><?= $r['name'] ?></td>
<td><?= $r['sku'] ?></td>
<td><?= $r['stock'] ?></td>
<td><?= $r['sold'] ?></td>

</tr>

<?php endwhile; ?>

</table>

<script>
window.print();
</script>