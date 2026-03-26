<?php
ob_start();

include '../db.php';
// Set date range filter (default last 6 months)
$startDate = $_GET['start_date'] ?? date('Y-m-01', strtotime('-1 months'));
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Fetch report data function (to avoid duplication)
function fetchReportData($conn, $startDate, $endDate) {
    $sql = "SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS orders_count,
        SUM(total) AS total_sales,
        AVG(total) AS avg_order_value
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    GROUP BY month
    ORDER BY month DESC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $startDate, $endDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}
/* =========================
   PDF EXPORT
========================= */
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {

    // FIX: clear buffer before PDF
    if (ob_get_length()) ob_end_clean();

    require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

    $data = fetchReportData($conn, $startDate, $endDate);

    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    $html = "<h3>Sales Report</h3>
    <p>From <strong>$startDate</strong> to <strong>$endDate</strong></p>
    <table border='1' cellpadding='5'>
        <tr>
            <th>Date</th>
            <th>Sales</th>
            <th>Staff</th>
            <th>Remarks</th>
        </tr>";

    $stmt = $conn->prepare("
        SELECT * FROM daily_sales
        WHERE sale_date BETWEEN ? AND ?
        ORDER BY sale_date ASC
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {

        $html .= "
        <tr>
            <td>{$row['sale_date']}</td>
            <td>PHP" . number_format($row['total_sales'], 2) . "</td>
            <td>{$row['staff_name']}</td>
            <td>{$row['remarks']}</td>
        </tr>";
    }

    $html .= "</table>";

    $pdf->writeHTML($html);

    // FIX: clear again before output
    if (ob_get_length()) ob_end_clean();

    $pdf->Output('sales_report.pdf', 'D');
    exit;
}

/* =========================
   HANDLE SALES FORM
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $sale_date   = $_POST['sale_date'];
    $total_sales = (float) $_POST['total_sales'];
    $staff_name  = trim($_POST['staff_name']);
    $remarks     = trim($_POST['remarks']);

    $stmt = $conn->prepare("
        INSERT INTO daily_sales (sale_date, total_sales, staff_name, remarks)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_sales = VALUES(total_sales),
            staff_name = VALUES(staff_name),
            remarks = VALUES(remarks)
    ");
    $stmt->bind_param("sdss", $sale_date, $total_sales, $staff_name, $remarks);
    $stmt->execute();

    header("Location: admin_sales.php");
    exit;
}

include 'header.php';
/* =========================
   FILTER MODE
========================= */
$mode = $_GET['mode'] ?? 'monthly';

switch ($mode) {

    case 'daily':
        $startDate = $_GET['date'] ?? date('Y-m-d');
        $endDate   = $startDate;
        break;

    case 'weekly':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate   = date('Y-m-d', strtotime('sunday this week'));
        break;

    case 'yearly':
        $year      = $_GET['year'] ?? date('Y');
        $startDate = "$year-01-01";
        $endDate   = "$year-12-31";
        break;

    default: // monthly
        $year      = $_GET['year'] ?? date('Y');
        $month     = $_GET['month'] ?? date('m');
        $startDate = "$year-$month-01";
        $endDate   = date("Y-m-t", strtotime($startDate));
}

/* =========================
   CSV EXPORT
========================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=sales_report.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Total Sales', 'Staff', 'Remarks']);

    $stmt = $conn->prepare("
        SELECT sale_date, total_sales, staff_name, remarks
        FROM daily_sales
        WHERE sale_date BETWEEN ? AND ?
        ORDER BY sale_date ASC
    ");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        fputcsv($out, $row);
    }

    fclose($out);
    exit;
}



/* =========================
   FETCH SALES
========================= */
$stmt = $conn->prepare("
    SELECT * FROM daily_sales
    WHERE sale_date BETWEEN ? AND ?
    ORDER BY sale_date DESC
");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

/* =========================
   TOTAL SALES
========================= */
$stmt = $conn->prepare("
    SELECT SUM(total_sales) AS total
    FROM daily_sales
    WHERE sale_date BETWEEN ? AND ?
");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$totalSales = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
?>

<h3>Sales Report</h3>

<!-- FILTER BUTTONS -->
<div class="mb-3">
    <a href="?mode=daily" class="btn btn-outline-primary">Daily</a>
    <a href="?mode=weekly" class="btn btn-outline-primary">Weekly</a>
    <a href="?mode=monthly" class="btn btn-outline-primary">Monthly</a>
    <a href="?mode=yearly" class="btn btn-outline-primary">Yearly</a>

    <a href="?export=csv" class="btn btn-success ms-3">Export CSV</a>
    <a href="?export=pdf" class="btn btn-danger">Export PDF</a>
</div>

<!-- SALES FORM -->
<form method="POST" class="row g-3 mb-4">
    <div class="col-md-2">
        <input type="date" name="sale_date" class="form-control" required>
    </div>

    <div class="col-md-2">
        <input type="number" step="0.01" name="total_sales" class="form-control" required>
    </div>

    <div class="col-md-3">
        <input type="text" name="staff_name" class="form-control" placeholder="Staff Name" required>
    </div>

    <div class="col-md-3">
        <input type="text" name="remarks" class="form-control" placeholder="Remarks">
    </div>

    <div class="col-md-12">
        <button class="btn btn-primary">Save Sales</button>
    </div>
</form>

<div class="alert alert-success">
    <strong>Total Sales:</strong> PHP<?= number_format($totalSales, 2) ?>
</div>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Date</th>
            <th>Sales</th>
            <th>Staff</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['sale_date'] ?></td>
                <td>PHP<?= number_format($row['total_sales'], 2) ?></td>
                <td><?= htmlspecialchars($row['staff_name']) ?></td>
                <td><?= htmlspecialchars($row['remarks']) ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php include 'footer.php'; ?>