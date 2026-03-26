<?php
include 'header.php';
include '../db.php';

$lowStockThreshold = 10;  // Customize this number as needed

// Pagination
$limit = 100;
$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$offset = ($page - 1) * $limit;

// Search
$search = $_GET['search'] ?? '';
$searchTerm = "%$search%";
$view = $_GET['view'] ?? 'default';

// Count total filtered products
$countSql = "SELECT COUNT(*) AS total 
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?";
$countStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($countStmt, "sss", $searchTerm, $searchTerm, $searchTerm);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRows / $limit);

// Fetch filtered products with join, limit and offset
$sql = "SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?
        ORDER BY
        CASE
            WHEN '$view'='expired' AND p.expiry_date < CURDATE() THEN 0
            WHEN '$view'='near' AND p.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 0
            ELSE 1
        END,
        CAST(p.sku AS UNSIGNED) ASC
        LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssii", $searchTerm, $searchTerm, $searchTerm, $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$today = date('Y-m-d');
?>

<h3 class="mb-4">Products</h3>

<a href="product_add.php" class="btn btn-success mb-3">➕ Add Product</a>

<!-- Export buttons -->


<a href="product_export_csv.php?search=<?= urlencode($search) ?>"
    class="btn btn-info mb-2">

    ⬇ Export CSV

</a>


<a href="product_export_pdf.php?search=<?= urlencode($search) ?>"
    class="btn btn-danger mb-2">

    ⬇ Export PDF

</a>


<div class="card shadow-sm">
    <div class="card-body">
        <form method="GET" class="mb-3 d-flex gap-2">

            <input type="text"
                name="search"
                class="form-control"
                placeholder="Search products..."
                value="<?= htmlspecialchars($search) ?>">

            <select name="view" class="form-control" style="width:180px">

                <option value="default" <?= $view == 'default' ? 'selected' : '' ?>>
                    Default view
                </option>

                <option value="expired" <?= $view == 'expired' ? 'selected' : '' ?>>
                    Expired first
                </option>

                <option value="near" <?= $view == 'near' ? 'selected' : '' ?>>
                    Near expiry first
                </option>

            </select>

            <button class="btn btn-primary">Apply</button>

        </form>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>SKU</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Cost</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Expiry Date</th>
                    <th>Actions</th>

                </tr>
            </thead>
            <tbody>
                <?php if ($totalRows == 0): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">No products found.</td>
                    </tr>
                <?php endif; ?>

                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php
                    $expiryDate = $row['expiry_date'];
                    $isExpired = $expiryDate && ($expiryDate < $today);
                    $isNearExpiry = $expiryDate && ($expiryDate <= date('Y-m-d', strtotime('+30 days')) && $expiryDate >= $today);

                    if ($isExpired) {
                        $rowClass = 'table-danger';
                    } elseif ($isNearExpiry) {
                        $rowClass = 'table-warning';
                    } elseif ($row['stock'] <= $lowStockThreshold) {
                        $rowClass = 'table-info';
                    } else {
                        $rowClass = '';
                    }
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><?= htmlspecialchars($row['sku']) ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                        <td>₱<?= number_format($row['cost_price'], 2) ?></td>
                        <td>₱<?= number_format($row['price'], 2) ?></td>
                        <td>
                            <?= $row['stock'] ?>
                            <?php if ($row['stock'] <= $lowStockThreshold): ?>
                                <span class="badge bg-danger ms-2">Low Stock!</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $expiryDate ? date('Y-m-d', strtotime($expiryDate)) : '-' ?>
                            <?php if ($isExpired): ?>
                                <span class="badge bg-danger ms-2">Expired</span>
                            <?php elseif ($isNearExpiry): ?>
                                <span class="badge bg-warning text-dark ms-2">Expiring Soon</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="product_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                            <a href="product_delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this product?')">Delete</a>
                        </td>
                        <!-- <td>
                            <form method="POST" action="cart_add.php" class="d-inline">
                                <input type="hidden" name="product_id" value="<?= $row['id'] ?>">
                                <input type="number" name="quantity" value="1" min="1" max="<?= $row['stock'] ?>" class="form-control form-control-sm d-inline-block" style="width: 60px;">
                                <button type="submit" class="btn btn-sm btn-success ms-2">Add to Cart</button>
                            </form>
                        </td> -->
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center mt-4">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&view=<?= $view ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>