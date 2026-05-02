<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin'])) {

    header("Location: /botika_mo_V1.0/admin/login.php");
    exit;
}

define('BASE_URL', 'http://localhost/botika_mo_V1.0/');
$currentPage = $_SERVER['REQUEST_URI'];

$isInventory =
    strpos($currentPage, 'inventory') !== false ||
    strpos($currentPage, 'stock_in') !== false ||
    strpos($currentPage, 'stock_out') !== false ||
    strpos($currentPage, 'stock_adjust') !== false ||
    strpos($currentPage, 'supplier') !== false;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Botika Mo</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">


    <style>
        body {
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background: #0d6efd;
            color: #fff;
        }

        .sidebar a {
            color: #fff;
            text-decoration: none;
            padding: 12px 20px;
            display: block;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .content {
            flex-grow: 1;
            padding: 30px;
            background: #f8f9fa;
        }
    </style>
</head>




<body>

    <div class="sidebar">
        <h4 class="text-center py-3 border-bottom">Botika Mo</h4>
        <a href="<?php echo BASE_URL; ?>admin/admin_reports.php">📊 Dashboard</a>
        <a href="<?php echo BASE_URL; ?>admin/products.php">📦 Products</a>
        <a href="<?php echo BASE_URL; ?>admin/admin_orders.php">🛒 Orders</a>
        <a href="<?php echo BASE_URL; ?>admin/dashboard.php">📨 Enquiries</a>
        <a href="<?php echo BASE_URL; ?>admin/admin_petty_cash.php">💰 Petty Cash</a>
        <a href="<?php echo BASE_URL; ?>admin/admin_sales.php">💰 Sales (Manual)</a>
        <a href="<?php echo BASE_URL; ?>admin/sales_report.php">📊 Sales Report</a>
        <a href="<?php echo BASE_URL; ?>admin/product_profit_report.php">📊 Product Profit Report</a>
        <a href="<?php echo BASE_URL; ?>admin/category_profit_report.php">📊 Category Profit Report</a>
        <a href="<?php echo BASE_URL; ?>admin/x_reading.php">📊 X Reading</a>
        <a href="<?php echo BASE_URL; ?>admin/z_reading.php">📊 Z Reading</a>
        <a href="<?php echo BASE_URL; ?>admin/void_refund_report.php">🧾 Void/Refund Report</a>
        <a href="<?php echo BASE_URL; ?>admin/backup.php">💾 Backup</a>
        <li class="nav-item">

            <a class="nav-link"
                data-bs-toggle="collapse"
                href="#inventoryMenu"
                aria-expanded="<?= $isInventory ? 'true' : 'false' ?>">

                📦 Inventory

            </a>

            <div class="collapse <?= $isInventory ? 'show' : '' ?>" id="inventoryMenu">

                <ul class="nav flex-column ms-3">

                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo BASE_URL; ?>admin/inventory_movement_stock.php">
                            Inventory Stock
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo BASE_URL; ?>admin/inventory/inventory_movement.php">
                            Inventory Movement Sold
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo BASE_URL; ?>admin/stock_in.php">
                            Stock In
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo BASE_URL; ?>admin/stock_adjust.php">
                            Stock Adjust
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo BASE_URL; ?>admin/stock_out.php">
                            Stock Out
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="<?php echo BASE_URL; ?>admin/supplier.php">
                            Supplier
                        </a>
                    </li>
                </ul>

            </div>

        </li>
        <!-- <a href="<?php echo BASE_URL; ?>admin/inventory/inventory_movement.php">📦 Inventory Movement</a> -->
        <a href="<?php echo BASE_URL; ?>index.php">📨 Home</a>
        <a href="<?php echo BASE_URL; ?>admin/logout.php" class="text-warning">🚪 Logout</a>
    </div>

    <div class="content">