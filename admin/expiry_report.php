<?php
include 'header.php';
include '../db.php';

$q=$conn->query("
SELECT 
p.name,
b.batch_number,
b.expiry_date,
b.qty

FROM product_batches b

JOIN products p
ON p.id=b.product_id

WHERE b.expiry_date <= DATE_ADD(CURDATE(),INTERVAL 90 DAY)

ORDER BY b.expiry_date
");
?>

<h3>Expiry Report (Next 90 days)</h3>

<table class="table table-bordered">

<tr>
<th>Product</th>
<th>Batch</th>
<th>Expiry</th>
<th>Qty</th>
</tr>

<?php while($r=$q->fetch_assoc()): ?>

<tr>

<td><?= $r['name'] ?></td>

<td><?= $r['batch_number'] ?></td>

<td><?= $r['expiry_date'] ?></td>

<td><?= $r['qty'] ?></td>

</tr>

<?php endwhile; ?>

</table>