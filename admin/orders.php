
<?php
session_start();

require_once "../config/database.php";

// Admin only
if (
    !isset($_SESSION["user_id"]) ||
    strtolower($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../account.php");
    exit;
}

$message = "";
$messageType = "";

/* =========================================================
   CONFIRM / CANCEL ORDER
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $orderId = (int)($_POST["order_id"] ?? 0);
    $action = $_POST["action"] ?? "";

    if (
        $orderId > 0 &&
        in_array($action, ["confirm", "cancel"], true)
    ) {
        $conn->begin_transaction();

        try {

            // Lock the order to prevent duplicate processing
            $getOrder = $conn->prepare("
                SELECT order_id, user_id, status
                FROM orders
                WHERE order_id = ?
                FOR UPDATE
            ");

            if (!$getOrder) {
                throw new Exception($conn->error);
            }

            $getOrder->bind_param("i", $orderId);

            if (!$getOrder->execute()) {
                throw new Exception($getOrder->error);
            }

            $orderResult = $getOrder->get_result();
            $order = $orderResult->fetch_assoc();
            $getOrder->close();

            if (!$order) {
                throw new Exception("Order not found.");
            }

            $userId = (int)$order["user_id"];
            $currentStatus = strtolower(trim($order["status"]));

            // Only pending orders can be processed
            if ($currentStatus !== "pending") {
                throw new Exception(
                    "This order has already been processed. Stock was not restored again."
                );
            }

            if ($action === "confirm") {

                $newStatus = "confirmed";
                $title = "Order Confirmed";
                $notificationMessage =
                    "Your order #" . $orderId .
                    " has been confirmed. Please proceed with payment.";
                $notificationType = "order_confirmed";

            } else {

                /*
                 * Restore stock for every item in this order.
                 * Stock is restored only while the order is pending.
                 */

                $itemsStmt = $conn->prepare("
                    SELECT dress_id, size, quantity
                    FROM order_items
                    WHERE order_id = ?
                ");

                if (!$itemsStmt) {
                    throw new Exception($conn->error);
                }

                $itemsStmt->bind_param("i", $orderId);

                if (!$itemsStmt->execute()) {
                    throw new Exception($itemsStmt->error);
                }

                $itemsResult = $itemsStmt->get_result();
                $items = [];

                while ($item = $itemsResult->fetch_assoc()) {
                    $items[] = $item;
                }

                $itemsStmt->close();

                if (count($items) === 0) {
                    throw new Exception(
                        "No order items found. Stock was not restored."
                    );
                }

                $stockStmt = $conn->prepare("
                    UPDATE dress_size_stock
                    SET quantity = quantity + ?
                    WHERE dress_id = ?
                      AND size = ?
                ");

                if (!$stockStmt) {
                    throw new Exception($conn->error);
                }

                foreach ($items as $item) {

                    $dressId = (int)$item["dress_id"];
                    $size = trim($item["size"]);
                    $quantity = (int)$item["quantity"];

                    if ($dressId <= 0 || $quantity <= 0 || $size === "") {
                        throw new Exception(
                            "Invalid order item data. Stock was not restored."
                        );
                    }

                    $stockStmt->bind_param(
                        "iis",
                        $quantity,
                        $dressId,
                        $size
                    );

                    if (!$stockStmt->execute()) {
                        throw new Exception($stockStmt->error);
                    }

                    // A matching stock row must exist
                    if ($stockStmt->affected_rows !== 1) {
                        throw new Exception(
                            "Stock row not found for Dress ID " .
                            $dressId . ", Size " . $size .
                            ". Check dress_size_stock."
                        );
                    }
                }

                $stockStmt->close();

                $newStatus = "cancelled";
                $title = "Order Cancelled";
                $notificationMessage =
                    "Your order #" . $orderId .
                    " has been cancelled by the admin.";
                $notificationType = "order_cancelled";
            }

            // Change status only if it is still pending
            $updateOrder = $conn->prepare("
                UPDATE orders
                SET status = ?
                WHERE order_id = ?
                  AND status = 'pending'
            ");

            if (!$updateOrder) {
                throw new Exception($conn->error);
            }

            $updateOrder->bind_param(
                "si",
                $newStatus,
                $orderId
            );

            if (!$updateOrder->execute()) {
                throw new Exception($updateOrder->error);
            }

            if ($updateOrder->affected_rows !== 1) {
                throw new Exception(
                    "Order status was not changed. No stock restoration was committed."
                );
            }

            $updateOrder->close();

            // Create customer notification
            $insertNotification = $conn->prepare("
                INSERT INTO notifications
                (
                    user_id,
                    title,
                    message,
                    notification_type,
                    is_read
                )
                VALUES (?, ?, ?, ?, 0)
            ");

            if (!$insertNotification) {
                throw new Exception($conn->error);
            }

            $insertNotification->bind_param(
                "isss",
                $userId,
                $title,
                $notificationMessage,
                $notificationType
            );

            if (!$insertNotification->execute()) {
                throw new Exception($insertNotification->error);
            }

            $insertNotification->close();

            // Commit stock, status and notification together
            $conn->commit();

            $_SESSION["orders_message"] =
                $action === "confirm"
                    ? "Order #" . $orderId . " confirmed successfully."
                    : "Order #" . $orderId .
                      " cancelled. Stock restored successfully.";

            $_SESSION["orders_message_type"] = "success";

        } catch (Throwable $e) {

            $conn->rollback();

            $_SESSION["orders_message"] =
                "Operation failed: " . $e->getMessage();

            $_SESSION["orders_message_type"] = "error";
        }

        header("Location: orders.php");
        exit;
    }
}

// Show result after redirect
$message = $_SESSION["orders_message"] ?? "";
$messageType = $_SESSION["orders_message_type"] ?? "";

unset(
    $_SESSION["orders_message"],
    $_SESSION["orders_message_type"]
);

/* =========================================================
   GET CUSTOMER ORDERS
========================================================= */

$sql = "
    SELECT
        o.order_id,
        o.user_id,
        o.order_date,
        o.status,
        o.payment_method,
        o.total_amount,
        u.name,
        u.email
    FROM orders o
    INNER JOIN users u ON o.user_id = u.user_id
    ORDER BY o.order_date DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Unable to load orders: " . htmlspecialchars($conn->error));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - DRESORA</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffafc;
            color: #333;
        }

        .header {
            background: #5d405c;
            color: white;
            padding: 18px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            margin: 0;
        }

        .back-btn {
            color: white;
            text-decoration: none;
            background: #8b5a83;
            padding: 10px 18px;
            border-radius: 6px;
        }

        .back-btn:hover {
            background: #a66b9b;
        }

        .container {
            width: 94%;
            max-width: 1400px;
            margin: 35px auto;
        }

        .page-title {
            color: #5d405c;
            margin-bottom: 25px;
        }

        .table-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f5edf4;
            color: #5d405c;
        }

        .order-id {
            color: #8b5a83;
            font-weight: bold;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status.pending {
            background: #fff4d6;
            color: #9a7200;
        }

        .status.confirmed {
            background: #e7f4ff;
            color: #2876a8;
        }

        .status.completed {
            background: #e9f8ef;
            color: #267342;
        }

        .status.cancelled {
            background: #fdecec;
            color: #b33a3a;
        }

        .status.rejected {
            background: #f5e5e5;
            color: #8b3030;
        }

        .amount {
            font-weight: bold;
            color: #5d405c;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }

        .view-btn, .confirm-btn, .cancel-btn {
            display: inline-block;
            text-decoration: none;
            border: none;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .view-btn {
            background: #8b5a83;
            color: white;
        }

        .view-btn:hover {
            background: #5d405c;
        }

        .confirm-btn {
            background: #e9f8ef;
            color: #267342;
        }

        .confirm-btn:hover {
            background: #d5f0df;
        }

        .cancel-btn {
            background: #fdecec;
            color: #b33a3a;
        }

        .cancel-btn:hover {
            background: #f8d7d7;
        }

        .action-form {
            display: inline;
            margin: 0;
        }

        .no-action {
            color: #999;
            font-size: 12px;
        }

        .alert {
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .alert.success {
            background: #e9f8ef;
            color: #267342;
        }

        .alert.error {
            background: #fdecec;
            color: #b33a3a;
        }

        @media (max-width: 900px) {
            .header {
                padding: 15px 20px;
            }

            .container {
                width: 96%;
            }

            .table-box {
                padding: 15px;
            }

            th, td {
                padding: 10px;
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

<header class="header">
    <h2>DRESORA Admin</h2>

    <a href="dashboard.php" class="back-btn">
        ← Dashboard
    </a>
</header>

<div class="container">

    <h1 class="page-title">Customer Orders</h1>

    <?php if ($message !== ""): ?>
        <div class="alert <?= $messageType === "success" ? "success" : "error" ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="table-box">
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($result->num_rows > 0): ?>

                    <?php while ($order = $result->fetch_assoc()): ?>

                        <?php
                        $status = strtolower(trim($order["status"] ?? ""));
                        $id = (int)$order["order_id"];
                        ?>

                        <tr>
                            <td>
                                <span class="order-id">
                                    #<?= $id ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($order["name"] ?? "") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($order["email"] ?? "") ?>
                            </td>

                            <td>
                                <?php
                                echo !empty($order["order_date"])
                                    ? date("M d, Y", strtotime($order["order_date"]))
                                    : "-";
                                ?>
                            </td>

                            <td>
                                <span class="amount">
                                    Rs. <?= number_format((float)$order["total_amount"], 2) ?>
                                </span>
                            </td>

                            <td>
                                <?= htmlspecialchars($order["payment_method"] ?? "-") ?>
                            </td>

                            <td>
                                <span class="status <?= htmlspecialchars($status) ?>">
                                    <?= htmlspecialchars(ucfirst($status)) ?>
                                </span>
                            </td>

                            <td>
                                <div class="action-buttons">

                                    <a
                                        href="order-details.php?order_id=<?= $id ?>"
                                        class="view-btn"
                                    >
                                        View
                                    </a>

                                    <?php if ($status === "pending"): ?>

                                        <form
                                            method="POST"
                                            class="action-form"
                                            onsubmit="return confirm('Are you sure you want to confirm Order #<?= $id ?>?');"
                                        >
                                            <input type="hidden" name="order_id" value="<?= $id ?>">
                                            <input type="hidden" name="action" value="confirm">

                                            <button type="submit" class="confirm-btn">
                                                Confirm
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            class="action-form"
                                            onsubmit="return confirm('Are you sure you want to cancel Order #<?= $id ?>? Stock will be restored.');"
                                        >
                                            <input type="hidden" name="order_id" value="<?= $id ?>">
                                            <input type="hidden" name="action" value="cancel">

                                            <button type="submit" class="cancel-btn">
                                                Cancel
                                            </button>
                                        </form>

                                    <?php else: ?>

                                        <span class="no-action">No action</span>

                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8">No customer orders found.</td>
                    </tr>

                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>