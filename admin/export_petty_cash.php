<?php
session_start();
if (!isset($_SESSION['admin'])) exit;

include '../db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="petty_cash_report.csv"');

$output = fopen('php://output', 'w');

fputcsv($output, ['Date', 'Staff', 'Type', 'Amount', 'Remarks']);

$result = $conn->query("SELECT * FROM petty_cash ORDER BY created_at DESC");

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['created_at'],
        $row['staff_name'],
        $row['type'],
        $row['amount'],
        $row['remarks']
    ]);
}

fclose($output);
exit;
