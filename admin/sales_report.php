<?php
include '../db.php';


/* =========================
   FILTER LOGIC
========================= */
$filter    = $_GET['filter'] ?? 'daily';
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // default: first day of current month
$endDate   = $_GET['end_date'] ?? date('Y-m-t');   // default: last day of current month

switch ($filter) {
    case 'weekly':
        $periodExpr = "CONCAT(YEAR(o.created_at), '-W', LPAD(WEEK(o.created_at),2,'0'))";
        $groupBy    = "YEAR(o.created_at), WEEK(o.created_at)";
        break;
    case 'monthly':
        $periodExpr = "DATE_FORMAT(o.created_at, '%Y-%m')";
        $groupBy    = "YEAR(o.created_at), MONTH(o.created_at)";
        break;
    case 'yearly':
        $periodExpr = "YEAR(o.created_at)";
        $groupBy    = "YEAR(o.created_at)";
        break;
    default: // daily
        $periodExpr = "DATE(o.created_at)";
        $groupBy    = "DATE(o.created_at)";
}

/* =========================
   MAIN REPORT QUERY
========================= */
$query = "
    SELECT
        $periodExpr AS period,

        SUM(oi.quantity * oi.sell_price) AS gross_sales,

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
            (oi.quantity * oi.sell_price)
            -
            ((oi.quantity * oi.sell_price) * (o.discount_percent / 100))
        ) AS net_sales,

        SUM(oi.quantity * oi.cost_price) AS cogs,

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
    WHERE DATE(o.created_at) BETWEEN ? AND ?
    AND o.status != 'Voided'
    GROUP BY $groupBy
    ORDER BY MIN(o.created_at) DESC
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$totalGross = 0;
$totalCogs  = 0;
$totalNetSales = 0;
$totalNet   = 0;
$totalDiscount = 0;

$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
    $totalGross += $row['gross_sales'] ?? 0;
    $totalNetSales += $row['net_sales'] ?? 0;
    $totalDiscount += $row['discount_total'] ?? 0;
    $totalCogs  += $row['cogs'] ?? 0;
    $totalNet   += $row['net_profit'] ?? 0;
}

/* =========================
   EXPORT LOGIC
========================= */
/* =========================
   EXPORT LOGIC
========================= */
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];

    // Calculate totals
    $totalGrossExport = array_sum(array_column($rows, 'gross_sales'));
    $totalDiscountExport = array_sum(array_column($rows, 'discount_total'));
    $totalNetSalesExport = array_sum(array_column($rows, 'net_sales'));
    $totalCogsExport  = array_sum(array_column($rows, 'cogs'));
    $totalNetExport   = array_sum(array_column($rows, 'net_profit'));

    if ($exportType === 'csv') {
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="sales_report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Period', 'Gross Sales', 'Net Sales', 'Discount', 'COGS', 'Net Profit']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['period'], $r['gross_sales'], $r['net_sales'], $r['discount_total'], $r['cogs'], $r['net_profit']]);
        }
        // Add total row
        fputcsv($out, ['TOTAL', $totalGrossExport, $totalNetSalesExport, $totalDiscountExport, $totalCogsExport, $totalNetExport]);
        fclose($out);
        exit;
    } elseif ($exportType === 'pdf') {
        require_once __DIR__ . '/../vendor/autoload.php'; // TCPDF
        if (ob_get_length()) ob_end_clean();
        $pdf = new TCPDF();
        $pdf->AddPage();
        $html = "<h2>Sales Report ($startDate to $endDate)</h2>
        <table border='1' cellpadding='5'>
        <thead><tr><th>Period</th><th>Gross Sales</th><th>Net Sales</th><th>Discount</th><th>COGS</th><th>Net Profit</th></tr></thead><tbody>";
        foreach ($rows as $r) {
            $html .= "<tr>
                        <td>{$r['period']}</td>
                        <td>PHP" . number_format((float)$r['gross_sales'], 2) . "</td>
                        <td>PHP" . number_format((float)$r['net_sales'], 2) . "</td>
                        <td>PHP" . number_format((float)$r['discount_total'], 2) . "</td>
                        <td>PHP" . number_format((float)$r['cogs'], 2) . "</td>
                        <td>PHP" . number_format((float)$r['net_profit'], 2) . "</td>
                      </tr>";
        }
        // Add total row
        $html .= "<tr style='font-weight:bold; background-color:#f0f0f0;'>
                    <td>TOTAL</td>
                    <td>PHP" . number_format($totalGrossExport, 2) . "</td>
                    <td>PHP" . number_format($totalNetSalesExport, 2) . "</td>
                    <td>PHP" . number_format($totalDiscountExport, 2) . "</td>
                    <td>PHP" . number_format($totalCogsExport, 2) . "</td>
                    <td>PHP" . number_format($totalNetExport, 2) . "</td>
                  </tr>";

        $html .= "</tbody></table>";
        $pdf->writeHTML($html);
        $pdf->Output('sales_report.pdf', 'D');
        exit;
    }
}

