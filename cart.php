<?php

include 'header.php';
include 'db.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

/* -------------------------
   HANDLE UPDATE / REMOVE
-------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // UPDATE quantities
    if (isset($_POST['update']) && isset($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $product_id => $qty) {

            $product_id = (int)$product_id;
            $qty = max(1, (int)$qty);

            if (isset($_SESSION['cart'][$product_id])) {

                // Check stock from DB
                $stmt = mysqli_prepare($conn, "SELECT stock FROM products WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $product_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $p = mysqli_fetch_assoc($res);

                if ($p) {
                    if ($qty > $p['stock']) {
                        $qty = $p['stock'];
                    }
                    $_SESSION['cart'][$product_id]['quantity'] = $qty;
                }
            }
        }
    }

    // REMOVE item
    if (isset($_POST['remove'])) {
        $removeId = (int)$_POST['remove'];
        unset($_SESSION['cart'][$removeId]);
    }

    header("Location: cart.php");
    exit;
}
// var_dump($_SESSION['cart']);
// exit;

$cart = $_SESSION['cart'];
?>

<h3 class="mb-4">Your Cart</h3>

<?php if (empty($cart)): ?>
    <p>Your cart is empty. <a href="order.php">Start shopping</a>.</p>
<?php else: ?>
    <form method="POST">

        <table class="table table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th width="120">Quantity</th>
                    <th>Subtotal</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>

                <?php
                $total = 0;

                foreach ($cart as $item):
                    $subtotal = $item['price'] * $item['quantity'];
                    $total += $subtotal;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td>₱<?= number_format($item['price'], 2) ?></td>
                        <td>
                            <input
                                type="number"
                                name="quantities[<?= $item['id'] ?>]"
                                value="<?= $item['quantity'] ?>"
                                min="1"
                                class="form-control">
                        </td>
                        <td>₱<?= number_format($subtotal, 2) ?></td>
                        <td>
                            <button
                                type="submit"
                                name="remove"
                                value="<?= $item['id'] ?>"
                                class="btn btn-danger btn-sm"
                                onclick="return confirm('Remove item?')">
                                Remove
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <tr class="table-secondary">
                    <td colspan="3" class="text-end fw-bold">Total</td>
                    <td colspan="2" class="fw-bold">₱<?= number_format($total, 2) ?></td>
                </tr>

            </tbody>
        </table>




        <div class="d-flex justify-content-between">
            <a href="order.php" class="btn btn-secondary">Continue Shopping</a>
            <button type="submit" name="update" class="btn btn-primary">Update Cart</button>
            <!-- <a href="checkout.php?type=online" class="btn btn-success">Online Checkout</a> -->
            <a href="checkout.php?type=pos" class="btn btn-primary">POS Checkout</a>

            <!-- <a href="checkout.php" class="btn btn-success">Checkout</a> -->
        </div>

    </form>
<?php endif; ?>

<?php include 'footer.php'; ?>