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
// $discountPercent = (float)($_POST['discount_percent'] ?? 0);

// =========================
// FUNCTION: restore stock
// =========================
function restoreStock($conn, $order_id)
{
    $conn->begin_transaction();

    try {

        //  FAST: restore all stock in ONE query
        $conn->query("
            UPDATE products p
            JOIN order_items oi ON oi.product_id = p.id
            SET p.stock = p.stock + oi.quantity
            WHERE oi.order_id = $order_id
        ");

        //  remove items after restoring
        $conn->query("
            DELETE FROM order_items
            WHERE order_id = $order_id
        ");

        $conn->commit();
    } catch (Exception $e) {

        $conn->rollback();

        error_log("Restore stock failed: " . $e->getMessage());
    }
}
// =========================
// Fetch current cart items
// =========================


$total = 0;
$totalDiscountFromItems = 0;

$cartItems = $conn->query("
    SELECT oi.product_id, p.name, oi.quantity, oi.sell_price, oi.cost_price, oi.discount_percent
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = $order_id
");

while ($item = $cartItems->fetch_assoc()) {

    $itemSubtotal = $item['sell_price'] * $item['quantity'];

    $itemDiscountPercent = $item['discount_percent'] ?? 0;

    $itemDiscount = $itemSubtotal * ($itemDiscountPercent / 100);

    $totalDiscountFromItems += $itemDiscount;
    $total += ($itemSubtotal - $itemDiscount);
    // $discountAmount = $total * ($discountPercent / 100);
    // $grandTotal = $total - $discountAmount;
}
// SAFE DEFAULTS (IMPORTANT)
$discountPercent = $_SESSION['discount_percent'] ?? 0;

// ensure numeric safety
// $total = $total ?? 0;

// calculate discount + total
$discountAmount = 0;
$grandTotal = $total;
$discountPercent = 0;

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

    .table-primary {
        background-color: #cfe2ff !important;
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
                    <tr class="product-item">
                        <td><?= htmlspecialchars($p['sku']) ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td>₱<?= number_format($p['price'], 2) ?></td>
                        <td><?= $p['stock'] ?></td>
                        <td>
                            <?php if ($p['stock'] > 0): ?>
                                <form method="POST" action="cart_add.php">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button class="btn btn-sm btn-success add-btn">Add</button>
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
    SELECT oi.product_id, p.name, oi.quantity, oi.sell_price, oi.cost_price, oi.discount_percent
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = $order_id
");

                while ($item = $cartItems->fetch_assoc()):

                    $itemSubtotal = $item['sell_price'] * $item['quantity'];
                    $itemDiscountPercent = $item['discount_percent'] ?? 0;
                    $itemDiscount = $itemSubtotal * ($itemDiscountPercent / 100);
                    $item_total = $itemSubtotal - $itemDiscount;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>

                        <td>
                            <form method="POST" action="cart_add.php">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <input type="hidden" name="cost_price" value="<?= $item['cost_price'] ?>">
                                <input type="number"
                                    name="quantity"
                                    value="<?= $item['quantity'] ?>"
                                    min="1"
                                    class="form-control form-control-sm qty-input"
                                    data-product-id="<?= $item['product_id'] ?>"
                                    onclick="setActiveQty(this)"
                                    onfocus="setActiveQty(this)">
                            </form>
                        </td>

                        <td>
                            <?= number_format($item_total, 2) ?>
                            <?php if ($itemDiscountPercent > 0): ?>
                                <br><small class="text-danger">-<?= $itemDiscountPercent ?>%</small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <form method="POST" action="cart_discount.php" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                <button name="discount" value="20" class="btn btn-sm btn-info">20%</button>
                                <button name="discount" value="10" class="btn btn-sm btn-info">10%</button>
                                <button name="discount" value="3" class="btn btn-sm btn-info">3%</button>
                            </form>

                            <a href="cart_remove.php?id=<?= $item['product_id'] ?>" class="btn btn-sm btn-danger">×</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <h5 class="text-end">Subtotal: ₱<?= number_format($total + $totalDiscountFromItems, 2) ?></h5>

        <h6 class="text-end text-danger">
            Item Discounts Applied: - ₱<?= number_format($totalDiscountFromItems, 2) ?>
        </h6>

        <h4 class="text-end text-success">
            TOTAL: ₱<?= number_format($total, 2) ?>
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

        <!-- <form method="post" class="mt-2">
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
        </form> -->

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
        // highlight active field (optional UX)
        document.querySelectorAll('.qty-input').forEach(i => i.style.border = '');
        input.style.border = '2px solid #0d6efd';
    }


    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', function() {
            activeInput = this;
        });
    });

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
<script>
    let currentIndex = -1;

    function getRows() {
        return document.querySelectorAll(".product-item");
    }

    function highlightRow(index) {
        let rows = getRows();

        rows.forEach(r => r.classList.remove("table-primary"));

        if (rows[index]) {
            rows[index].classList.add("table-primary");
            rows[index].scrollIntoView({
                block: "nearest"
            });
        }
    }

    document.addEventListener("keydown", function(e) {

        let rows = getRows();
        if (rows.length === 0) return;

        // ⬇️ Arrow Down
        if (e.key === "ArrowDown") {
            e.preventDefault();
            currentIndex++;
            if (currentIndex >= rows.length) currentIndex = 0;
            highlightRow(currentIndex);
        }

        // ⬆️ Arrow Up
        if (e.key === "ArrowUp") {
            e.preventDefault();
            currentIndex--;
            if (currentIndex < 0) currentIndex = rows.length - 1;
            highlightRow(currentIndex);
        }

        // ENTER = ADD PRODUCT
        if (e.key === "Enter") {

            let activeElement = document.activeElement;

            // 🚫 Ignore when typing or editing
            // Allow Enter in barcode, block only qty inputs
            // 🚫 Block Enter when typing in search OR qty
            if (
                activeElement.id === "barcode" ||
                activeElement.classList.contains("qty-input")
            ) {
                return;
            }

            e.preventDefault();

            if (rows.length > 0) {

                // if nothing selected yet → auto select first
                if (currentIndex < 0) {
                    currentIndex = 0;
                }

                let row = rows[currentIndex];
                let btn = row.querySelector(".add-btn");

                if (btn) {
                    btn.click();

                    // Reset selection
                    currentIndex = -1;

                    // Focus back to barcode for next scan
                    setTimeout(() => {
                        document.getElementById("barcode").focus();
                    }, 100);
                }
            }
        }
    });

    window.addEventListener("load", function() {

        let rows = getRows();

        if (rows.length > 0) {
            currentIndex = 0;
            highlightRow(currentIndex);
        }

        // ✅ If came from search → focus product list
        if (sessionStorage.getItem("fromSearch") === "true") {

            sessionStorage.removeItem("fromSearch");

            // remove focus from input
            document.getElementById("barcode").blur();
        } else {
            // default behavior
            document.getElementById("barcode").focus();
        }
    });

    document.getElementById("barcode").addEventListener("keydown", function(e) {

        if (e.key === "Enter") {
            // Let form submit normally (search)

            // 🔥 Save state so we know it's from search
            sessionStorage.setItem("fromSearch", "true");
        }

    });
</script>

<?php include 'footer.php'; ?>