<?php
include '../header.php';
include '../../db.php';


$from = $_GET['from'] ?? date("Y-m-01");
$to   = $_GET['to'] ?? date("Y-m-d");


$query = $conn->query("
SELECT 

p.id,
p.name,
p.sku,
p.stock,

IFNULL(SUM(
CASE
WHEN o.status != 'Voided'
AND DATE(o.created_at) BETWEEN '$from' AND '$to'
THEN oi.quantity
ELSE 0
END
),0) as sold

FROM products p

LEFT JOIN order_items oi 
ON oi.product_id = p.id

LEFT JOIN orders o
ON o.id = oi.order_id
AND o.status != 'Voided'
AND DATE(o.created_at)
BETWEEN '$from' AND '$to'

GROUP BY p.id

ORDER BY CAST(p.sku AS UNSIGNED) ASC
");

?>

<h3>Inventory Movement Sold</h3>

<form method="get">

    From
    <input type="date" name="from" value="<?= $from ?>">

    To
    <input type="date" name="to" value="<?= $to ?>">

    <button class="btn btn-primary">Filter</button>

</form>

<br>

<a href="export_inventory_csv.php?from=<?= $from ?>&to=<?= $to ?>"
    class="btn btn-success">
    Export CSV
</a>

<a href="export_inventory_pdf.php?from=<?= $from ?>&to=<?= $to ?>"
    class="btn btn-danger" target="_blank">
    Export PDF
</a>

<br><br>

<table class="table table-bordered">

    <tr>

        <th>Product</th>
        <th>SKU</th>
        <th>Current Stock</th>
        <th>Sold</th>
        <th>Remaining</th>

    </tr>

    <?php while ($r = $query->fetch_assoc()): ?>

        <tr>

            <td><?= $r['name'] ?></td>

            <td><?= $r['sku'] ?></td>

            <td><?= $r['stock'] ?></td>

            <td><?= $r['sold'] ?></td>

            <td><?= $r['stock'] ?></td>

        </tr>

    <?php endwhile; ?>

</table>

<?php include '../footer.php'; ?>