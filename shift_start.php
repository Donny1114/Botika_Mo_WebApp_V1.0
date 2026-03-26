<?php
include 'header.php';
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$cashier = $_SESSION['user_id'];

// Check if shift already open
$check = $conn->query("
    SELECT id FROM cashier_shift
    WHERE cashier_id=$cashier
    AND status='open'
");

if ($check->num_rows > 0) {
    header("Location: order.php");
    exit;
}

// Start shift
if (isset($_POST['start_shift'])) {
    $opening = floatval($_POST['opening_cash']);
    if ($opening < 0) {
        echo "<div class='alert alert-danger'>Invalid opening cash.</div>";
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO cashier_shift
        (cashier_id, open_time, opening_cash, status)
        VALUES (?, NOW(), ?, 'open')
    ");
    $stmt->bind_param("id", $cashier, $opening);
    $stmt->execute();

    header("Location: order.php");
    exit;
}
?>

<h3>Start Shift</h3>

<form method="post">
    <label>Opening Cash</label>
    <input type="number" step="0.01" name="opening_cash" required value="0.00">
    <button type="submit" name="start_shift">Start Shift</button>
</form>