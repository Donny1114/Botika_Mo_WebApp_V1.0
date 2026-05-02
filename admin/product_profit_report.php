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
/* =========================
   PAGINATION
========================= */
if ($isExport) {
    $perPage = 999999999; // effectively unlimited
    $page = 1;
    $offset = 0;
} else {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;

    $offset = ($page - 1) * $perPage;
}


/* =========================
   TOTAL COUNT
========================= */
$count_sql = "
SELECT COUNT(DISTINCT p.id) AS total
FROM order_items oi
JOIN products p ON p.id = oi.product_id
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
   TOTALS FOR FULL REPORT
========================= */
$total_sql = "
SELECT
    SUM(oi.quantity) AS total_qty,

    SUM(oi.quantity * oi.sell_price) AS total_sales,

    SUM(
        (oi.quantity * oi.sell_price)
        * (IFNULL(oi.discount_percent, 0) / 100)
    ) AS total_discount,

    SUM(oi.quantity * oi.cost_price) AS total_cost,

    SUM(
        (
            (oi.quantity * oi.sell_price)
            -
            ((oi.quantity * oi.sell_price)
            * (IFNULL(oi.discount_percent, 0) / 100))
        )
        -
        (oi.quantity * oi.cost_price)
    ) AS total_profit

FROM order_items oi
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
   QUERY
========================= */

$sql = "
SELECT
    p.id,
    p.name,
    p.sku,
    p.cost_price,
    p.price AS sell_price,

    SUM(oi.quantity) AS qty_sold,

    SUM(oi.quantity * oi.sell_price) AS gross_sales,

    SUM(
        (oi.quantity * oi.sell_price)
        * (IFNULL(oi.discount_percent, 0) / 100)
    ) AS discount_total,

    SUM(
        (oi.quantity * oi.sell_price)
        -
        ((oi.quantity * oi.sell_price)
        * (IFNULL(oi.discount_percent, 0) / 100))
    ) AS net_sales,

    SUM(oi.quantity * oi.cost_price) AS total_cost,

    SUM(
        (
            (oi.quantity * oi.sell_price)
            -
            ((oi.quantity * oi.sell_price)
            * (IFNULL(oi.discount_percent, 0) / 100))
        )
        -
        (oi.quantity * oi.cost_price)
    ) AS net_profit

FROM order_items oi
JOIN products p ON p.id = oi.product_id
JOIN orders o ON o.id = oi.order_id

WHERE DATE(o.created_at) BETWEEN ? AND ?
AND o.status != 'Voided'

GROUP BY p.id, p.name, p.sku, p.cost_price, p.price
ORDER BY gross_sales DESC
LIMIT $perPage OFFSET $offset
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$rows = [];

$totalSold = 0;
$totalSales = 0;
$totalCost  = 0;
$totalProfit = 0;
$totalDiscount = 0;


while ($r = mysqli_fetch_assoc($result)) {

    $rows[] = $r;

    $totalSold += $r['qty_sold'];
    $totalSales += $r['gross_sales'];
    $totalCost  += $r['total_cost'];
    $totalProfit += $r['net_profit'];
    $totalDiscount += $r['discount_total'];
}

if (isset($_GET['export'])) {
    $perPage = 999999; // get all
    $offset = 0;
}

/* =========================
   EXPORT FIRST (IMPORTANT)
========================= */