include 'header.php';


?>

<h2>Sales Report</h2>

<!-- FILTER FORM -->
<form method="get" class="row g-3 mb-4">
    <div class="col-md-3">
        <label>Start Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
    </div>
    <div class="col-md-3">
        <label>End Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
    </div>
    <div class="col-md-3">
        <label>View by</label>
        <select name="filter" class="form-control">
            <?php foreach (['daily', 'weekly', 'monthly', 'yearly'] as $v): ?>
                <option value="<?= $v ?>" <?= $v == $filter ? 'selected' : '' ?>><?= ucfirst($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3 align-self-end">
        <button class="btn btn-primary">Filter</button>
        <a href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&filter=<?= $filter ?>&export=csv" class="btn btn-success">CSV</a>
        <a href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&filter=<?= $filter ?>&export=pdf" class="btn btn-danger">PDF</a>
    </div>
</form>

<!-- SUMMARY CARDS -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-bg-primary p-3">
            <h6>Gross Sales</h6>
            <h4>₱<?= number_format($totalGross, 2) ?></h4>
        </div>

    </div>
    <div class="col-md-4">
        <div class="card text-bg-info p-3">
            <h6>Net Sales</h6>
            <h4>₱<?= number_format($totalNetSales, 2) ?></h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-bg-danger p-3">
            <h6>Discount</h6>
            <h4>₱<?= number_format($totalDiscount, 2) ?></h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-bg-warning p-3">
            <h6>COGS</h6>
            <h4>₱<?= number_format($totalCogs, 2) ?></h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-bg-success p-3">
            <h6>Net Profit</h6>
            <h4>₱<?= number_format($totalNet, 2) ?></h4>
        </div>
    </div>
</div>

<!-- SALES TABLE -->
<table class="table table-bordered">
    <thead class="table-dark">
        <tr>
            <th>Period</th>
            <th>Gross Sales</th>
            <th>Net Sales</th>
            <th>Discount</th>
            <th>COGS</th>
            <th>Net Profit</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= $r['period'] ?></td>
                <td>₱<?= number_format((float)$r['gross_sales'], 2) ?></td>
                <td>₱<?= number_format((float)$r['net_sales'], 2) ?></td>
                <td>₱<?= number_format((float)$r['discount_total'], 2) ?></td>
                <td>₱<?= number_format((float)$r['cogs'], 2) ?></td>
                <td>₱<?= number_format((float)$r['net_profit'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot class="table-secondary">
        <tr>
            <th>TOTAL</th>
            <th>₱<?= number_format($totalGross, 2) ?></th>
            <th>₱<?= number_format($totalNetSales, 2) ?></th>
            <th>₱<?= number_format($totalDiscount, 2) ?></th>
            <th>₱<?= number_format($totalCogs, 2) ?></th>
            <th>₱<?= number_format($totalNet, 2) ?></th>
        </tr>
    </tfoot>
</table>

<!-- CHART -->
<canvas id="salesChart" height="100"></canvas>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const labels = <?= json_encode(array_column($rows, 'period')) ?>;
    const gross = <?= json_encode(array_column($rows, 'gross_sales')) ?>;
    const netSales = <?= json_encode(array_column($rows, 'net_sales')) ?>;
    const net = <?= json_encode(array_column($rows, 'net_profit')) ?>;
    const discount = <?= json_encode(array_column($rows, 'discount_total')) ?>;

    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                    label: 'Gross Sales',
                    data: gross,
                    backgroundColor: '#0d6efd'
                },
                {
                    label: 'Net Sales',
                    data: netSales,
                    backgroundColor: '#6c757d'
                },
                {
                    label: 'Discount',
                    data: discount,
                    backgroundColor: '#dc3545'
                },
                {
                    label: 'Net Profit',
                    data: net,
                    backgroundColor: '#198754'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });
</script>

<?php include 'footer.php'; ?>