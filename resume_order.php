<?php
include 'header.php';
include 'db.php';

$orders = $conn->query("
SELECT id, created_at
FROM orders
WHERE status='suspended'
ORDER BY id DESC
");

?>

<h3>Suspended Orders</h3>

<table class="table">

<tr>
<th>ID</th>
<th>Date</th>
<th>Action</th>
</tr>

<?php while($o=$orders->fetch_assoc()): ?>

<tr>

<td><?= $o['id'] ?></td>

<td><?= $o['created_at'] ?></td>

<td>

<a href="order.php?resume=<?= $o['id'] ?>"
class="btn btn-success">

Resume

</a>

</td>

</tr>

<?php endwhile; ?>

</table>