if (isset($_GET['export'])) {

    $type = $_GET['export'];

    // ---------- CSV ----------
    if ($type == "csv") {

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=product_profit_report.csv");
        header("Pragma: no-cache");
        header("Expires: 0");

        $out = fopen("php://output", "w");

        // HEADER
        fputcsv($out, [
            'Product',
            'SKU',
            'Cost Price',
            'Sell Price',
            'Qty Sold',
            'Gross Sales',
            'Discount Total',
            'Net Sales',
            'Total Cost',
            'Net Profit'
        ]);

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['name'],
                $r['sku'],
                $r['cost_price'],
                $r['sell_price'],
                $r['qty_sold'],
                $r['gross_sales'],
                $r['discount_total'],
                $r['net_sales'],
                $r['total_cost'],
                $r['net_profit']
            ]);
        }

        // ✅ ADD TOTAL ROW (FIXED)
        fputcsv($out, [
            'TOTAL',
            '',
            '',
            '',
            $totalSold,
            $totalSales,
            $totalDiscount,
            $totalSales - $totalDiscount,
            $totalCost,
            $totalProfit
        ]);

        fclose($out);
        exit;
    }


    // ---------- PDF ----------
    if ($type == "pdf") {

        ob_start();

        require_once __DIR__ . '/../vendor/autoload.php';


        if (ob_get_length()) ob_end_clean();

        $pdf = new TCPDF();
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();

        $html = "<h2>Product Profit Report ($startDate to $endDate)</h2>";

        $html .= "
        <table border='1' cellpadding='4'>
        
        <tr style='font-weight:bold; background-color:#f0f0f0;'>
            <th width='18%'>Product</th>
            <th width='10%'>SKU</th>
            <th width='8%'>Cost</th>
            <th width='8%'>Sell</th>
            <th width='8%'>Qty</th>
            <th width='12%'>Gross Sales</th>
            <th width='12%'>Discount</th>
            <th width='12%'>Net Sales</th>
            <th width='12%'>Total Cost</th>
            <th width='10%'>Net Profit</th>
        </tr>
        
        
        ";

        foreach ($rows as $r) {

            $html .= "<tr>
                <td>{$r['name']}</td>
                <td>{$r['sku']}</td>
                <td>" . number_format($r['cost_price'], 2) . "</td>
                <td>" . number_format($r['sell_price'], 2) . "</td>
                <td>{$r['qty_sold']}</td>
                <td>" . number_format($r['gross_sales'], 2) . "</td>
                <td>" . number_format($r['discount_total'], 2) . "</td>
                <td>" . number_format($r['net_sales'], 2) . "</td>
                <td>" . number_format($r['total_cost'], 2) . "</td>
                <td>" . number_format($r['net_profit'], 2) . "</td>
            </tr>";
        }

        $html .= "
            <tr style='font-weight:bold; background-color:#d9edf7;'>
                <td width='18%' colspan='2' align='right'><h2>TOTAL</h2></td>
                <td width='8%'></td>
                <td width='8%'></td>
                <td width='8%'></td>
                <td width='8%' align='center'>{$totalSold}</td>
                <td width='12%' align='right'>₱" . number_format($totalSales, 2) . "</td>
                <td width='12%' align='right'>₱" . number_format($totalDiscount, 2) . "</td>
                <td width='12%' align='right'>₱" . number_format($totalSales - $totalDiscount, 2) . "</td>
                <td width='12%' align='right'>₱" . number_format($totalCost, 2) . "</td>
                <td width='10%' align='right'>₱" . number_format($totalProfit, 2) . "</td>
            </tr>
            ";



        $html .= "</table>";

        $pdf->writeHTML($html);
        $pdf->Output("product_profit_report.pdf", "D");

        exit;
    }
}


/* =========================
   NORMAL PAGE
========================= */

include 'header.php';
?>


<div class="container mt-4">

    <h2>Product Profit Report</h2>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-2">
            <label>Show</label>
            <select name="limit" class="form-select">
                <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50 items</option>
                <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100 items</option>
                <option value="200" <?= $perPage == 200 ? 'selected' : '' ?>>200 items</option>
                <option value="999999" <?= $perPage == 999999 ? 'selected' : '' ?>>All items</option>
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
                href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&limit=<?= $perPage ?>&export=csv">
                Export CSV
            </a>

            <a class="btn btn-danger"
                href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&limit=<?= $perPage ?>&export=pdf">
                Export PDF
            </a>


        </div>

    </form>


    <table class="table table-bordered table-striped">

        <thead class="table-dark">

            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Cost Price</th>
                <th>Sell Price</th>
                <th>Qty Sold</th>
                <th>Gross Sales</th>
                <th>Discount Total</th>
                <th>Net Sales</th>
                <th>Total Cost</th>
                <th>Net Profit</th>
            </tr>

        </thead>

        <tbody>

            <?php foreach ($rows as $r): ?>

                <tr>

                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td><?= htmlspecialchars($r['sku']) ?></td>
                    <td>₱<?= number_format($r['cost_price'], 2) ?></td>

                    <td>₱<?= number_format($r['sell_price'], 2) ?></td>

                    <td><?= $r['qty_sold'] ?></td>

                    <td>₱<?= number_format($r['gross_sales'], 2) ?></td>

                    <td>₱<?= number_format($r['discount_total'], 2) ?></td>

                    <td>₱<?= number_format($r['net_sales'], 2) ?></td>

                    <td>₱<?= number_format(floatval($r['total_cost']), 2) ?></td>

                    <td class="fw-bold text-success">
                        ₱<?= number_format(floatval($r['net_profit']), 2) ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        </tbody>

        <tfoot class="table-secondary">
            <tr>
                <th colspan="4">TOTAL (ALL PAGES)</th>

                <th><?= number_format((float)$totals['total_qty'], 2) ?></th>

                <th>₱<?= number_format((float)$totals['total_sales'], 2) ?></th>
                <th>₱<?= number_format((float)$totals['total_discount'], 2) ?></th>
                <th>₱<?= number_format((float)$totals['total_sales'] - (float)$totals['total_discount'], 2) ?></th>
                <th>₱<?= number_format((float)$totals['total_cost'], 2) ?></th>
                <th>₱<?= number_format((float)$totals['total_profit'], 2) ?></th>
            </tr>
        </tfoot>

    </table>
    <nav>
        <ul class="pagination">

            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link"
                        href="?page=<?= $page - 1 ?>&limit=<?= $perPage ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                        Previous
                    </a>
                </li>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link"
                        href="?page=<?= $i ?>&limit=<?= $perPage ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link"
                        href="?page=<?= $page + 1 ?>&limit=<?= $perPage ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                        Next
                    </a>
                </li>
            <?php endif; ?>

        </ul>
    </nav>

</div>

<?php include 'footer.php'; ?>