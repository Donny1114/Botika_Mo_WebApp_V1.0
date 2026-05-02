<?php

include '../db.php';

/* =========================
   FILTER INPUT (DEFAULT TODAY)
========================= */
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

/* =========================
   PAGE SIZE
========================= */
$perPage = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

if (!in_array($perPage, [50, 100, 200, 999999])) {
    $perPage = 50;
}

$isExport = isset($_GET['export']);

/* =========================
   PAGINATION
========================= */
if ($isExport) {
    $perPage = 999999999;
    $page = 1;
    $offset = 0;
} else {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $perPage;
}

/* =========================
   TOTAL COUNT (CATEGORY)
========================= */
$count_sql = "
SELECT COUNT(DISTINCT c.id) AS total
FROM order_items oi
JOIN products p ON p.id = oi.product_id
JOIN categories c ON c.id = p.category_id
JOIN orders o ON o.id = oi.order_id
WHERE DATE(o.created_at) BETWEEN ? AND ?
AND o.status != 'Voided'
";

$count_stmt = mysqli_prepare($conn, $count_sql);
mysqli_stmt_bind_param($count_stmt, "ss", $startDate, $endDate);
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);

$totalRow = mysqli_fetch_assoc($count_result);
$totalRecords = $totalRow['total'];
$totalPages = ceil($totalRecords / $perPage);

/* =========================
   TOTALS (ALL DATA)
========================= */
$total_sql = "
SELECT
    SUM(oi.quantity) AS total_qty,
    SUM(oi.quantity * oi.sell_price) AS total_sales,
    SUM((oi.quantity * oi.sell_price) * (IFNULL(oi.discount_percent, 0) / 100)) AS total_discount,
    SUM(oi.quantity * oi.cost_price) AS total_cost,
    SUM(
        (
            (oi.quantity * oi.sell_price)
            - ((oi.quantity * oi.sell_price) * (IFNULL(oi.discount_percent, 0) / 100))
        ) - (oi.quantity * oi.cost_price)
    ) AS total_profit

FROM order_items oi
JOIN products p ON p.id = oi.product_id
JOIN categories c ON c.id = p.category_id
JOIN orders o ON o.id = oi.order_id

WHERE DATE(o.created_at) BETWEEN ? AND ?
AND o.status != 'Voided'
";

$total_stmt = mysqli_prepare($conn, $total_sql);
mysqli_stmt_bind_param($total_stmt, "ss", $startDate, $endDate);
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$totals = mysqli_fetch_assoc($total_result);

/* =========================
   MAIN QUERY (CATEGORY)
========================= */
$sql = "
SELECT
    c.id AS category_id,
    c.name AS category_name,

    SUM(oi.quantity) AS qty_sold,
    SUM(oi.quantity * oi.sell_price) AS gross_sales,

    SUM((oi.quantity * oi.sell_price) * (IFNULL(oi.discount_percent, 0) / 100)) AS discount_total,

    SUM(
        (oi.quantity * oi.sell_price)
        - ((oi.quantity * oi.sell_price) * (IFNULL(oi.discount_percent, 0) / 100))
    ) AS net_sales,

    SUM(oi.quantity * oi.cost_price) AS total_cost,

    SUM(
        (
            (oi.quantity * oi.sell_price)
            - ((oi.quantity * oi.sell_price) * (IFNULL(oi.discount_percent, 0) / 100))
        )
        - (oi.quantity * oi.cost_price)
    ) AS net_profit

FROM order_items oi
JOIN products p ON p.id = oi.product_id
JOIN categories c ON c.id = p.category_id
JOIN orders o ON o.id = oi.order_id

WHERE DATE(o.created_at) BETWEEN ? AND ?
AND o.status != 'Voided'

GROUP BY c.id, c.name
ORDER BY gross_sales DESC
LIMIT $perPage OFFSET $offset
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$rows = [];

$totalQty = 0;
$totalSales = 0;
$totalCost = 0;
$totalProfit = 0;
$totalDiscount = 0;

while ($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;

    $totalQty += $r['qty_sold'];
    $totalSales += $r['gross_sales'];
    $totalCost += $r['total_cost'];
    $totalProfit += $r['net_profit'];
    $totalDiscount += $r['discount_total'];
}

