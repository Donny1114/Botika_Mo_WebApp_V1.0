<?php
include 'header.php';
include 'db.php';

$cashier = $_SESSION['user_id'] ?? 0;

if (!isset($_SESSION['order_id'])) {
    echo "<div class='alert alert-warning'>No active order to checkout.</div>";
    exit;
}

$order_id = $_SESSION['order_id'];
$error = "";
// =============================
// Discount from session
// =============================
$discountPercent = $_SESSION['discount_percent'] ?? 0;

// Fetch order items and total
$orderItems = $conn->query("
    SELECT oi.product_id, p.name, oi.quantity, oi.sell_price
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = $order_id
");

$total = 0;
while ($item = $orderItems->fetch_assoc()) {
    $total += $item['sell_price'] * $item['quantity'];
}

$discountAmount = $total * ($discountPercent / 100);
$grandTotal = $total - $discountAmount;


// =============================
// CHECKOUT
// =============================

if (isset($_POST['checkout'])) {

    $payment_method = $_POST['payment_method'] ?? 'Cash';
    $amount_paid = floatval($_POST['amount_paid']);
    if ($amount_paid < $grandTotal) {
        $error = "Money not enough!";
    } else {

        // NEW
        $print_receipt = $_POST['print_receipt'] ?? 'no';


        // Update order as paid
        $stmt = $conn->prepare("
    UPDATE orders
    SET status='paid',
        payment_method=?,
        created_at=NOW(),
        total=?,
        discount_percent=?,
        discount_amount=?,
        grand_total=?
    WHERE id=?
");

        $stmt->bind_param(
            "sddddi",
            $payment_method,
            $total,
            $discountPercent,
            $discountAmount,
            $grandTotal,
            $order_id
        );
        $stmt->execute();


        unset($_SESSION['order_id']);
        unset($_SESSION['discount_percent']);


        // =============================
        // PRINT OPTION
        // =============================

        if ($print_receipt == "yes") {

            header("Location: print_receipt.php?order_id=" . $order_id);
            exit;
        } else {

            echo "<div class='alert alert-success'>Order checked out successfully!</div>";
            echo "<a href='order.php' id='newOrderBtn' class='btn btn-primary'>New Order</a>";
            exit;
        }
    }
}
?>

<h3>Checkout</h3>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <?= $error ?>
    </div>
<?php endif; ?>

<table class="table table-sm table-bordered">
    <thead>
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Subtotal</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $orderItems->data_seek(0);
        while ($item = $orderItems->fetch_assoc()):
            $subtotal = $item['sell_price'] * $item['quantity'];
        ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>₱<?= number_format($item['sell_price'], 2) ?></td>
                <td>₱<?= number_format($subtotal, 2) ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<h5>Subtotal: ₱<?= number_format($total, 2) ?></h5>

<h6 class="text-danger">
    Discount (<?= $discountPercent ?>%) :
    - ₱<?= number_format($discountAmount, 2) ?>
</h6>

<h4 class="text-success">
    Total: ₱<?= number_format($grandTotal, 2) ?>
</h4>

<form method="POST" id="checkoutForm">

    <div class="mb-3">
        <label>Payment Method</label>
        <select name="payment_method" class="form-control" required>
            <option value="Cash">Cash</option>
            <option value="GCash">GCash</option>
            <option value="Card">Card</option>
        </select>
    </div>

    <div class="mb-3">
        <label>Amount Paid</label>
        <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control" value="<?= $grandTotal ?>" required>
    </div>


    <!-- Quick Cash Buttons -->
    <div class="mb-3">
        <button type="button" class="btn btn-outline-secondary" onclick="setCash(100)">₱100</button>
        <button type="button" class="btn btn-outline-secondary" onclick="setCash(200)">₱200</button>
        <button type="button" class="btn btn-outline-secondary" onclick="setCash(500)">₱500</button>
        <button type="button" class="btn btn-outline-secondary" onclick="setCash(1000)">₱1000</button>
        <button type="button" class="btn btn-outline-success" onclick="exactCash()">Exact</button>
        <button type="button" class="btn btn-outline-danger" onclick="clearCash()">Clear</button>
    </div>


    <div class="mb-3">
        <label>Change:</label>
        <input type="text" id="change" class="form-control" readonly value="₱0.00">
    </div>


    <!-- ===========================
         PRINT OPTION (NEW)
    ============================ -->

    <div class="mb-3">

        <label>Print Receipt?</label><br>

        <input type="radio" name="print_receipt" value="no" checked> No

        <input type="radio" name="print_receipt" value="yes"> Yes

    </div>


    <button type="submit" name="checkout" id="checkoutBtn" class="btn btn-success w-100">
        Pay & Checkout (Enter)
    </button>

</form>


<script>
    const totalAmount = <?= $grandTotal ?>;
    const amountInput = document.getElementById('amount_paid');
    const changeInput = document.getElementById('change');

    function setCash(amount) {
        amountInput.value = amount;
        calculateChange();
    }

    function exactCash() {
        amountInput.value = totalAmount;
        calculateChange();
    }

    function clearCash() {
        amountInput.value = '';
        calculateChange();
    }

    amountInput.addEventListener('input', calculateChange);

    function calculateChange() {

        let paid = parseFloat(amountInput.value) || 0;

        let change = paid - totalAmount;

        if (change < 0) {
            changeInput.value = "Not enough";
            return;
        }
        changeInput.value = "₱" + change.toFixed(2);

    }
    // =============================
    // ENTER KEY SMART ACTION
    // =============================
    document.addEventListener('keydown', function(e) {

        if (e.key === 'Enter') {

            e.preventDefault();

            // If checkout button exists → do checkout
            let checkoutBtn = document.getElementById('checkoutBtn');
            if (checkoutBtn) {
                checkoutBtn.click();
                return;
            }

            // If new order button exists → go to new order
            let newOrderBtn = document.getElementById('newOrderBtn');
            if (newOrderBtn) {
                window.location.href = newOrderBtn.href;
                return;
            }
        }

    });
</script>