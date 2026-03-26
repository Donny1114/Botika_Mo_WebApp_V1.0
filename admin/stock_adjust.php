<?php
include 'header.php';
include '../db.php';


if(isset($_POST['save']))
{

$product = $_POST['product_id'];
$qty     = $_POST['qty'];

$conn->query("
UPDATE products
SET stock = stock + $qty
WHERE id=$product
");

$conn->query("
INSERT INTO inventory_movements
(product_id,type,qty,user_id)
VALUES
($product,'ADJUST',$qty,".$_SESSION['user_id'].")
");

echo "Adjusted";
}

$products=$conn->query("SELECT id,name FROM products");
?>

<h3>Adjustment</h3>

<form method="post">

<select name="product_id">

<?php while($p=$products->fetch_assoc()): ?>

<option value="<?= $p['id'] ?>">
<?= $p['name'] ?>
</option>

<?php endwhile; ?>

</select>

Qty (+ / -)

<input type="number" name="qty">

<button name="save">
Save
</button>

</form>