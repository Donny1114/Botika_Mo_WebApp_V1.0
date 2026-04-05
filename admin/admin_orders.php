<?php

include '../db.php';

/* =========================
   VOID ORDER
========================= */

if (isset($_GET['void'])) {

    $void_id = (int)$_GET['void'];

    // check status first (prevent double void)
    $check = mysqli_query($conn, "
        SELECT status, discount_percent
        FROM orders
        WHERE id=$void_id
    ");

    $order = mysqli_fetch_assoc($check);

    if (!$order) {
        header("Location: admin_orders.php");
        exit;
    }

    // if already voided stop
    if ($order['status'] == 'Voided') {
        header("Location: admin_orders.php");
        exit;
    }


    /* =========================
       GET TOTAL AMOUNT
    ========================= */

    $totalQ = mysqli_query($conn, "
        SELECT
        SUM(oi.quantity * oi.sell_price) AS subtotal,
        o.discount_percent
        FROM orders o
        LEFT JOIN order_items oi
        ON oi.order_id = o.id
        WHERE o.id=$void_id
        GROUP BY o.id
    ");

    $totalQ = mysqli_query($conn, "
    SELECT
        SUM(oi.quantity * oi.sell_price) AS subtotal,

        SUM(
            (oi.quantity * oi.sell_price) * (oi.discount_percent / 100)
        ) AS discount_amount

    FROM order_items oi
    WHERE oi.order_id=$void_id
");

    $t = mysqli_fetch_assoc($totalQ);

    $subtotal = (float)$t['subtotal'];
    $discount = (float)$t['discount_amount'];

    $total_amount = $subtotal - $discount;

    /* =========================
       RESTORE STOCK
    ========================= */

    $items = mysqli_query($conn, "
        SELECT product_id, quantity
        FROM order_items
        WHERE order_id=$void_id
    ");

    while ($row = mysqli_fetch_assoc($items)) {

        $pid = (int)$row['product_id'];
        $qty = (int)$row['quantity'];

        mysqli_query($conn, "
            UPDATE products
            SET stock = stock + $qty
            WHERE id=$pid
        ");
    }


    /* =========================
       SET VOIDED
    ========================= */

    mysqli_query($conn, "
        UPDATE orders
        SET status='Voided'
        WHERE id=$void_id
    ");


    /* =========================
   GET PRODUCT LIST FOR LOG
========================= */

    $product_list = "";

    $plist = mysqli_query($conn, "
SELECT p.name, oi.quantity
FROM order_items oi
JOIN products p ON p.id = oi.product_id
WHERE oi.order_id = $void_id
");

    while ($pr = mysqli_fetch_assoc($plist)) {

        $product_list .=
            $pr['name']
            . " x"
            . $pr['quantity']
            . ", ";
    }


    /* =========================
   AUDIT LOG WITH AMOUNT + PRODUCTS
========================= */

    $note_text = "Order voided | Products: " . $product_list;

    mysqli_query($conn, "
    INSERT INTO audit_log
    (user, action, order_id, note, total_amount)
    VALUES
    (
        'admin',
        'VOID',
        $void_id,
        '" . mysqli_real_escape_string($conn, $note_text) . "',
        $total_amount
    )
");


    header("Location: admin_orders.php");
    exit;
}
include 'header.php';

/* =========================
   FILTER INPUTS
========================= */
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$view_by    = $_GET['view_by'] ?? 'daily';

/* =========================
   KEEP ORIGINAL DATE (FIX)
========================= */

$start_sql = $start_date;
$end_sql   = $end_date;


/* =========================
   WHERE CONDITIONS
========================= */
$where = "WHERE 1=1";

if ($start_sql) {

    $start_sql .= " 00:00:00";

    $where .= " AND o.created_at >= '" .
        mysqli_real_escape_string($conn, $start_sql) . "'";
}

if ($end_sql) {

    $end_sql .= " 23:59:59";

    $where .= " AND o.created_at <= '" .
        mysqli_real_escape_string($conn, $end_sql) . "'";
}


/* =========================
   FETCH ORDERS (FIX DISCOUNT)
========================= */
$sql = "
SELECT
    o.id,
    o.customer_name,
    o.customer_email,
    o.customer_phone,
    o.delivery_address,
    o.status,
    o.payment_method,
    o.created_at,
    o.discount_percent,

    COALESCE(SUM(oi.quantity * oi.sell_price), 0) AS subtotal,

    COALESCE(
        SUM(
            (oi.quantity * oi.sell_price)
            -
            ((oi.quantity * oi.sell_price) * (oi.discount_percent / 100))
        ), 0
    ) AS total_amount,

    COALESCE(
        SUM(
            (oi.quantity * oi.sell_price) * (oi.discount_percent / 100)
        ), 0
    ) AS discount_amount

FROM orders o
LEFT JOIN order_items oi ON oi.order_id = o.id

$where

GROUP BY o.id

ORDER BY o.created_at DESC
";

$result = mysqli_query($conn, $sql);
?>

<div class="container mt-4">
    <h2>Admin Orders</h2>

    <!-- FILTER FORM -->
    <form method="GET" class="row g-2 mb-4">

        <div class="col-md-3">
            <label>Start Date</label>

            <input
                type="date"
                name="start_date"
                class="form-control"
                value="<?= htmlspecialchars($start_date) ?>">
        </div>

        <div class="col-md-3">
            <label>End Date</label>

            <input
                type="date"
                name="end_date"
                class="form-control"
                value="<?= htmlspecialchars($end_date) ?>">
        </div>

        <div class="col-md-3">
            <label>View By</label>

            <select name="view_by" class="form-select">

                <option value="daily"
                    <?= $view_by === 'daily' ? 'selected' : '' ?>>
                    Daily
                </option>

                <option value="weekly"
                    <?= $view_by === 'weekly' ? 'selected' : '' ?>>
                    Weekly
                </option>

                <option value="monthly"
                    <?= $view_by === 'monthly' ? 'selected' : '' ?>>
                    Monthly
                </option>

                <option value="yearly"
                    <?= $view_by === 'yearly' ? 'selected' : '' ?>>
                    Yearly
                </option>

            </select>
        </div>

        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100">
                Filter
            </button>
        </div>

    </form>


    <?php if (mysqli_num_rows($result) === 0): ?>

        <div class="alert alert-info">
            No orders found.
        </div>

    <?php else: ?>

        <table class="table table-bordered table-striped">

            <thead class="table-dark">
                <tr>

                    <th>ID</th>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Address</th>
                    <th>Total (₱)</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Date</th>
                    <th>Actions</th>

                </tr>
            </thead>

            <tbody>

                <?php while ($o = mysqli_fetch_assoc($result)): ?>


                    <?php
                    /* =========================
   AUTO COMPLETE POS
========================= */
                    if (
                        in_array(
                            $o['payment_method'],
                            ['Cash', 'Card', 'GCash']
                        )
                        &&
                        $o['status'] !== 'Completed'
                        &&
                        $o['status'] !== 'Voided'
                    ) {

                        mysqli_query(
                            $conn,
                            "UPDATE orders
         SET status='Completed'
         WHERE id={$o['id']}"
                        );

                        $o['status'] = 'Completed';
                    }
                    ?>


                    <tr>

                        <td><?= $o['id'] ?></td>

                        <td>
                            <?= htmlspecialchars(
                                $o['customer_name']
                                    ?? 'Walk-in Customer'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($o['customer_email'] ?? '-') ?>
                            <br>
                            <?= htmlspecialchars($o['customer_phone'] ?? '-') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $o['delivery_address'] ?? '-'
                            ) ?>
                        </td>


                        <td>

                            <?php if ($o['discount_amount'] > 0): ?>

                                <small>
                                    Sub:
                                    ₱<?= number_format(
                                            $o['subtotal'],
                                            2
                                        ) ?>
                                    <br>

                                    Disc:
                                    ₱<?= number_format($o['discount_amount'], 2) ?>
                                </small>

                                <br>

                            <?php endif; ?>

                            <strong>
                                ₱<?= number_format(
                                        $o['total_amount'],
                                        2
                                    ) ?>
                            </strong>

                        </td>


                        <td>

                            <?php
                            $badge = match ($o['status']) {
                                'Completed'  => 'success',
                                'Processing' => 'info',
                                'Cancelled'  => 'danger',
                                'Voided'     => 'dark',
                                default      => 'warning'
                            };
                            ?>

                            <span class="badge bg-<?= $badge ?>">
                                <?= htmlspecialchars($o['status']) ?>
                            </span>

                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $o['payment_method']
                            ) ?>
                        </td>


                        <td>
                            <?= date(
                                'Y-m-d H:i',
                                strtotime($o['created_at'])
                            ) ?>
                        </td>


                        <td>

                            <button
                                class="btn btn-sm btn-info"
                                data-bs-toggle="collapse"
                                data-bs-target="#items-<?= $o['id'] ?>">
                                View Items
                            </button>


                            <a
                                href="invoice.php?order_id=<?= $o['id'] ?>"
                                target="_blank"
                                class="btn btn-sm btn-secondary ms-1">
                                Invoice
                            </a>
                            <a
                                href="admin_orders.php?void=<?= $o['id'] ?>"
                                class="btn btn-sm btn-danger ms-1"
                                onclick="return confirm('Void this order? Stock will be restored.')">
                                Void
                            </a>
                        </td>

                    </tr>


                    <tr
                        class="collapse"
                        id="items-<?= $o['id'] ?>">

                        <td colspan="9">

                            <strong>Order Items:</strong>

                            <ul class="mb-0">

                                <?php

                                $items = mysqli_query(
                                    $conn,
                                    "
SELECT
p.name,
oi.quantity,
oi.sell_price,
oi.discount_percent
FROM order_items oi
JOIN products p
ON p.id = oi.product_id
WHERE oi.order_id = {$o['id']}
"
                                );

                                while ($item = mysqli_fetch_assoc($items)):
                                ?>

                                    <li>
                                        <?= htmlspecialchars($item['name']) ?>
                                        —
                                        <?= $item['quantity'] ?> × ₱<?= number_format($item['sell_price'], 2) ?>

                                        <?php if ($item['discount_percent'] > 0): ?>
                                            <br><small class="text-danger">
                                                -<?= $item['discount_percent'] ?>%
                                            </small>
                                        <?php endif; ?>
                                    </li>
                                <?php endwhile; ?>

                            </ul>

                        </td>

                    </tr>


                <?php endwhile; ?>

            </tbody>
        </table>

    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>