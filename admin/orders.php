<?php

session_start();

require_once "../config/database.php";

// Admin only
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../account.php");
    exit;
}


/* =========================================================
   CONFIRM / CANCEL ORDER
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $orderId = intval($_POST["order_id"] ?? 0);
    $action = $_POST["action"] ?? "";

    if ($orderId > 0 && in_array($action, ["confirm", "cancel"], true)) {

        /*
         * Get current order details
         */
        $getOrder = $conn->prepare("
            SELECT order_id, user_id, status
            FROM orders
            WHERE order_id = ?
            LIMIT 1
        ");

        $getOrder->bind_param("i", $orderId);
        $getOrder->execute();

        $orderResult = $getOrder->get_result();

        if ($orderResult->num_rows === 1) {

            $order = $orderResult->fetch_assoc();

            $userId = (int)$order["user_id"];
            $currentStatus = strtolower(trim($order["status"]));

            /*
             * Only pending orders can be confirmed/cancelled
             */
            if ($currentStatus === "pending") {

                if ($action === "confirm") {

                    $newStatus = "confirmed";

                    $title = "Order Confirmed";

                    $message =
                        "Your order #" . $orderId .
                        " has been confirmed. Please proceed with payment.";

                    $notificationType = "order_confirmed";

                } else {

                    $newStatus = "cancelled";

                    $title = "Order Cancelled";

                    $message =
                        "Your order #" . $orderId .
                        " has been cancelled by the admin.";

                    $notificationType = "order_cancelled";
                }


                /*
                 * Start transaction
                 *
                 * Order status update and notification insert
                 * should both happen together.
                 */
                $conn->begin_transaction();

                try {

                    /*
                     * Update order status
                     */
                    $updateOrder = $conn->prepare("
                        UPDATE orders
                        SET status = ?
                        WHERE order_id = ?
                    ");

                    $updateOrder->bind_param(
                        "si",
                        $newStatus,
                        $orderId
                    );

                    if (!$updateOrder->execute()) {
                        throw new Exception("Failed to update order status.");
                    }


                    /*
                     * Insert notification
                     */
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

                    $insertNotification->bind_param(
                        "isss",
                        $userId,
                        $title,
                        $message,
                        $notificationType
                    );

                    if (!$insertNotification->execute()) {
                        throw new Exception("Failed to create notification.");
                    }


                    /*
                     * Everything successful
                     */
                    $conn->commit();

                } catch (Exception $e) {

                    /*
                     * Something went wrong
                     */
                    $conn->rollback();

                }
            }
        }

        /*
         * Refresh page
         */
        header("Location: orders.php");
        exit;
    }
}


/* =========================================================
   GET CUSTOMER ORDERS
========================================================= */

$sql = "SELECT
            o.order_id,
            o.user_id,
            o.order_date,
            o.status,
            o.payment_method,
            o.total_amount,
            u.name,
            u.email
        FROM orders o
        INNER JOIN users u
            ON o.user_id = u.user_id
        ORDER BY o.order_date DESC";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

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

            box-shadow:
                0 3px 12px rgba(0,0,0,0.08);

            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
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

        .view-btn,
        .confirm-btn,
        .cancel-btn {
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

            th,
            td {
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

    <h1 class="page-title">
        Customer Orders
    </h1>


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

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($order = $result->fetch_assoc()): ?>

                        <?php

                        $status = strtolower(
                            trim($order["status"] ?? "")
                        );

                        ?>

                        <tr>

                            <!-- ORDER ID -->
                            <td>

                                <span class="order-id">

                                    #<?php
                                    echo (int)$order["order_id"];
                                    ?>

                                </span>

                            </td>


                            <!-- CUSTOMER -->
                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $order["name"]
                                );
                                ?>

                            </td>


                            <!-- EMAIL -->
                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $order["email"]
                                );
                                ?>

                            </td>


                            <!-- DATE -->
                            <td>

                                <?php
                                echo date(
                                    "M d, Y",
                                    strtotime(
                                        $order["order_date"]
                                    )
                                );
                                ?>

                            </td>


                            <!-- TOTAL -->
                            <td>

                                <span class="amount">

                                    Rs.
                                    <?php
                                    echo number_format(
                                        (float)$order["total_amount"],
                                        2
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- PAYMENT METHOD -->
                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $order["payment_method"] ?? "-"
                                );
                                ?>

                            </td>


                            <!-- STATUS -->
                            <td>

                                <span
                                    class="status <?php
                                    echo htmlspecialchars($status);
                                    ?>"
                                >

                                    <?php
                                    echo ucfirst(
                                        htmlspecialchars(
                                            $order["status"]
                                        )
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- ACTION -->
                            <td>

                                <div class="action-buttons">

                                    <!-- VIEW -->
                                    <a
                                        href="order-details.php?order_id=<?php
                                        echo (int)$order["order_id"];
                                        ?>"
                                        class="view-btn"
                                    >
                                        View
                                    </a>


                                    <?php if ($status === "pending"): ?>

                                        <!-- CONFIRM -->
                                        <form
                                            method="POST"
                                            class="action-form"
                                            onsubmit="return confirm(
                                                'Are you sure you want to confirm Order #<?php
                                                echo (int)$order["order_id"];
                                                ?>?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?php
                                                echo (int)$order["order_id"];
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="confirm"
                                            >

                                            <button
                                                type="submit"
                                                class="confirm-btn"
                                            >
                                                Confirm
                                            </button>

                                        </form>


                                        <!-- CANCEL -->
                                        <form
                                            method="POST"
                                            class="action-form"
                                            onsubmit="return confirm(
                                                'Are you sure you want to cancel Order #<?php
                                                echo (int)$order["order_id"];
                                                ?>?'
                                            );"
                                        >

                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?php
                                                echo (int)$order["order_id"];
                                                ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="cancel"
                                            >

                                            <button
                                                type="submit"
                                                class="cancel-btn"
                                            >
                                                Cancel
                                            </button>

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

                        <td colspan="8">

                            No customer orders found.

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>

</html>