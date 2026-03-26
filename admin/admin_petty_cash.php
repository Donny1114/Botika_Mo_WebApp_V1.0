<?php
ob_start();

include '../db.php';

// $description = trim($_POST['description'] ?? '');
$date = date('Y-m-d');

// Date range filter
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$startDate = $from;
$endDate   = $to;

$runningBalance = 0;


/* =========================
   EXPORT CSV
========================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    if (ob_get_length()) ob_end_clean();

    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=petty_cash_{$from}_{$to}.csv");

    $out = fopen('php://output', 'w');

    fputcsv($out, ['Date', 'Type', 'Amount', 'Remarks']);

    $stmt = $conn->prepare("
        SELECT date, type, amount, remarks
        FROM petty_cash
        WHERE date BETWEEN ? AND ?
        ORDER BY date ASC
    ");

    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {

        fputcsv($out, [
            $row['date'],
            $row['type'],
            $row['amount'],
            $row['remarks']
        ]);

    }

    fclose($out);
    exit;
}


/* =========================
   EXPORT PDF
========================= */
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {

    if (ob_get_length()) ob_end_clean();

    require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

    $stmt = $conn->prepare("
        SELECT date, type, amount, remarks
        FROM petty_cash
        WHERE date BETWEEN ? AND ?
        ORDER BY date ASC
    ");

    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();

    $totalIn = 0;
    $totalOut = 0;
    $runningBalance = 0;

    $pdf = new TCPDF();
    $pdf->AddPage();

    $html = "<h3>Petty Cash Report</h3>";
    $html .= "<table border='1' cellpadding='4'>
    <tr>
        <th>Date</th>
        <th>Type</th>
        <th>Amount</th>
        <th>Remarks</th>
        <th>Balance</th>
    </tr>";

    while ($row = $res->fetch_assoc()) {

        if ($row['type'] == 'cash_in') {
            $runningBalance += $row['amount'];
            $totalIn += $row['amount'];
        } else {
            $runningBalance -= $row['amount'];
            $totalOut += $row['amount'];
        }

        $html .= "<tr>
            <td>{$row['date']}</td>
            <td>{$row['type']}</td>
            <td>{$row['amount']}</td>
            <td>{$row['remarks']}</td>
            <td>$runningBalance</td>
        </tr>";
    }

    $html .= "</table>";

    $pdf->writeHTML($html);

    if (ob_get_length()) ob_end_clean();

    $pdf->Output("petty_cash.pdf", "D");
    exit;
}


// NOW LOAD HEADER AFTER EXPORT CHECK
include 'header.php';

/* =========================
   HANDLE FORM SUBMIT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $date        = $_POST['date'];
    $type        = $_POST['type']; // IN / OUT
    $amount      = (float) $_POST['amount'];
    $staff_name = trim($_POST['staff_name']); // get staff name
    $remarks     = trim($_POST['remarks']);

    $stmt = $conn->prepare("
    INSERT INTO petty_cash (date, type, amount, remarks, staff_name)
    VALUES (?, ?, ?, ?, ?)
");
    $stmt->bind_param("ssdss", $date, $type, $amount, $remarks, $staff_name);
    $stmt->execute();


    header("Location: admin_petty_cash.php");
    exit;
}

/* =========================
   FETCH TOTALS
========================= */
$stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN type='cash_in'  THEN amount ELSE 0 END) AS total_in,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_out
    FROM petty_cash
    WHERE date BETWEEN ? AND ?
");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc();

$totalIn  = $totals['total_in'] ?? 0;
$totalOut = $totals['total_out'] ?? 0;
$balance  = $totalIn - $totalOut;

$stmt = $conn->prepare("
    SELECT
        DATE_FORMAT(date, '%Y-%m') AS month,
        SUM(CASE WHEN type='cash_in' THEN amount ELSE 0 END) AS total_in,
        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) AS total_out
    FROM petty_cash
    GROUP BY month
    ORDER BY month DESC
");
$stmt->execute();
$monthly = $stmt->get_result();

/* =========================
   FETCH RECORDS
========================= */
$stmt = $conn->prepare("
    SELECT * FROM petty_cash
    WHERE date BETWEEN ? AND ?
    ORDER BY date DESC, id ASC
");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
?>

<h3>Petty Cash</h3>

<form method="POST" class="row g-3 mb-4">
    <div class="col-md-2">
        <input type="date" name="date" class="form-control" required>
    </div>

    <div class="col-md-2">
        <select name="type" class="form-control" required>
            <option value="expense">Expense</option>
            <option value="cash_in">Cash In</option>
        </select>
    </div>

    <div class="col-md-2">
        <input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required>
    </div>

    <!-- <div class="col-md-3">
        <input type="text" name="description" class="form-control" placeholder="Description" require>
    </div> -->

    <div class="col-md-3">
        <input type="text" name="remarks" class="form-control" placeholder="Remarks">
    </div>
    <div class="col-md-3">
        <input type="text" name="staff_name" class="form-control" placeholder="Staff Name" required>
    </div>


    <div class="col-md-12">
        <button class="btn btn-primary">Save</button>
        <a href="?export=csv&from=<?= $from ?>&to=<?= $to ?>" class="btn btn-success ms-2">
            Export CSV
        </a>
        <a href="?export=pdf&from=<?= $from ?>&to=<?= $to ?>" class="btn btn-danger ms-2">
            Export PDF
        </a>

    </div>
</form>
<form method="GET" class="row g-2 mb-3">

    <div class="col-md-3">
        <label>From</label>
        <input type="date" name="from"
            value="<?= $from ?>"
            class="form-control">
    </div>

    <div class="col-md-3">
        <label>To</label>
        <input type="date" name="to"
            value="<?= $to ?>"
            class="form-control">
    </div>

    <div class="col-md-3 d-flex align-items-end">

        <button class="btn btn-primary me-2">
            Filter
        </button>

        <a href="admin_petty_cash.php"
            class="btn btn-secondary">
            Reset
        </a>

    </div>

</form>
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>Total Cash In</h6>
                <h4>₱<?= number_format($totalIn, 2) ?></h4>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6>Total Expenses</h6>
                <h4>₱<?= number_format($totalOut, 2) ?></h4>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-dark text-white">
            <div class="card-body">
                <h6>Balance</h6>
                <h4>₱<?= number_format($balance, 2) ?></h4>
            </div>
        </div>
    </div>
</div>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Remarks</th>
            <th>Balance</th>

        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>

            <?php
            if ($row['type'] === 'cash_in') {
                $runningBalance += $row['amount'];
            } else {
                $runningBalance -= $row['amount'];
            }
            ?>

            <tr>
                <td><?= $row['date'] ?></td>

                <td>
                    <span class="badge bg-<?= $row['type'] === 'cash_in' ? 'success' : 'danger' ?>">
                        <?= $row['type'] === 'cash_in' ? 'Cash In' : 'Expense' ?>
                    </span>
                </td>

                <td>PHP <?= number_format($row['amount'], 2) ?></td>

                <td><?= htmlspecialchars($row['remarks']) ?></td>

                <td>
                    <strong>PHP <?= number_format($runningBalance, 2) ?></strong>
                </td>
            </tr>

        <?php endwhile; ?>
    </tbody>

</table>

<?php include 'footer.php'; ?>