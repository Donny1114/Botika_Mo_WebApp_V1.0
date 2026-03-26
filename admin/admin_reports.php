<?php
include 'header.php'; // 
include '../db.php';




// Handle filter
$filter = $_GET['filter'] ?? 'daily';
$startDate = $_GET['start_date'] ?? date('Y-m-01', strtotime('-1 days'));
$endDate   = $_GET['end_date'] ?? date('Y-m-t');

/* Append full-day time range */
$startDateTime = $startDate . " 00:00:00";
$endDateTime   = $endDate . " 23:59:59";

// Determine grouping and period label for SQL
switch ($filter) {
    case 'daily':
        $periodSelect = "DATE(o.created_at) AS period";
        $groupBy = "DATE(o.created_at)";
        break;
    case 'weekly':
        $periodSelect = "CONCAT(YEAR(o.created_at), '-W', LPAD(WEEK(o.created_at),2,'0')) AS period";
        $groupBy = "YEAR(o.created_at), WEEK(o.created_at)";
        break;
    case 'monthly':
        $periodSelect = "DATE_FORMAT(o.created_at, '%Y-%m') AS period";
        $groupBy = "YEAR(o.created_at), MONTH(o.created_at)";
        break;
    case 'yearly':
        $periodSelect = "YEAR(o.created_at) AS period";
        $groupBy = "YEAR(o.created_at)";
        break;
    default:
        $periodSelect = "DATE_FORMAT(o.created_at, '%Y-%m') AS period";
        $groupBy = "YEAR(o.created_at), MONTH(o.created_at)";
}

// Fetch report data
$sql = "
    SELECT 
        $periodSelect,

        COUNT(DISTINCT oi.order_id) AS total_orders,

        SUM(oi.quantity * oi.sell_price) AS gross_sales,

        SUM(oi.quantity * oi.cost_price) AS total_cogs,

        SUM(
            (oi.quantity * oi.sell_price)
            * (o.discount_percent / 100)
        ) AS discount_total,

        SUM(
            (oi.quantity * oi.sell_price)
            -
            ((oi.quantity * oi.sell_price) * (o.discount_percent / 100))
        ) AS net_sales,

        SUM(
            (
                (oi.quantity * oi.sell_price)
                -
                ((oi.quantity * oi.sell_price) * (o.discount_percent / 100))
            )
            -
            (oi.quantity * oi.cost_price)
        ) AS net_profit

    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.created_at BETWEEN ? AND ?
    AND o.status != 'Voided'
    GROUP BY $groupBy
    ORDER BY period ASC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $startDateTime, $endDateTime);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$reportData = [];
$totalGross = 0;
$totalNetSales = 0;
$totalCogs = 0;
$totalNet = 0;
$totalDiscount = 0;
$totalOrders = 0;


