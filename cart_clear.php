<?php
session_start();
unset($_SESSION['cart']);
$redirect = $_SERVER['HTTP_REFERER'] ?? 'order.php';
header("Location: $redirect");
exit;
// echo "Cart session cleared. <a href='order.php'>Back to Order</a>";
