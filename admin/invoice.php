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
    SELECT oi.quantity, oi.sell_price, p.name
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

$pdf = new TCPDF();

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Botika Mo');
$pdf->SetTitle('Invoice');

$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 16);

$pdf->Cell(
    0,
    10,
    'Botika Mo Pharmacy',
    0,
    1,
    'C'
);

$pdf->SetFont('helvetica', '', 10);

$pdf->Cell(
    0,
    6,
    'Union, Libertad',
    0,
    1,
    'C'
);

$pdf->Cell(
    0,
    6,
    'Antique, Philippines',
    0,
    1,
    'C'
);

$pdf->Cell(
    0,
    6,
    'Phone: +639671797111 | botikamo24@gmail.com',
    0,
    1,
    'C'
);

$pdf->Line(
    15,
    $pdf->GetY(),
    195,
    $pdf->GetY()
);

$pdf->Ln(6);


// --------------------
// HTML
// --------------------

$html = "

<h3>Invoice #{$order_id}</h3>

<p>
Date: {$order['created_at']}<br>
Status: {$order['status']}<br>
Payment: {$order['payment_method']}
</p>

<hr>

<h4>Customer</h4>

<p>
" . htmlspecialchars($order['customer_name'] ?? '-') . "<br>
" . htmlspecialchars($order['customer_email'] ?? '-') . "<br>
" . htmlspecialchars($order['customer_phone'] ?? '-') . "<br>
" . nl2br(htmlspecialchars($order['delivery_address'] ?? '-')) . "
</p>

<h4>Items</h4>

<table border='1' cellpadding='5'>

<tr>
<th>Product</th>
<th>Qty</th>
<th>Price</th>
<th>Total</th>
</tr>

";

$subtotal = 0;

while ($item = mysqli_fetch_assoc($itemsResult)) {

    $lineTotal =
        $item['quantity']
        *
        $item['sell_price'];

    $subtotal += $lineTotal;

    $html .= "

    <tr>
        <td>{$item['name']}</td>
        <td align='center'>{$item['quantity']}</td>
        <td align='right'>"
        . number_format(
            $item['sell_price'],
            2
        ) .
        "</td>
        <td align='right'>"
        . number_format(
            $lineTotal,
            2
        ) .
        "</td>
    </tr>

    ";
}


/* =========================
   DISCOUNT CALC (NEW)
========================= */

$discountPercent =
    (float)($order['discount_percent'] ?? 0);

$discountAmount =
    $subtotal
    *
    ($discountPercent / 100);

$grandTotal =
    $subtotal
    -
    $discountAmount;


/* =========================
   TOTAL ROWS (UPDATED)
========================= */

$html .= "

<tr>
<td colspan='3' align='right'>
Subtotal
</td>
<td align='right'>
" . number_format($subtotal, 2) . "
</td>
</tr>

";


if ($discountPercent > 0) {

$html .= "

<tr>
<td colspan='3' align='right'>
Discount ({$discountPercent}%)
</td>
<td align='right'>
- "
. number_format($discountAmount, 2)
. "
</td>
</tr>

";

}


$html .= "

<tr>
<td colspan='3' align='right'>
<b>Total</b>
</td>
<td align='right'>
<b>"
. number_format($grandTotal, 2)
. "</b>
</td>
</tr>

</table>

<br><br>

Thank you for shopping at Botika Mo!<br>

";

$pdf->writeHTML($html);


// --------------------
// GCash QR
// --------------------

if ($order['payment_method'] === 'GCash') {

    $pdf->Ln(10);

    $pdf->SetFont(
        'helvetica',
        'B',
        11
    );

    $pdf->Cell(
        0,
        8,
        'Scan to Pay via GCash',
        0,
        1,
        'C'
    );

    $qrPath =
        __DIR__
        . '/assets/images/gcash_qr.png';

    if (file_exists($qrPath)) {

        $pdf->Image(
            $qrPath,
            ($pdf->GetPageWidth() - 50) / 2,
            $pdf->GetY(),
            50
        );

        $pdf->Ln(55);
    }

    $pdf->SetFont(
        'helvetica',
        '',
        10
    );

    $pdf->MultiCell(
        0,
        6,
        "Please send payment and include Order Number.",
        0,
        'C'
    );
}


ob_end_clean();

$pdf->Output(
    "invoice_{$order_id}.pdf",
    "I"
);

exit;