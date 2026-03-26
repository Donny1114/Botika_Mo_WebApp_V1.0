<?php

include '../db.php';


$search = $_GET['search'] ?? '';
$searchTerm = "%$search%";


// ===== QUERY =====

$sql = "SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?
        ORDER BY p.created_at DESC";

$stmt = mysqli_prepare($conn,$sql);

mysqli_stmt_bind_param(
    $stmt,
    "sss",
    $searchTerm,
    $searchTerm,
    $searchTerm
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


// ===== CSV HEADER =====

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=products.csv');

$output = fopen("php://output", "w");


// ===== COLUMN HEADER =====

fputcsv($output,[
    'SKU',
    'Name',
    'Category',
    'Cost Price',
    'Price',
    'Stock',
    'Expiry'
]);


// ===== DATA =====

while($row = mysqli_fetch_assoc($result)){

    fputcsv($output,[
        $row['sku'],
        $row['name'],
        $row['category_name'],
        $row['cost_price'],
        $row['price'],
        $row['stock'],
        $row['expiry_date']
    ]);

}

fclose($output);

exit;