while ($row = mysqli_fetch_assoc($result)) {
    $row['gross_sales'] = (float)$row['gross_sales'];
    $row['net_sales'] = (float)$row['net_sales'];
    $row['total_cogs'] = (float)$row['total_cogs'];
    $row['net_profit'] = (float)$row['net_profit'];
    $row['total_orders'] = (int)$row['total_orders'];
    $row['discount_total'] = (float)$row['discount_total'];

    $totalGross += $row['gross_sales'];
    $totalNetSales += $row['net_sales'];
    $totalCogs += $row['total_cogs'];
    $totalNet += $row['net_profit'];
    $totalOrders += $row['total_orders'];
    $totalDiscount += $row['discount_total'];

    $reportData[] = $row;
}
// Cash Drawer Sales Today
$cash_sales = $conn->query("
SELECT SUM(sell_price * quantity) as total
FROM order_items oi
JOIN orders o ON o.id = oi.order_id
WHERE o.payment_method='Cash'
AND o.status != 'Voided'
AND DATE(o.created_at)=CURDATE()
");

$rowCash = $cash_sales->fetch_assoc();
$cash = $rowCash['total'] ?? 0;


// Opening Cash
$opening_query = $conn->query("
SELECT opening_cash
FROM cashier_shift
WHERE status = 'open'

");

$rowOpening = $opening_query->fetch_assoc();
$opening = $rowOpening['opening_cash'] ?? 0;


// Expected Drawer Cash
$expected_cash = $opening + $cash;

?>

<div class="container mt-4">
    <h2>Sales Dashboard</h2>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-3">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div class="col-md-3">
            <label>End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
        </div>
        <div class="col-md-3">
            <label>View By</label>
            <select name="filter" class="form-control">
                <option value="daily" <?= $filter == 'daily' ? 'selected' : '' ?>>Daily</option>
                <option value="weekly" <?= $filter == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                <option value="monthly" <?= $filter == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                <option value="yearly" <?= $filter == 'yearly' ? 'selected' : '' ?>>Yearly</option>
            </select>
        </div>
        <div class="col-md-3 align-self-end">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white p-3">
                <h4>Gross Sales</h4>
                <h3>₱<?= number_format($totalGross, 2) ?></h3>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-success text-white p-3">
                <h4>Net Sales</h4>
                <h3>₱<?= number_format($totalNetSales, 2) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white p-3">
                <h4>Total COGS</h4>
                <h3>₱<?= number_format($totalCogs, 2) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white p-3">
                <h4>Net Profit</h4>
                <h3>₱<?= number_format($totalNet, 2) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white p-3">
                <h4>Total Orders</h4>
                <h3><?= number_format($totalOrders) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark p-3">
                <h4>Total Discount</h4>
                <h3>₱<?= number_format($totalDiscount, 2) ?></h3>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <canvas id="salesChart" height="100"></canvas>

    <?php

    $today = date("Y-m-d");

    $query = "
SELECT 
COUNT(id) as total_orders,
SUM(total_amount) as total_sales
FROM orders
WHERE DATE(created_at)=?
AND status != 'Voided'
";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $today);
    $stmt->execute();

    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    ?>
    <div class="card p-3 mb-3">

        <h5>Cash Drawer Balance</h5>

        <p>Opening Cash: ₱<?= number_format($opening, 2) ?></p>

        <p>Cash Sales: ₱<?= number_format($cash, 2) ?></p>

        <hr>

        <h4>Expected Cash: ₱<?= number_format($expected_cash, 2) ?></h4>

    </div>
    <h3 class="mt-5">Detailed Report</h3>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Gross Sales</th>
                </th>Net Sales</th>
                <th>Discount</th>
                <th>COGS</th>
                <th>Net Profit</th>
                <th>Total Orders</th>
                <th>No. of Orders</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reportData as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['period']) ?></td>
                    <td>₱<?= number_format($row['gross_sales'], 2) ?></td>
                    <td>₱<?= number_format($row['net_sales'], 2) ?></td>
                    <td>₱<?= number_format($row['discount_total'], 2) ?></td>
                    <td>₱<?= number_format($row['total_cogs'], 2) ?></td>
                    <td>₱<?= number_format($row['net_profit'], 2) ?></td>
                    <td><?= number_format($row['total_orders']) ?></td>

                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    const chartLabels = <?= json_encode(array_column($reportData, 'period')) ?>;
    const grossData = <?= json_encode(array_column($reportData, 'gross_sales')) ?>;
    const netSalesData = <?= json_encode(array_column($reportData, 'net_sales')) ?>;

    const cogsData = <?= json_encode(array_column($reportData, 'total_cogs')) ?>;
    const netData = <?= json_encode(array_column($reportData, 'net_profit')) ?>;
    const discountData = <?= json_encode(array_column($reportData, 'discount_total')) ?>;
    const orderData = <?= json_encode(array_column($reportData, 'total_orders')) ?>;

    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                    label: 'Gross Sales',
                    data: grossData,
                    borderColor: 'green',
                    backgroundColor: 'rgba(0,128,0,0.2)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Net Sales',
                    data: netSalesData,
                    borderColor: 'blue',
                    backgroundColor: 'rgba(0,0,255,0.2)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'COGS',
                    data: cogsData,
                    borderColor: 'red',
                    backgroundColor: 'rgba(255,0,0,0.2)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Net Profit',
                    data: netData,
                    borderColor: 'brown',
                    backgroundColor: 'rgba(0,0,255,0.2)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Discount',
                    data: discountData,
                    borderColor: 'orange',
                    backgroundColor: 'rgba(255,165,0,0.2)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Total Orders',
                    data: orderData,
                    borderColor: 'purple',
                    backgroundColor: 'rgba(187, 19, 114, 0.2)',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Sales, Discount, COGS & Net Profit'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(val) {
                            return '₱' + val.toLocaleString();
                        }
                    }
                }
            }
        }
    });
</script>

<?php include 'footer.php'; ?>