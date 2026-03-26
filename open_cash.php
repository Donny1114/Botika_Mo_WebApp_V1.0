<?php

include 'header.php';
include 'db.php';

if (isset($_POST['open_cash'])) {

    $opening_cash = $_POST['opening_cash'];

    $stmt = $conn->prepare("
        INSERT INTO cash_drawer (date, opening_cash)
        VALUES (CURDATE(), ?)
    ");

    $stmt->bind_param("d", $opening_cash);
    $stmt->execute();

    header("Location: order.php");
}
?>


<form method="POST">

    <label>Opening Cash</label>

    <input type="number"
        step="0.01"
        name="opening_cash"
        class="form-control"
        required>

    <br>

    <button type="submit" name="open_cash" class="btn btn-primary">
        Start Day
    </button>

</form>