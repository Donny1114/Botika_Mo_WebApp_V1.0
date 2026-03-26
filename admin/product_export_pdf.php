<?php

include '../db.php';

require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

ob_start(); // ✅ VERY IMPORTANT

$search = $_GET['search'] ?? '';
$searchTerm = "%$search%";


// ================= QUERY =================

$sql = "SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?
        ORDER BY p.created_at DESC";

$stmt = mysqli_prepare($conn,$sql);
mysqli_stmt_bind_param($stmt,"sss",
    $searchTerm,
    $searchTerm,
    $searchTerm
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


// ================= TCPDF =================

$pdf = new TCPDF();

$pdf->SetCreator('Pharmacy POS');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('Product List');

$pdf->AddPage();

$pdf->SetFont('helvetica','B',14);

$pdf->Cell(0,10,'Pharmacy Product List',0,1,'C');

$pdf->Ln(5);


$pdf->SetFont('helvetica','',9);


// ================= TABLE =================

$html = '

<table border="1" cellpadding="4">

<tr style="background-color:#cccccc;">

<th>SKU</th>
<th>Name</th>
<th>Category</th>
<th>Cost</th>
<th>Price</th>
<th>Stock</th>
<th>Expiry</th>

</tr>

';


while($row = mysqli_fetch_assoc($result)){

$html .= '

<tr>

<td>'.$row['sku'].'</td>

<td>'.$row['name'].'</td>

<td>'.$row['category_name'].'</td>

<td>'.$row['cost_price'].'</td>

<td>'.$row['price'].'</td>

<td>'.$row['stock'].'</td>

<td>'.$row['expiry_date'].'</td>

</tr>

';

}


$html .= '</table>';


$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output('products.pdf', 'D');

exit;
