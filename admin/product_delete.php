<?php
include 'header.php';
include '../db.php';

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: products.php");
    exit;
}

$id = (int) $_GET['id'];

// Optional: Confirm product exists before deleting
$stmt = mysqli_prepare($conn, "SELECT id FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    // Product not found
    header("Location: products.php");
    exit;
}

// Delete product
$deleteStmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
mysqli_stmt_bind_param($deleteStmt, "i", $id);
mysqli_stmt_execute($deleteStmt);

echo "<script>alert('Product deleted successfully!'); window.location.href='products.php';</script>";
exit;
