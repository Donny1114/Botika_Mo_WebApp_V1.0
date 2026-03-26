<?php
include 'header.php';
include '../db.php';

if (isset($_POST['save'])) {

    $product = $_POST['product_id'];
    $qty     = $_POST['qty'];
    $reason  = $_POST['reason'];

    $conn->query("
UPDATE products
SET stock = stock - $qty
WHERE id=$product
");

    $conn->query("
INSERT INTO inventory_movements
(product_id,type,qty,user_id,note)
VALUES
($product,'OUT',$qty," . $_SESSION['user_id'] . ",'$reason')
");

    echo "Stock removed";
}

$products = $conn->query("SELECT id,name FROM products");
?>

<h3>Stock OUT</h3>

<form method="post">

    Product
    <select name="product_id">

        <?php while ($p = $products->fetch_assoc()): ?>

            <option value="<?= $p['id'] ?>">
                <?= $p['name'] ?>
            </option>

        <?php endwhile; ?>

    </select>

    Qty
    <input type="number" name="qty">

    Reason
    <select name="reason">

        <option>Expired</option>
        <option>Damaged</option>
        <option>Return</option>
        <option>Transfer Out</option>


    </select>

    <button name="save">
        Save
    </button>

</form>