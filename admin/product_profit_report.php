<?php

include '../db.php';

/* =========================
   FILTER INPUT
========================= */

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-t');

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
                <td>P" . number_format($r['cost_price'], 2) . "</td>
                <td>P" . number_format($r['sell_price'], 2) . "</td>
                <td>{$r['qty_sold']}</td>
                <td>P" . number_format($r['gross_sales'], 2) . "</td>
                <td>P" . number_format($r['discount_total'], 2) . "</td>
                <td>P" . number_format($r['net_sales'], 2) . "</td>
                <td>P" . number_format($r['total_cost'], 2) . "</td>
                <td>P" . number_format($r['net_profit'], 2) . "</td>
            </tr>";
        }

        $html .= "
            <tr style='font-weight:bold; background-color:#d9edf7;'>
                <td width='18%' colspan='2' align='right'>TOTAL</td>
                <td width='8%'></td>
                <td width='8%'></td>
                <td width='8%'></td>
                <td width='8%' align='center'>{$totalSold}</td>
                <td width='12%' align='right'>P" . number_format($totalSales, 2) . "</td>
                <td width='12%' align='right'>P" . number_format($totalDiscount, 2) . "</td>
                <td width='12%' align='right'>P" . number_format($totalSales - $totalDiscount, 2) . "</td>
                <td width='12%' align='right'>P" . number_format($totalCost, 2) . "</td>
                <td width='10%' align='right'>P" . number_format($totalProfit, 2) . "</td>
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

                <th colspan="4">TOTAL</th>

                <th><?= number_format($totalSold, 2) ?></th>

                <th>₱<?= number_format($totalSales, 2) ?></th>
                <th>₱<?= number_format($totalDiscount, 2) ?></th>
                <th>₱<?= number_format($totalSales - $totalDiscount, 2) ?></th>
                <th>₱<?= number_format($totalCost, 2) ?></th>
                <th>₱<?= number_format($totalProfit, 2) ?></th>

            </tr>

        </tfoot>

    </table>

</div>

<?php include 'footer.php'; ?>