<?php
include 'header.php';
include '../db.php';

$id = $_GET['id'];

$q = $conn->query("
SELECT *
FROM z_reading
WHERE id = $id
");

$row = $q->fetch_assoc();

?>

<div class="card p-3">

    <h4>Z Reading Details</h4>

    <a href="z_print.php?id=<?= $row['id'] ?>"
        class="btn btn-primary mb-2"
        target="_blank">

        Print

    </a>
    <a href="z_print.php?id=<?= $row['id'] ?>"
        class="btn btn-success mb-2"
        target="_blank">

        Save PDF

    </a>

    <p>Date: <?= $row['date'] ?></p>

    <hr>

    <p>Opening Cash: ₱<?= number_format($row['opening_cash'], 2) ?></p>

    <p>Cash Sales: ₱<?= number_format($row['cash_sales'], 2) ?></p>

    <p>GCash Sales: ₱<?= number_format($row['gcash_sales'], 2) ?></p>

    <p>Card Sales: ₱<?= number_format($row['card_sales'], 2) ?></p>

    <hr>

    <p>Total Orders: <?= $row['total_orders'] ?></p>

    <p>Total Sales: ₱<?= number_format($row['total_sales'], 2) ?></p>

    <p>Total Discount: ₱<?= number_format($row['total_discount'], 2) ?></p>

    <hr>

    <p>Expected Cash: ₱<?= number_format($row['expected_cash'], 2) ?></p>

    <p>Closing Cash: ₱<?= number_format($row['closing_cash'], 2) ?></p>

</div>

<?php include 'footer.php'; ?>