/* =========================
   EXPORT
========================= */
if (isset($_GET['export'])) {

    $type = $_GET['export'];

    // CSV
    if ($type == "csv") {

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=category_profit_report.csv");

        $out = fopen("php://output", "w");

        fputcsv($out, [
            'Category',
            'Qty Sold',
            'Gross Sales',
            'Discount',
            'Net Sales',
            'Total Cost',
            'Net Profit'
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['category_name'],
                $r['qty_sold'],
                $r['gross_sales'],
                $r['discount_total'],
                $r['net_sales'],
                $r['total_cost'],
                $r['net_profit']
            ]);
        }

        fputcsv($out, [
            'TOTAL',
            $totalQty,
            $totalSales,
            $totalDiscount,
            $totalSales - $totalDiscount,
            $totalCost,
            $totalProfit
        ]);

        fclose($out);
        exit;
    }

    // PDF
    if ($type == "pdf") {

        ob_start();

        require_once __DIR__ . '/../vendor/autoload.php';


        if (ob_get_length()) ob_end_clean();
        require_once __DIR__ . '/../vendor/autoload.php';

        $pdf = new TCPDF();
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();

        $html = "<h2>Category Profit Report ($startDate to $endDate)</h2>";

        $html .= "<table border='1' cellpadding='4'>
        <tr style='font-weight:bold; background:#f0f0f0;'>
            <th>Category</th>
            <th>Qty</th>
            <th>Gross</th>
            <th>Discount</th>
            <th>Net</th>
            <th>Cost</th>
            <th>Profit</th>
        </tr>";

        foreach ($rows as $r) {
            $html .= "<tr>
                <td>" . htmlspecialchars($r['category_name']) . "</td>
                <td>" . number_format($r['qty_sold'], 2) . "</td>
                <td>" . number_format($r['gross_sales'], 2) . "</td>
                <td>" . number_format($r['discount_total'], 2) . "</td>
                <td>" . number_format($r['net_sales'], 2) . "</td>
                <td>" . number_format($r['total_cost'], 2) . "</td>
                <td>" . number_format($r['net_profit'], 2) . "</td>
            </tr>";
        }
        $html .= "<tr style='font-weight:bold; background:#f0f0f0;'>
            <td>TOTAL</td>
            <td>" . number_format($totalQty, 2) . "</td>
            <td>" . number_format($totalSales, 2) . "</td>
            <td>" . number_format($totalDiscount, 2) . "</td>
            <td>" . number_format($totalSales - $totalDiscount, 2) . "</td>
            <td>" . number_format($totalCost, 2) . "</td>
            <td>" . number_format($totalProfit, 2) . "</td>
        </tr>";

        $html .= "</table>";

        $pdf->writeHTML($html);
        $pdf->Output("category_profit_report.pdf", "D");

        exit;
    }
}

/* =========================
   UI
========================= */
include 'header.php';
?>

<div class="container mt-4">
    <h2>Category Profit Report</h2>

    <form method="GET" class="row g-3 mb-4">

        <div class="col-md-2">
            <label>Show</label>
            <select name="limit" class="form-select">
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
                <option value="999999">All</option>
            </select>
        </div>

        <div class="col-md-3">
            <label>Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
        </div>

        <div class="col-md-3">
            <label>End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
        </div>

        <div class="col-md-6 d-flex align-items-end">
            <button class="btn btn-primary me-2">Filter</button>

            <a class="btn btn-success me-2"
                href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=csv">
                Export CSV
            </a>

            <a class="btn btn-danger"
                href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&export=pdf">
                Export PDF
            </a>
        </div>
    </form>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Category</th>
                <th>Qty Sold</th>
                <th>Gross Sales</th>
                <th>Discount</th>
                <th>Net Sales</th>
                <th>Total Cost</th>
                <th>Net Profit</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['category_name']) ?></td>
                    <td><?= number_format($r['qty_sold'], 2) ?></td>
                    <td>₱<?= number_format($r['gross_sales'], 2) ?></td>
                    <td>₱<?= number_format($r['discount_total'], 2) ?></td>
                    <td>₱<?= number_format($r['net_sales'], 2) ?></td>
                    <td>₱<?= number_format($r['total_cost'], 2) ?></td>
                    <td class="fw-bold text-success">
                        ₱<?= number_format($r['net_profit'], 2) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>

        <tfoot class="table-secondary">
            <tr>
                <th>TOTAL</th>
                <th><?= number_format($totals['total_qty'], 2) ?></th>
                <th>₱<?= number_format($totals['total_sales'], 2) ?></th>
                <th>₱<?= number_format($totals['total_discount'], 2) ?></th>
                <th>₱<?= number_format($totals['total_sales'] - $totals['total_discount'], 2) ?></th>
                <th>₱<?= number_format($totals['total_cost'], 2) ?></th>
                <th>₱<?= number_format($totals['total_profit'], 2) ?></th>
            </tr>
        </tfoot>
    </table>
</div>

<?php include 'footer.php'; ?>