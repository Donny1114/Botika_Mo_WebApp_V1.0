<?php
include 'header.php';
include '../db.php';


/* ======================
ADD SUPPLIER
====================== */

if(isset($_POST['add_supplier']))
{

$name    = $_POST['name'];
$contact = $_POST['contact'];
$phone   = $_POST['phone'];
$email   = $_POST['email'];
$address = $_POST['address'];

$stmt = $conn->prepare("
INSERT INTO suppliers
(supplier_name,contact_person,phone,email,address)
VALUES (?,?,?,?,?)
");

$stmt->bind_param(
"sssss",
$name,
$contact,
$phone,
$email,
$address
);

$stmt->execute();

echo "<div class='alert alert-success'>
Supplier added
</div>";

}


/* ======================
DELETE SUPPLIER
====================== */

if(isset($_GET['delete']))
{

$id = (int)$_GET['delete'];

$conn->query("
DELETE FROM suppliers
WHERE id=$id
");

echo "<div class='alert alert-danger'>
Supplier deleted
</div>";

}


/* ======================
GET SUPPLIERS
====================== */

$suppliers = $conn->query("
SELECT *
FROM suppliers
ORDER BY supplier_name
");

?>


<div class="card p-3 mb-3">

<h3>Add Supplier</h3>

<form method="post">

Name

<input type="text"
name="name"
class="form-control"
required>


Contact Person

<input type="text"
name="contact"
class="form-control">


Phone

<input type="text"
name="phone"
class="form-control">


Email

<input type="text"
name="email"
class="form-control">


Address

<textarea
name="address"
class="form-control">
</textarea>

<br>

<button
name="add_supplier"
class="btn btn-primary">

Add Supplier

</button>

</form>

</div>



<div class="card p-3">

<h3>Supplier List</h3>

<table class="table table-bordered">

<tr>

<th>ID</th>
<th>Name</th>
<th>Contact</th>
<th>Phone</th>
<th>Email</th>
<th>Action</th>

</tr>


<?php while($s = $suppliers->fetch_assoc()): ?>

<tr>

<td><?= $s['id'] ?></td>

<td><?= $s['supplier_name'] ?></td>

<td><?= $s['contact_person'] ?></td>

<td><?= $s['phone'] ?></td>

<td><?= $s['email'] ?></td>

<td>

<a
href="?delete=<?= $s['id'] ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete supplier?')"
>

Delete

</a>

</td>

</tr>

<?php endwhile; ?>

</table>

</div>


<?php include 'footer.php'; ?>