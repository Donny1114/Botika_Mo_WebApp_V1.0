<?php
include 'header.php';
include '../db.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']);
    $sku         = trim($_POST['sku']);
    $category_id = $_POST['category_id'] ?? null;
    $description = trim($_POST['description']);
    $price       = $_POST['price'] ?? 0;
    $cost        = $_POST['cost_price'] ?? 0;
    $stock       = $_POST['stock'] ?? 0;
    $expiry_date = trim($_POST['expiry_date'] ?? '');

    // Basic validation
    if ($name === '') $errors[] = "Product name is required.";
    if ($sku === '')  $errors[] = "SKU is required.";
    if (!is_numeric($price) || $price < 0) $errors[] = "Valid price is required.";
    if (!is_numeric($cost) || $cost < 0) $errors[] = "Valid cost is required.";
    if (!is_numeric($stock) || $stock < 0) $errors[] = "Valid stock is required.";

    // Expiry date validation
    if ($expiry_date === '') {
        $errors[] = "Expiry date is required.";
    } else {
        $parsedDate = DateTime::createFromFormat('Y-m-d', $expiry_date);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $expiry_date) {
            $errors[] = "Expiry date is invalid.";
        }
    }

    // Check for duplicate SKU
    if (count($errors) === 0) {
        $stmtCheck = mysqli_prepare($conn, "SELECT id FROM products WHERE sku = ?");
        mysqli_stmt_bind_param($stmtCheck, "s", $sku);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);
        if (mysqli_stmt_num_rows($stmtCheck) > 0) {
            $errors[] = "SKU '$sku' already exists. Please use a different SKU.";
        }
        mysqli_stmt_close($stmtCheck);
    }

    // Insert if no errors
    if (count($errors) === 0) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO products
            (name, sku, category_id, description, price, cost_price, stock, expiry_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        mysqli_stmt_bind_param($stmt, "ssisddis",
            $name, $sku, $category_id, $description, $price, $cost, $stock, $expiry_date
        );
        if (mysqli_stmt_execute($stmt)) {
            echo "<script>alert('Product added successfully!'); window.location.href='products.php';</script>";
            exit;
        } else {
            $errors[] = "Database error: " . mysqli_error($conn);
        }
    }
}

// Fetch categories
$catRes = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$categories = mysqli_fetch_all($catRes, MYSQLI_ASSOC);

// Check if expiry date field has an error
$expiryHasError = !empty(array_filter($errors, fn($e) => str_contains($e, 'Expiry') || str_contains($e, 'expiry')));
?>

<h3>Add Product</h3>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label>Product Name</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label>SKU</label>
        <input type="text" name="sku" class="form-control" required value="<?= htmlspecialchars($_POST['sku'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label>Category</label>
        <select name="category_id" class="form-control">
            <option value="">Uncategorized</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
        <label>Price</label>
        <input type="number" step="0.01" name="price" class="form-control" required value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label>Cost</label>
        <input type="number" step="0.01" name="cost_price" class="form-control" required value="<?= htmlspecialchars($_POST['cost_price'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label>Stock</label>
        <input type="number" name="stock" class="form-control" required value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label>Expiry Date <span class="text-danger">*</span></label>
        <input 
            type="date" 
            name="expiry_date" 
            class="form-control <?= $expiryHasError ? 'is-invalid' : '' ?>"
            value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>"
            required
        >
        <?php if ($expiryHasError): ?>
            <div class="invalid-feedback d-block">Expiry date is required.</div>
        <?php endif; ?>
    </div>

    <button class="btn btn-success">Add Product</button>
</form>