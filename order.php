<?php
include 'header.php';
include 'db.php';

$cashier = $_SESSION['user_id'] ?? 0;

// =========================
// Check if shift is open
// =========================
$checkShift = $conn->query("
    SELECT id FROM cashier_shift
    WHERE cashier_id=$cashier
    AND status='open'
");
if ($checkShift->num_rows == 0) {
    header("Location: shift_start.php");
    exit;
}

// =========================
// Create Order if none exists
// =========================
if (!isset($_SESSION['order_id'])) {
    $conn->query("
        INSERT INTO orders
        (cashier_id, customer_name, delivery_address, status, order_type, created_at)
        VALUES
        ($cashier,'Walk-in', '', 'open', 'pos', NOW())
    ");
    $_SESSION['order_id'] = $conn->insert_id;
}
$order_id = $_SESSION['order_id'];


// =========================
// Discount session
// =========================
if (!isset($_SESSION['discount_percent'])) {
    $_SESSION['discount_percent'] = 0;
}

if (isset($_POST['discount'])) {
    $_SESSION['discount_percent'] = (float)$_POST['discount'];
}

if (isset($_POST['clear_discount'])) {
    $_SESSION['discount_percent'] = 0;
}

$discountPercent = $_SESSION['discount_percent'];

// =========================
// FUNCTION: restore stock
// =========================
function restoreStock($conn, $order_id)
{
    $items = $conn->query("
        SELECT product_id, quantity
        FROM order_items
        WHERE order_id=$order_id
    ");

    while ($row = $items->fetch_assoc()) {

        $pid = (int)$row['product_id'];
        $qty = (int)$row['quantity'];

        $conn->query("
            UPDATE products
            SET stock = stock + $qty
            WHERE id=$pid
        ");
    }

    $conn->query("
        DELETE FROM order_items
        WHERE order_id=$order_id
    ");
}

// =========================
// Fetch current cart items
// =========================
$cartItems = $conn->query("
    SELECT oi.product_id, p.name, oi.quantity, oi.sell_price, oi.cost_price
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = $order_id
");

$total = 0;
while ($item = $cartItems->fetch_assoc()) {
    $total += $item['sell_price'] * $item['quantity'];
}

$discountAmount = $total * ($discountPercent / 100);
$grandTotal = $total - $discountAmount;

// =========================
// Handle Suspend Order
// =========================
if (isset($_POST['suspend'])) {

    $conn->query("UPDATE orders SET status='suspended' WHERE id=$order_id");

    unset($_SESSION['order_id']);

    header("Location: order.php");
    exit;
}

// =========================
// Handle New Order
// =========================
if (isset($_POST['new_order'])) {

    restoreStock($conn, $order_id);

    unset($_SESSION['order_id']);
    unset($_SESSION['cart']);

    header("Location: order.php");
    exit;
}

// =========================
// Handle Resume Order
// =========================
if (isset($_GET['resume'])) {
    $id = (int)$_GET['resume'];
    $_SESSION['order_id'] = $id;
    $conn->query("UPDATE orders SET status='open' WHERE id=$id");
    header("Location: order.php");
    exit;
}

// =========================
// Fetch Products
// =========================
$search = $_GET['search'] ?? '';
$stmt = $conn->prepare("
    SELECT id, sku, name, price, stock
    FROM products
    WHERE name LIKE ? OR sku LIKE ?
    ORDER BY CAST(sku AS UNSIGNED) ASC
");
$like = "%$search%";
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$products = $stmt->get_result();
?>

<h3 class="mb-3">🧾 POS Order</h3>

<style>
    .pos-container {
        display: grid;
        grid-template-columns: 65% 35%;
        gap: 10px;
    }

    .product-list {
        max-height: 75vh;
        overflow-y: auto;
    }

    .cart-panel {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
    }

    .keypad button {
        width: 30%;
        margin: 3px;
        font-size: 20px;
    }

    /* =========================
       MOBILE FIX
    ========================= */

    @media (max-width: 768px) {

        .pos-container {
            grid-template-columns: 1fr;
        }

        .product-list {
            max-height: 45vh;
        }

        .product-list table {
            font-size: 12px;
        }

        .product-list td,
        .product-list th {
            padding: 4px;
        }

        .product-list button {
            font-size: 12px;
            padding: 4px 6px;
        }

        .cart-panel {
            max-height: 55vh;
            overflow-y: auto;
        }

    }

    /* =========================
       EXTRA SMALL PHONE FIX
    ========================= */

    @media (max-width: 480px) {

        .product-list table {
            font-size: 11px;
        }

        .product-list button {
            font-size: 11px;
            padding: 3px 5px;
        }

        .product-list td:nth-child(2) {
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

    }
</style>

<div class="pos-container">

    <!-- =======================
         LEFT – PRODUCTS
    ====================== -->
    <div class="product-list">
        <form method="GET" class="mb-2 d-flex gap-2">
            <input type="text" name="search" id="barcode" class="form-control form-control-lg"
                placeholder="Scan barcode or search product" autofocus
                value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="order.php" class="btn btn-secondary">Refresh List</a>
        </form>

        <table class="table table-sm table-hover table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($p = $products->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['sku']) ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td>₱<?= number_format($p['price'], 2) ?></td>
                        <td><?= $p['stock'] ?></td>
                        <td>
                            <?php if ($p['stock'] > 0): ?>
                                <form method="POST" action="cart_add.php">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button class="btn btn-sm btn-success">Add</button>
                                </form>
                            <?php else: ?>
                                <span class="text-danger">Out</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- =======================
         RIGHT – CART
    ====================== -->
    <div class="cart-panel">
        <h5>🛒 Current Order</h5>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>₱</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $cartItems = $conn->query("
                SELECT oi.product_id, p.name, oi.quantity, oi.sell_price, oi.cost_price
                FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = $order_id
            ");

                while ($item = $cartItems->fetch_assoc()):
                    $item_total = $item['sell_price'] * $item['quantity'];
                ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td>
                            <form method="POST" action="cart_add.php">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <input type="hidden" name="cost_price" value="<?= $item['cost_price'] ?>">
                                <input type="number" name="quantity" value="<?= $item['quantity'] ?>"
                                    data-product-id="<?= $item['product_id'] ?>"
                                    min="1"
                                    class="form-control form-control-sm d-inline w-26 qty-input"
                                    onclick="setActiveQty(this)">
                            </form>
                        </td>
                        <td><?= number_format($item_total, 2) ?></td>
                        <td><a href="cart_remove.php?id=<?= $item['product_id'] ?>" class="btn btn-sm btn-danger">×</a></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <h5 class="text-end">Subtotal: ₱<?= number_format($total, 2) ?></h5>

        <h6 class="text-end text-danger">
            Discount (<?= $discountPercent ?>%) :
            - ₱<?= number_format($discountAmount, 2) ?>
        </h6>

        <h4 class="text-end text-success">
            TOTAL: ₱<?= number_format($grandTotal, 2) ?>
        </h4>

        <!-- Digital Keypad -->
        <div class="keypad text-center mt-3">
            <?php for ($r = 1; $r <= 9; $r += 3): ?>
                <div>
                    <button class="btn btn-secondary" onclick="pressKey(<?= $r ?>)"><?= $r ?></button>
                    <button class="btn btn-secondary" onclick="pressKey(<?= $r + 1 ?>)"><?= $r + 1 ?></button>
                    <button class="btn btn-secondary" onclick="pressKey(<?= $r + 2 ?>)"><?= $r + 2 ?></button>
                </div>
            <?php endfor; ?>
            <div>
                <button class="btn btn-secondary" onclick="pressKey(0)">0</button>
                <button class="btn btn-warning" onclick="applyQty()">Qty</button>
                <button class="btn btn-danger" onclick="clearQty()">Clear</button>
            </div>
        </div>

        <!-- Discount Buttons -->

        <form method="post" class="mt-2">
            <button name="discount" value="20" class="btn btn-info w-100 mb-1">
                Senior 20%
            </button>

            <button name="discount" value="10" class="btn btn-info w-100 mb-1">
                Discount 10%
            </button>

            <button name="discount" value="3" class="btn btn-info w-100 mb-1">
                Regular 3%
            </button>

            <button name="clear_discount" class="btn btn-secondary w-100">
                Clear Discount
            </button>
        </form>

        <!-- Checkout / Suspend / New / Resume -->
        <a href="checkout.php?type=pos&discount=<?= $discountPercent ?>" class="btn btn-success btn-lg w-100 mt-3">CHECKOUT (F4)</a>

        <form method="post" style="display:inline;">
            <button name="suspend" class="btn btn-warning mt-2 w-100">Suspend (F1)</button>
        </form>

        <form method="post" style="display:inline;">
            <button name="new_order" class="btn btn-primary mt-2 w-100">New Order (F2)</button>
        </form>

        <a href="resume_order.php" class="btn btn-success mt-2 w-100">Resume (F3)</a>
    </div>
</div>

<script>
    let activeInput = null;

    function setActiveQty(input) {
        activeInput = input;
        activeInput.value = '';
    }

    function pressKey(num) {
        if (!activeInput) {
            alert('Click a quantity field first');
            return;
        }
        activeInput.value += num;
    }

    function clearQty() {
        if (!activeInput) return;
        activeInput.value = '';
    }

    function applyQty() {
        if (!activeInput) return;
        const qty = parseInt(activeInput.value);
        const productId = activeInput.dataset.productId;
        if (!qty || qty <= 0) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'cart_add.php';
        form.innerHTML = `
        <input type="hidden" name="product_id" value="${productId}">
        <input type="hidden" name="quantity" value="${qty}">
    `;
        document.body.appendChild(form);
        form.submit();
    }

    // Focus barcode
    document.getElementById('barcode').focus();
</script>

<script>
    document.addEventListener("keydown", function(e) {
        if (e.key === "F1") {
            e.preventDefault();
            document.querySelector("button[name='suspend']").click();
        }
        if (e.key === "F2") {
            e.preventDefault();
            document.querySelector("button[name='new_order']").click();
        }
        if (e.key === "F3") {
            e.preventDefault();
            window.location = "resume_order.php";
        }
        if (e.key === "F4") {
            e.preventDefault();
            window.location = "checkout.php?type=pos";
        }
        if (e.key === "Escape") {
            e.preventDefault();
            if (confirm("Cancel this order?")) window.location = "cancel_order.php";
        }
    });
</script>

<?php include 'footer.php'; ?>