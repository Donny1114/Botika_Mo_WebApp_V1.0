<?php
include 'header.php';
include '../db.php';

if(isset($_POST['save']))
{

$product  = $_POST['product_id'];
$supplier = $_POST['supplier_id'];
$batch    = $_POST['batch'];
$expiry   = $_POST['expiry'];
$qty      = $_POST['qty'];
$cost     = $_POST['cost'];

$conn->query("
INSERT INTO product_batches
(product_id,supplier_id,batch_no,expiry_date,qty,cost)
VALUES
($product,$supplier,'$batch','$expiry',$qty,$cost)
");

$conn->query("
UPDATE products
SET stock = stock + $qty
WHERE id=$product
");

$conn->query("
INSERT INTO inventory_movements
(product_id,type,qty,user_id,note)
VALUES
($product,'IN',$qty,".$_SESSION['user_id'].",'Batch $batch')
");

echo "Stock added";

}

$products = $conn->query("SELECT id,name FROM products");
$suppliers = $conn->query("SELECT id,supplier_name FROM suppliers");
?>

<h3>Stock Purchase</h3>

<form method="post">

Product
<select name="product_id">
<?php while($p=$products->fetch_assoc()): ?>
<option value="<?= $p['id'] ?>"><?= $p['name'] ?></option>
<?php endwhile; ?>
</select>

Supplier
<select name="supplier_id">
<?php while($s=$suppliers->fetch_assoc()): ?>
<option value="<?= $s['id'] ?>"><?= $s['supplier_name'] ?></option>
<?php endwhile; ?>
</select>

Batch
<input type="text" name="batch">

Expiry
<input type="date" name="expiry">

Cost Price
<input type="number" step="0.01" name="cost">

Qty
<input type="number" name="qty">

<button name="save">Save</button>

</form>