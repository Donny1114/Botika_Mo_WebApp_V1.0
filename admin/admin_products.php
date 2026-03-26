$limit = 50;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';

$sql = "
SELECT id, sku, name, cost, price, stock, expiry_date
FROM products
WHERE name LIKE ? OR sku LIKE ?
ORDER BY name
LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);
$like = "%$search%";
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$result = $stmt->get_result();

<form method="POST" action="update_products_bulk.php">
<table class="table table-sm table-bordered">
<thead>
<tr>
    <th>SKU</th>
    <th>Product</th>
    <th>Cost</th>
    <th>Price</th>
    <th>Stock</th>
    <th>Expiry</th>
    <th>Save</th>
</tr>
</thead>

<tbody>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <td>
        <input type="text" name="sku[<?= $row['id'] ?>]"
               value="<?= htmlspecialchars($row['sku']) ?>" class="form-control">
    </td>

    <td><?= htmlspecialchars($row['name']) ?></td>

    <td>
        <input type="number" step="0.01" name="cost[<?= $row['id'] ?>]"
               value="<?= $row['cost'] ?>" class="form-control">
    </td>

    <td>
        <input type="number" step="0.01" name="price[<?= $row['id'] ?>]"
               value="<?= $row['price'] ?>" class="form-control">
    </td>

    <td>
        <input type="number" name="stock[<?= $row['id'] ?>]"
               value="<?= $row['stock'] ?>" class="form-control">
    </td>

    <td>
        <input type="date" name="expiry[<?= $row['id'] ?>]"
               value="<?= $row['expiry_date'] ?>" class="form-control">
    </td>

    <td>
        <button class="btn btn-sm btn-primary">Save</button>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</form>
