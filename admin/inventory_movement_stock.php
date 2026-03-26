<?php
include 'header.php';
include '../db.php';

$from = $_GET['from'] ?? date("Y-m-01");
$to = $_GET['to'] ?? date("Y-m-d");


$q = $conn->query("
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
ORDER BY CAST(p.sku AS UNSIGNED) ASC
");
?>

<h3>Inventory Movement Stock</h3>

<form>

    From <input type="date" name="from" value="<?= $from ?>">

    To <input type="date" name="to" value="<?= $to ?>">

    <button>Filter</button>

    <a href="export_inventory_stock_csv.php?from=<?= $from ?>&to=<?= $to ?>"
        class="btn btn-success">
        Export CSV
    </a>

    <a href="export_inventory_stock_pdf.php?from=<?= $from ?>&to=<?= $to ?>"
        class="btn btn-danger"
        target="_blank">
        Export PDF
    </a>

</form>

<table class="table table-bordered table-striped">

    <tr>

        <th style="padding:8px;">Product</th>

        <th style="padding:8px;">SKU</th>

        <th style="padding:8px;">Stock IN</th>

        <th style="padding:8px;">Stock OUT</th>

        <th style="padding:8px;">Adjust</th>

        <th style="padding:8px;">Current</th>

    </tr>

    <?php while ($r = $q->fetch_assoc()): ?>

        <tr>

            <td style="padding:8px;">
                <?= $r['name'] ?>
            </td>

            <td style="padding:8px;">
                <?= $r['sku'] ?>
            </td>

            <td style="padding:8px;">
                <?= $r['stock_in'] ?>
            </td>

            <td style="padding:8px;">
                <?= $r['stock_out'] ?>
            </td>

            <td style="padding:8px;">
                <?= $r['adjust'] ?>
            </td>

            <td style="padding:8px;">
                <?= $r['stock'] ?>
            </td>

        </tr>

    <?php endwhile; ?>

</table>