<?php
// admin/invoice.php

ob_start();

session_start();

if (!isset($_SESSION['admin'])) {
    exit('Unauthorized access');
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db.php';


if (!isset($_GET['order_id'])) {
    exit('Order ID missing.');
}

$order_id = (int)$_GET['order_id'];


// --------------------
// Fetch order
// --------------------

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM orders WHERE id = ?"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $order_id
);

mysqli_stmt_execute($stmt);

$orderResult = mysqli_stmt_get_result($stmt);

$order = mysqli_fetch_assoc($orderResult);

if (!$order) {
    exit('Order not found.');
}


// --------------------
// Fetch items
// --------------------

$stmt = mysqli_prepare(
    $conn,
    "
    SELECT oi.quantity, oi.sell_price, oi.discount_percent, p.name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $order_id
);

mysqli_stmt_execute($stmt);

$itemsResult = mysqli_stmt_get_result($stmt);


// --------------------
// CLEAR BUFFER
// --------------------

ob_clean();


// --------------------
// PDF
// --------------------

// --------------------
// PDF (58mm RECEIPT)
// --------------------

$pdf = new TCPDF('P', 'mm', array(58, 200)); // 58mm width
$pdf->SetMargins(3, 5, 3);
$pdf->AddPage();

$pdf->SetFont('courier', '', 8); // small font for receipt

// --------------------
// HEADER
// --------------------

$html = "
<div style='text-align:center;'>
    <b>BOTIKA MO PHARMACY</b><br>
    Union, Libertad<br>
    Antique, Philippines<br>
    -----------------------------<br>
</div>

Invoice #: {$order_id}<br>
Date: {$order['created_at']}<br>
Payment: {$order['payment_method']}<br>
--------------------------------<br>
";

// --------------------
// ITEMS
// --------------------

$subtotal = 0;
$totalDiscount = 0;
$grandTotal = 0;

while ($item = mysqli_fetch_assoc($itemsResult)) {

    $itemSubtotal = $item['quantity'] * $item['sell_price'];

    $itemDiscountPercent = $item['discount_percent'] ?? 0;
    $itemDiscount = $itemSubtotal * ($itemDiscountPercent / 100);

    $lineTotal = $itemSubtotal - $itemDiscount;

    $subtotal += $itemSubtotal;
    $totalDiscount += $itemDiscount;
    $grandTotal += $lineTotal;

    $html .= "
    {$item['name']}<br>
    {$item['quantity']} x " . number_format($item['sell_price'], 2) . "
    = " . number_format($lineTotal, 2) . "<br>
    ";

    if ($itemDiscountPercent > 0) {
        $html .= "<small>  (-{$itemDiscountPercent}%)</small><br>";
    }

    $html .= "--------------------------------<br>";
}

// --------------------
// TOTALS
// --------------------

$html .= "
Subtotal: " . number_format($subtotal, 2) . "<br>
";

if ($totalDiscount > 0) {
    $html .= "Discount: - " . number_format($totalDiscount, 2) . "<br>";
}

$html .= "
<b>TOTAL: " . number_format($grandTotal, 2) . "</b><br>
--------------------------------<br>
";

// --------------------
// FOOTER
// --------------------

$html .= "
<div style='text-align:center;'>
    Thank you for shopping!<br>
</div>
";

// --------------------
// PRINT
// --------------------

$pdf->writeHTML($html, true, false, true, false, '');


// --------------------
// GCash QR (RESIZED)
// --------------------

if ($order['payment_method'] === 'GCash') {

    $pdf->Ln(3);

    $qrPath = __DIR__ . '/assets/images/gcash_qr.png';

    if (file_exists($qrPath)) {

        $pdf->Image(
            $qrPath,
            ($pdf->GetPageWidth() - 30) / 2,
            $pdf->GetY(),
            30
        );

        $pdf->Ln(32);
    }

    $pdf->MultiCell(0, 4, "Scan to Pay", 0, 'C');
}

$pdf->Output("invoice_{$order_id}.pdf", "I");
exit;