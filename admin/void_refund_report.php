<?php
include 'header.php';
include '../db.php';


$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end'] ?? date('Y-m-d');


$q = $conn->prepare("
SELECT *
FROM audit_log
WHERE DATE(created_at) BETWEEN ? AND ?
AND action IN ('REMOVE ITEM','DELETE ORDER','REFUND','VOID','DISCOUNT')
ORDER BY created_at DESC
");

$q->bind_param("ss", $start, $end);
$q->execute();

$result = $q->get_result();

?>


<h3>VOID / REFUND REPORT</h3>

<form method="get" class="row mb-3">

<div class="col-md-3">
Start
<input type="date" name="start" class="form-control"
value="<?= $start ?>">
</div>

<div class="col-md-3">
End
<input type="date" name="end" class="form-control"
value="<?= $end ?>">
</div>

<div class="col-md-3 align-self-end">
<button class="btn btn-primary">Filter</button>
</div>

</form>


<table class="table table-bordered table-sm">

<tr>
<th>Date</th>
<th>User</th>
<th>Action</th>
<th>Order</th>
<th>Amount</th>
<th>Note</th>
</tr>


<?php while($row = $result->fetch_assoc()): ?>

<tr>

<td><?= $row['created_at'] ?></td>

<td><?= $row['user'] ?></td>

<td><?= $row['action'] ?></td>

<td><?= $row['order_id'] ?></td>



<td><?= number_format((float)$row['total_amount'],2) ?></td>

<td><?= $row['note'] ?></td>

</tr>

<?php endwhile; ?>

</table>


<?php include 'footer.php'; ?>