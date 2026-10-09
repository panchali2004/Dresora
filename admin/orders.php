
<?php
session_start();

require_once "../config/database.php";

/* =========================
   ADMIN LOGIN CHECK
========================= */
if (
    !isset($_SESSION["user_id"]) ||
    strtolower($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../account.php");
    exit;
}

/* =========================
   HELPER FUNCTION
========================= */
function e($value)
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* =========================
   ALLOWED FILTERS
========================= */
$allowedStatuses = [
    "pending",
    "confirmed",
    "rejected",
    "completed",
    "cancelled"
];

$filterStatus = strtolower(trim($_GET["status"] ?? ""));
$filterPayment = strtolower(trim($_GET["payment"] ?? ""));

if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = "";
}

if ($filterPayment !== "paid") {
    $filterPayment = "";
}

/* =========================
   FLASH MESSAGE
========================= */
$message = $_SESSION["orders_message"] ?? "";
$messageType = $_SESSION["orders_message_type"] ?? "success";

unset(
    $_SESSION["orders_message"],
    $_SESSION["orders_message_type"]
);

/* =========================
   PROCESS ORDER ACTIONS
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $orderId = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
    $action = strtolower(trim($_POST["action"] ?? ""));

    $returnStatus = strtolower(trim($_POST["return_status"] ?? ""));
    $returnPayment = strtolower(trim($_POST["return_payment"] ?? ""));

    if (!in_array($returnStatus, $allowedStatuses, true)) {
        $returnStatus = "";
    }

    if ($returnPayment !== "paid") {
        $returnPayment = "";
    }

    $redirectUrl = "orders.php";

    if ($returnPayment === "paid") {
        $redirectUrl .= "?payment=paid";
    } elseif ($returnStatus !== "") {
        $redirectUrl .= "?status=" . urlencode($returnStatus);
    }

    if (!$orderId || !in_array($action, ["confirm", "cancel"], true)) {

        $_SESSION["orders_message"] = "Invalid order action.";
        $_SESSION["orders_message_type"] = "error";

        header("Location: " . $redirectUrl);
        exit;
    }

    $conn->begin_transaction();

    try {

        /* Lock the order to prevent duplicate processing */
        $stmt = $conn->prepare("
            SELECT order_id, user_id, status
            FROM orders
            WHERE order_id = ?
            FOR UPDATE
        ");

        $stmt->bind_param("i", $orderId);
        $stmt->execute();

        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            throw new Exception("Order not found.");
        }

        if (strtolower($order["status"]) !== "pending") {
            throw new Exception(
                "Only pending orders can be confirmed or cancelled."
            );
        }

        $customerId = (int)$order["user_id"];

        /* =========================
           CONFIRM ORDER
        ========================= */
        if ($action === "confirm") {

            $stmt = $conn->prepare("
                UPDATE orders
                SET status = 'confirmed'
                WHERE order_id = ?
                  AND LOWER(status) = 'pending'
            ");

            $stmt->bind_param("i", $orderId);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new Exception("Could not confirm this order.");
            }

            $stmt->close();

            $title = "Order Confirmed";
            $notificationMessage =
                "Your order #" . $orderId . " has been confirmed.";

            $notificationType = "order";

            $stmt = $conn->prepare("
                INSERT INTO notifications
                    (user_id, title, message, notification_type,
                     is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");

            $stmt->bind_param(
                "isss",
                $customerId,
                $title,
                $notificationMessage,
                $notificationType
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $_SESSION["orders_message"] =
                "Order #" . $orderId . " confirmed successfully.";

            $_SESSION["orders_message_type"] = "success";
        }

        /* =========================
           CANCEL ORDER + RESTORE STOCK
        ========================= */
        elseif ($action === "cancel") {

            $stmt = $conn->prepare("
                SELECT dress_id, size, quantity
                FROM order_items
                WHERE order_id = ?
            ");

            $stmt->bind_param("i", $orderId);
            $stmt->execute();

            $itemsResult = $stmt->get_result();
            $items = [];

            while ($item = $itemsResult->fetch_assoc()) {
                $items[] = $item;
            }

            $stmt->close();

            if (empty($items)) {
                throw new Exception(
                    "No order items found. Order was not cancelled."
                );
            }

            /* Restore the stock for each ordered dress and size */
            $stockStmt = $conn->prepare("
                UPDATE dress_size_stock
                SET quantity = quantity + ?
                WHERE dress_id = ?
                  AND size = ?
            ");

            foreach ($items as $item) {

                $quantity = (int)$item["quantity"];
                $dressId = (int)$item["dress_id"];
                $size = (string)$item["size"];

                if ($quantity <= 0) {
                    throw new Exception("Invalid order item quantity.");
                }

                $stockStmt->bind_param(
                    "iis",
                    $quantity,
                    $dressId,
                    $size
                );

                $stockStmt->execute();

                if ($stockStmt->affected_rows !== 1) {
                    throw new Exception(
                        "Stock record not found for dress ID "
                        . $dressId . ", size " . $size
                        . ". Order was not cancelled."
                    );
                }
            }

            $stockStmt->close();

            $stmt = $conn->prepare("
                UPDATE orders
                SET status = 'cancelled'
                WHERE order_id = ?
                  AND LOWER(status) = 'pending'
            ");

            $stmt->bind_param("i", $orderId);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new Exception("Could not cancel this order.");
            }

            $stmt->close();

            $title = "Order Cancelled";
            $notificationMessage =
                "Your order #" . $orderId
                . " has been cancelled by the administrator.";

            $notificationType = "order";

            $stmt = $conn->prepare("
                INSERT INTO notifications
                    (user_id, title, message, notification_type,
                     is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");

            $stmt->bind_param(
                "isss",
                $customerId,
                $title,
                $notificationMessage,
                $notificationType
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $_SESSION["orders_message"] =
                "Order #" . $orderId
                . " cancelled and stock restored successfully.";

            $_SESSION["orders_message_type"] = "success";
        }

    } catch (Throwable $ex) {

        $conn->rollback();

        $_SESSION["orders_message"] = $ex->getMessage();
        $_SESSION["orders_message_type"] = "error";
    }

    header("Location: " . $redirectUrl);
    exit;
}

/* =========================
   FETCH ORDERS
========================= */
$sql = "
    SELECT
        o.order_id,
        o.user_id,
        o.order_date,
        o.status,
        o.payment_method,
        o.payment_status,
        o.total_amount,
        u.name,
        u.email
    FROM orders o
    INNER JOIN users u ON o.user_id = u.user_id
";

if ($filterPayment === "paid") {

    $sql .= "
        WHERE LOWER(o.payment_status) = 'paid'
    ";

} elseif ($filterStatus !== "") {

    $sql .= "
        WHERE LOWER(o.status) = ?
    ";
}

$sql .= " ORDER BY o.order_date DESC";

if ($filterPayment === "paid") {

    $result = $conn->query($sql);

} elseif ($filterStatus !== "") {

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $filterStatus);
    $stmt->execute();
    $result = $stmt->get_result();

} else {

    $result = $conn->query($sql);
}

/* =========================
   PAGE HEADING
========================= */
if ($filterPayment === "paid") {
    $pageTitle = "Paid Orders";
} elseif ($filterStatus !== "") {
    $pageTitle = ucfirst($filterStatus) . " Orders";
} else {
    $pageTitle = "Manage Orders";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?> | DRESORA Admin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #fffafc;
            color: #5d405c;
        }

        .container {
            max-width: 1250px;
            margin: auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0 0 8px;
            color: #5d405c;
        }

        .subtitle {
            color: #8b7b89;
            margin: 0;
        }

        .back-link {
            text-decoration: none;
            background: #5d405c;
            color: white;
            padding: 11px 17px;
            border-radius: 8px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 22px 0;
        }

        .filter-btn {
            display: inline-block;
            text-decoration: none;
            padding: 10px 15px;
            border: 1px solid #e6d7e4;
            border-radius: 8px;
            color: #5d405c;
            background: white;
            font-size: 14px;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: #8b5a83;
            border-color: #8b5a83;
            color: white;
        }

        .message {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .message.success {
            background: #e6f7e9;
            color: #236b32;
        }

        .message.error {
            background: #ffe9e9;
            color: #a12626;
        }

        .table-card {
            background: white;
            border: 1px solid #f0e5ef;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(93, 64, 92, 0.06);
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        th,
        td {
            padding: 15px 13px;
            text-align: left;
            border-bottom: 1px solid #f1e8f0;
            font-size: 13px;
        }

        th {
            background: #f8eef7;
            color: #5d405c;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #fffaff;
        }

        .status,
        .payment-status {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .status.pending {
            background: #fff0c9;
            color: #855b00;
        }

        .status.confirmed {
            background: #e3edff;
            color: #2454a6;
        }

        .status.rejected,
        .status.cancelled {
            background: #ffe3e3;
            color: #a32626;
        }

        .status.completed {
            background: #dff5e5;
            color: #26733b;
        }

        .payment-status.paid {
            background: #dff5e5;
            color: #26733b;
        }

        .payment-status.unpaid {
            background: #fff0c9;
            color: #855b00;
        }

        .payment-status.other {
            background: #eee7f2;
            color: #5d405c;
        }

        .actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .action-btn {
            border: none;
            border-radius: 6px;
            padding: 8px 10px;
            color: white;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }

        .view-btn {
            background: #8b5a83;
        }

        .confirm-btn {
            background: #31834b;
        }

        .cancel-btn {
            background: #c64b4b;
        }

        .empty {
            text-align: center;
            padding: 40px 15px;
            color: #8b7b89;
        }

        .no-action {
            color: #968a95;
            font-size: 12px;
        }

        @media (max-width: 600px) {
            body {
                padding: 14px;
            }

            .topbar {
                align-items: flex-start;
            }

            h1 {
                font-size: 24px;
            }

            .filter-btn {
                padding: 9px 11px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <div class="topbar">
        <div>
            <h1><?= e($pageTitle) ?></h1>
            <p class="subtitle">
                View and manage customer rental orders.
            </p>
        </div>

        <a href="dashboard.php" class="back-link">
            ← Admin Dashboard
        </a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <nav class="filters">
        <a
            href="orders.php"
            class="filter-btn <?= ($filterStatus === "" && $filterPayment === "") ? "active" : "" ?>"
        >All Orders</a>

        <a
            href="orders.php?status=pending"
            class="filter-btn <?= $filterStatus === "pending" ? "active" : "" ?>"
        >Pending</a>

        <a
            href="orders.php?status=confirmed"
            class="filter-btn <?= $filterStatus === "confirmed" ? "active" : "" ?>"
        >Confirmed</a>

        <a
            href="orders.php?status=rejected"
            class="filter-btn <?= $filterStatus === "rejected" ? "active" : "" ?>"
        >Rejected</a>

        <a
            href="orders.php?payment=paid"
            class="filter-btn <?= $filterPayment === "paid" ? "active" : "" ?>"
        >Paid Orders</a>

        <a
            href="orders.php?status=completed"
            class="filter-btn <?= $filterStatus === "completed" ? "active" : "" ?>"
        >Completed</a>

        <a
            href="orders.php?status=cancelled"
            class="filter-btn <?= $filterStatus === "cancelled" ? "active" : "" ?>"
        >Cancelled</a>
    </nav>

    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Order Date</th>
                    <th>Total (Rs.)</th>
                    <th>Payment Method</th>
                    <th>Payment Status</th>
                    <th>Order Status</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <?php
                        $status = strtolower($row["status"] ?? "");
                        $paymentStatus = strtolower(
                            $row["payment_status"] ?? ""
                        );
                        ?>

                        <tr>
                            <td>#<?= (int)$row["order_id"] ?></td>

                            <td><?= e($row["name"]) ?></td>

                            <td><?= e($row["email"]) ?></td>

                            <td><?= e($row["order_date"]) ?></td>

                            <td>
                                <?= number_format(
                                    (float)$row["total_amount"],
                                    2
                                ) ?>
                            </td>

                            <td><?= e($row["payment_method"]) ?></td>

                            <td>
                                <span class="payment-status <?= $paymentStatus === "paid" ? "paid" : ($paymentStatus === "unpaid" ? "unpaid" : "other") ?>">
                                    <?= e($paymentStatus ?: "unknown") ?>
                                </span>
                            </td>

                            <td>
                                <span class="status <?= e($status) ?>">
                                    <?= e($status) ?>
                                </span>
                            </td>

                            <td>
                                <div class="actions">

                                    <a
                                        href="order-details.php?order_id=<?= (int)$row["order_id"] ?>"
                                        class="action-btn view-btn"
                                    >View</a>

                                    <?php if ($status === "pending"): ?>

                                        <form
                                            method="POST"
                                            action=""
                                            onsubmit="return confirm('Confirm this order?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?= (int)$row["order_id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="confirm"
                                            >

                                            <input
                                                type="hidden"
                                                name="return_status"
                                                value="<?= e($filterStatus) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="return_payment"
                                                value="<?= e($filterPayment) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action-btn confirm-btn"
                                            >Confirm</button>
                                        </form>

                                        <form
                                            method="POST"
                                            action=""
                                            onsubmit="return confirm('Cancel this order and restore its stock?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?= (int)$row["order_id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="cancel"
                                            >

                                            <input
                                                type="hidden"
                                                name="return_status"
                                                value="<?= e($filterStatus) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="return_payment"
                                                value="<?= e($filterPayment) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="action-btn cancel-btn"
                                            >Cancel</button>
                                        </form>

                                    <?php else: ?>

                                        <span class="no-action">
                                            No action
                                        </span>

                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="9" class="empty">
                            No orders found for this filter.
                        </td>
                    </tr>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>