<?php
include 'header.php';
include '../db.php';

$errors = [];
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: products.php');
    exit;
}

// Fetch current product
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    header('Location: products.php');
    exit;
}

// Fetch categories
$catRes = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categories = mysqli_fetch_all($catRes, MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']);
    $sku         = trim($_POST['sku']);
    $category_id = $_POST['category_id'] ?? null;
    $description = trim($_POST['description']);
    $price       = $_POST['price'] ?? 0;
    $cost        = $_POST['cost_price'] ?? 0;
    $stock       = $_POST['stock'] ?? 0;
    $expiry_date = $_POST['expiry_date'] ?? null;

    // Basic validation
    if ($name === '') $errors[] = "Product name is required.";
    if ($sku === '')  $errors[] = "SKU is required.";
    if (!is_numeric($price) || $price < 0) $errors[] = "Valid price is required.";
    if (!is_numeric($cost) || $cost < 0) $errors[] = "Valid cost is required.";
    if (!is_numeric($stock) || $stock < 0) $errors[] = "Valid stock is required.";

    // Check for duplicate SKU in other products
    if (count($errors) === 0) {
        $stmtCheck = mysqli_prepare($conn, "SELECT id FROM products WHERE sku = ? AND id != ?");
        mysqli_stmt_bind_param($stmtCheck, "si", $sku, $id);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);
        if (mysqli_stmt_num_rows($stmtCheck) > 0) {
            $errors[] = "SKU '$sku' already exists for another product. Please use a different SKU.";
        }
        mysqli_stmt_close($stmtCheck);
    }

    // Update if no errors
    if (count($errors) === 0) {
        $stmt = mysqli_prepare($conn, "
            UPDATE products SET
                name = ?, sku = ?, category_id = ?, description = ?, price = ?, cost_price = ?, stock = ?, expiry_date = ?
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ssisddisi",
            $name, $sku, $category_id, $description, $price, $cost, $stock, $expiry_date, $id
        );
        if (mysqli_stmt_execute($stmt)) {
            echo "<script>alert('Product updated successfully!'); window.location.href='products.php';</script>";
            exit;
        } else {
            $errors[] = "Database error: " . mysqli_error($conn);
        }
    }
}
?>

<h3>Edit Product</h3>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label>Product Name</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? $product['name']) ?>">
    </div>

    <div class="mb-3">
        <label>SKU</label>
        <input type="text" name="sku" class="form-control" required value="<?= htmlspecialchars($_POST['sku'] ?? $product['sku']) ?>">
    </div>

    <div class="mb-3">
        <label>Category</label>
        <select name="category_id" class="form-control">
            <option value="">Uncategorized</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ((isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) || (!isset($_POST['category_id']) && $product['category_id'] == $cat['id'])) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($_POST['description'] ?? $product['description'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
        <label>Price</label>
        <input type="number" step="0.01" name="price" class="form-control" required value="<?= htmlspecialchars($_POST['price'] ?? $product['price']) ?>">
    </div>

    <div class="mb-3">
        <label>Cost</label>
        <input type="number" step="0.01" name="cost_price" class="form-control" required value="<?= htmlspecialchars($_POST['cost_price'] ?? $product['cost_price']) ?>">
    </div>

    <div class="mb-3">
        <label>Stock</label>
        <input type="number" name="stock" class="form-control" required value="<?= htmlspecialchars($_POST['stock'] ?? $product['stock']) ?>">
    </div>

    <div class="mb-3">
        <label>Expiry Date</label>
        <input type="date" name="expiry_date" class="form-control" value="<?= htmlspecialchars($_POST['expiry_date'] ?? $product['expiry_date']) ?>">
    </div>

    <button class="btn btn-primary">Update Product</button>
</form>
