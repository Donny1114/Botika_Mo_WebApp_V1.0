<?php
include 'header.php';
include '../db.php';
?>

<div class="card p-3">
    <h4>Z Reading History</h4>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Total Orders</th>
                <th>Total Sales</th>
                <th>Total Discount</th>
                <th>Closing Cash</th>
                <th>View</th>
                <th>Print</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $limit = 50;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $offset = ($page - 1) * $limit;
            $q = $conn->query("SELECT * FROM z_reading ORDER BY date DESC LIMIT $limit OFFSET $offset");
            while ($row = $q->fetch_assoc()):
            ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= $row['date'] ?></td>
                    <td><?= $row['total_orders'] ?></td>
                    <td>₱<?= number_format($row['total_sales'], 2) ?></td>
                    <td>₱<?= number_format($row['total_discount'], 2) ?></td>
                    <td>₱<?= number_format($row['closing_cash'], 2) ?></td>
                    <td>
                        <a href="z_view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">View</a>
                    </td>
                    <td>
                        <a href="z_print.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-success">Print</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>