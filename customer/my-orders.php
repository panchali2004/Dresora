<?php

session_start();

require_once "../config/database.php";


/* =========================
   Check Login
========================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: ../account.php");

    exit;
}


/* =========================
   Allow Customers Only
========================= */

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "customer"
) {

    header("Location: ../account.php");

    exit;
}


$user_id = (int) $_SESSION["user_id"];


/* =========================
   Cancel Order + Restore Stock
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["cancel_order_id"])
) {

    $cancel_order_id = (int) $_POST["cancel_order_id"];


    /* =========================
       START TRANSACTION
    ========================= */

    $conn->begin_transaction();


    try {


        /* =========================
           CHECK ORDER
        ========================= */

        $check_sql = "
            SELECT
                status,
                payment_status
            FROM orders
            WHERE order_id = ?
            AND user_id = ?
            FOR UPDATE
        ";


        $check_stmt = $conn->prepare($check_sql);


        if (!$check_stmt) {

            throw new Exception(
                "Order check failed: " . $conn->error
            );
        }


        $check_stmt->bind_param(
            "ii",
            $cancel_order_id,
            $user_id
        );


        $check_stmt->execute();


        $check_result = $check_stmt->get_result();


        $order_check = $check_result->fetch_assoc();


        $check_stmt->close();


        /* =========================
           ORDER NOT FOUND
        ========================= */

        if (!$order_check) {

            throw new Exception(
                "Order not found."
            );
        }


        /* =========================
           CHECK ORDER STATUS
        ========================= */

        $current_status = strtolower(
            trim($order_check["status"])
        );


        $current_payment_status = strtolower(
            trim($order_check["payment_status"] ?? "unpaid")
        );


        /*
         * Customer can cancel:
         *
         * 1. Pending order
         * 2. Confirmed but unpaid order
         *
         * Paid orders cannot be cancelled.
         */


        if ($current_payment_status === "paid") {

            throw new Exception(
                "Paid orders cannot be cancelled."
            );
        }


        if (
            $current_status !== "pending" &&
            $current_status !== "confirmed"
        ) {

            throw new Exception(
                "This order cannot be cancelled."
            );
        }


        /* =========================
           GET ORDER ITEMS
        ========================= */

        $items_sql = "
            SELECT
                dress_id,
                size,
                quantity
            FROM order_items
            WHERE order_id = ?
        ";


        $items_stmt = $conn->prepare($items_sql);


        if (!$items_stmt) {

            throw new Exception(
                "Order items query failed: " .
                $conn->error
            );
        }


        $items_stmt->bind_param(
            "i",
            $cancel_order_id
        );


        $items_stmt->execute();


        $items_result = $items_stmt->get_result();


        /* =========================
           RESTORE STOCK
        ========================= */

        $stock_sql = "
            UPDATE dress_size_stock
            SET quantity = quantity + ?
            WHERE dress_id = ?
            AND size = ?
        ";


        $stock_stmt = $conn->prepare($stock_sql);


        if (!$stock_stmt) {

            throw new Exception(
                "Stock update failed: " .
                $conn->error
            );
        }


        while ($item = $items_result->fetch_assoc()) {


            $quantity = (int) $item["quantity"];


            $dress_id = (int) $item["dress_id"];


            $size = $item["size"];


            $stock_stmt->bind_param(
                "iis",
                $quantity,
                $dress_id,
                $size
            );


            if (!$stock_stmt->execute()) {

                throw new Exception(
                    "Stock restore failed: " .
                    $stock_stmt->error
                );
            }

        }


        $stock_stmt->close();


        $items_stmt->close();


        /* =========================
           UPDATE ORDER STATUS
        ========================= */

        /*
         * IMPORTANT:
         * Allow both pending and confirmed.
         */

        $cancel_sql = "
            UPDATE orders
            SET status = 'cancelled'
            WHERE order_id = ?
            AND user_id = ?
            AND LOWER(status) IN ('pending', 'confirmed')
            AND LOWER(COALESCE(payment_status, 'unpaid')) <> 'paid'
        ";


        $cancel_stmt = $conn->prepare($cancel_sql);


        if (!$cancel_stmt) {

            throw new Exception(
                "Cancel update failed: " .
                $conn->error
            );
        }


        $cancel_stmt->bind_param(
            "ii",
            $cancel_order_id,
            $user_id
        );


        if (!$cancel_stmt->execute()) {

            throw new Exception(
                "Unable to cancel order: " .
                $cancel_stmt->error
            );
        }


        /*
         * Check whether order was actually updated.
         */

        if ($cancel_stmt->affected_rows !== 1) {

            throw new Exception(
                "Order could not be cancelled."
            );
        }


        $cancel_stmt->close();


        /* =========================
           COMMIT TRANSACTION
        ========================= */

        $conn->commit();


    } catch (Exception $e) {


        /* =========================
           ROLLBACK
        ========================= */

        $conn->rollback();


        die(
            "Unable to cancel order: " .
            htmlspecialchars($e->getMessage())
        );
    }


    /* =========================
       REDIRECT
    ========================= */

    header("Location: my-orders.php");

    exit;
}


/* =========================
   Get Customer Orders
========================= */

$sql = "
    SELECT
        order_id,
        total_amount,
        payment_method,
        payment_status,
        status,
        order_date
    FROM orders
    WHERE user_id = ?
    ORDER BY order_date DESC
";


$orders = [];


$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Order query failed: " .
        $conn->error
    );
}


$stmt->bind_param(
    "i",
    $user_id
);


$stmt->execute();


$result = $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $orders[] = $row;
}


$stmt->close();

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Orders - DRESORA</title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            font-family: Arial, sans-serif;

            background: #fffafc;

            color: #5d405c;
        }


        /* =========================
           Sidebar
        ========================= */

        .sidebar {

            position: fixed;

            left: 0;

            top: 0;

            width: 240px;

            height: 100vh;

            background: #5d405c;

            color: white;

            padding: 25px 15px;

            overflow-y: auto;
        }


        .logo {

            text-align: center;

            margin-bottom: 35px;
        }


        .logo span {

            display: block;

            font-size: 25px;

            font-weight: bold;

            letter-spacing: 2px;
        }


        .logo small {

            font-size: 11px;

            letter-spacing: 2px;

            opacity: 0.8;
        }


        .sidebar ul {

            list-style: none;
        }


        .sidebar ul li {

            margin-bottom: 8px;
        }


        .sidebar ul li a {

            display: block;

            text-decoration: none;

            color: white;

            padding: 13px 15px;

            border-radius: 8px;

            font-size: 14px;

            transition: 0.3s;
        }


        .sidebar ul li a:hover,
        .sidebar ul li a.active {

            background: #8b5a83;
        }


        /* =========================
           Main
        ========================= */

        .main {

            margin-left: 240px;

            min-height: 100vh;
        }


        /* =========================
           Topbar
        ========================= */

        .topbar {

            height: 70px;

            background: white;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 35px;

            border-bottom: 1px solid #eee;

            position: sticky;

            top: 0;

            z-index: 10;
        }


        .topbar h2 {

            font-size: 22px;

            color: #5d405c;
        }


        .user-name {

            background: #fff0f7;

            padding: 10px 16px;

            border-radius: 20px;

            color: #8b5a83;

            font-weight: 600;

            font-size: 14px;
        }


        /* =========================
           Content
        ========================= */

        .content {

            padding: 35px;
        }


        .orders-container {

            max-width: 1000px;

            margin: 0 auto;
        }


        .orders-card {

            background: white;

            border-radius: 15px;

            padding: 30px;

            box-shadow:
                0 5px 20px
                rgba(93, 64, 92, 0.08);
        }


        .orders-header {

            margin-bottom: 25px;
        }


        .orders-header h1 {

            font-size: 25px;

            margin-bottom: 8px;

            color: #5d405c;
        }


        .orders-header p {

            color: #777;

            font-size: 14px;
        }


        /* =========================
           Order Table
        ========================= */

        .table-container {

            width: 100%;

            overflow-x: auto;
        }


        .orders-table {

            width: 100%;

            border-collapse: collapse;

            min-width: 800px;
        }


        .orders-table th {

            background: #fff0f7;

            color: #5d405c;

            padding: 14px 12px;

            text-align: left;

            font-size: 13px;

            border-bottom: 2px solid #eadfea;
        }


        .orders-table td {

            padding: 15px 12px;

            font-size: 14px;

            color: #555;

            border-bottom: 1px solid #eee;
        }


        .orders-table tr:hover {

            background: #fffafc;
        }


        /* =========================
           Order ID
        ========================= */

        .order-id {

            color: #8b5a83;

            font-weight: bold;
        }


        /* =========================
           Status
        ========================= */

        .status {

            display: inline-block;

            padding: 6px 12px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;
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

            background: #fdecec;

            color: #b33a3a;
        }


        /* =========================
           Payment
        ========================= */

        .payment-method {

            font-size: 13px;

            color: #666;
        }


        /* =========================
           Amount
        ========================= */

        .amount {

            font-weight: bold;

            color: #5d405c;
        }


        /* =========================
           Empty Orders
        ========================= */

        .empty-orders {

            text-align: center;

            padding: 50px 20px;
        }


        .empty-orders .icon {

            font-size: 50px;

            margin-bottom: 15px;
        }


        .empty-orders h3 {

            color: #5d405c;

            margin-bottom: 8px;
        }


        .empty-orders p {

            color: #777;

            font-size: 14px;

            margin-bottom: 20px;
        }


        .shop-btn {

            display: inline-block;

            text-decoration: none;

            background: #8b5a83;

            color: white;

            padding: 12px 25px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: 600;

            transition: 0.3s;
        }


        .shop-btn:hover {

            background: #6f4569;
        }


        /* =========================
           View Button
        ========================= */

        .view-btn {

            display: inline-block;

            text-decoration: none;

            background: #8b5a83;

            color: white;

            padding: 8px 14px;

            border-radius: 7px;

            font-size: 12px;

            font-weight: 600;

            transition: 0.3s;
        }


        .view-btn:hover {

            background: #6f4569;
        }


        /* =========================
           Cancel Button
        ========================= */

        .cancel-btn {

            display: inline-block;

            background: #fdecec;

            color: #b33a3a;

            border: 1px solid #f3caca;

            padding: 8px 14px;

            border-radius: 7px;

            font-size: 12px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.3s;
        }


        .cancel-btn:hover {

            background: #b33a3a;

            color: white;
        }


        /* =========================
           Pay Now Button
        ========================= */

        .pay-btn {

            display: inline-block;

            text-decoration: none;

            background: #a66b9b;

            color: white;

            padding: 8px 14px;

            border-radius: 7px;

            font-size: 12px;

            font-weight: 600;

            transition: 0.3s;

            box-shadow:
                0 3px 8px
                rgba(166, 107, 155, 0.20);
        }


        .pay-btn:hover {

            background: #8b5a83;

            transform: translateY(-1px);
        }


        /* =========================
           Paid Button
        ========================= */

        .paid-btn {

            display: inline-block;

            background: #e9f8ef;

            color: #267342;

            padding: 8px 14px;

            border-radius: 7px;

            font-size: 12px;

            font-weight: 600;
        }


        /* =========================
           Action Buttons
        ========================= */

        .action-buttons {

            display: flex;

            gap: 7px;

            align-items: center;

            flex-wrap: wrap;
        }


        /* =========================
           Responsive
        ========================= */

        @media (max-width: 800px) {

            .sidebar {

                width: 200px;
            }


            .main {

                margin-left: 200px;
            }


            .content {

                padding: 20px;
            }

        }


        @media (max-width: 600px) {

            .sidebar {

                position: relative;

                width: 100%;

                height: auto;
            }


            .main {

                margin-left: 0;
            }


            .topbar {

                padding: 0 20px;
            }


            .content {

                padding: 15px;
            }


            .orders-card {

                padding: 20px;
            }

        }

    </style>

</head>


<body>


<!-- =========================
     Sidebar
========================= -->

<div class="sidebar">

    <div class="logo">

        <span>DRESORA</span>

        <small>DRESS RENTAL</small>

    </div>


    <ul>

        <li>

            <a href="profile.php">

                👤 My Profile

            </a>

        </li>


        <li>

            <a href="../cart.php">

                🛒 My Cart

            </a>

        </li>


        <li>

            <a href="rental-requests.php">

                📋 Rental Requests

            </a>

        </li>


        <li>

            <a href="my-orders.php" class="active">

                📦 My Orders

            </a>

        </li>


        <li>

            <a href="wishlist.php">

                ❤️ Wishlist

            </a>

        </li>


        <li>

            <a href="notifications.php">

                🔔 Notifications

            </a>

        </li>


        <li>

            <a href="settings.php">

                ⚙️ Change Password

            </a>

        </li>


        <li>

            <a href="logout.php">

                🚪 Logout

            </a>

        </li>

    </ul>

</div>


<!-- =========================
     Main
========================= -->

<div class="main">


    <!-- =========================
         Topbar
    ========================== -->

    <div class="topbar">

        <h2>
            My Orders
        </h2>


        <div class="user-name">

            👤

            <?php

            echo htmlspecialchars(
                $_SESSION["name"]
            );

            ?>

        </div>

    </div>


    <!-- =========================
         Content
    ========================== -->

    <div class="content">

        <div class="orders-container">

            <div class="orders-card">


                <div class="orders-header">

                    <h1>
                        My Orders
                    </h1>

                    <p>
                        View your rental order history and order details.
                    </p>

                </div>


                <?php if (!empty($orders)): ?>


                    <div class="table-container">


                        <table class="orders-table">


                            <thead>

                                <tr>

                                    <th>
                                        Order ID
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Total Amount
                                    </th>

                                    <th>
                                        Payment Method
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach ($orders as $order): ?>


                                    <?php

                                    $status = strtolower(
                                        trim(
                                            $order["status"] ?? ""
                                        )
                                    );


                                    $paymentMethod = strtolower(
                                        trim(
                                            $order["payment_method"] ?? ""
                                        )
                                    );


                                    $paymentStatus = strtolower(
                                        trim(
                                            $order["payment_status"] ?? "unpaid"
                                        )
                                    );

                                    ?>


                                    <tr>


                                        <!-- Order ID -->

                                        <td>

                                            <span class="order-id">

                                                #

                                                <?php

                                                echo htmlspecialchars(
                                                    $order["order_id"]
                                                );

                                                ?>

                                            </span>

                                        </td>


                                        <!-- Date -->

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


                                        <!-- Amount -->

                                        <td>

                                            <span class="amount">

                                                Rs.

                                                <?php

                                                echo number_format(
                                                    $order["total_amount"],
                                                    2
                                                );

                                                ?>

                                            </span>

                                        </td>


                                        <!-- Payment Method -->

                                        <td>

                                            <span class="payment-method">

                                                <?php

                                                echo htmlspecialchars(
                                                    $order["payment_method"]
                                                );

                                                ?>

                                            </span>

                                        </td>


                                        <!-- Status -->

                                        <td>

                                            <span
                                                class="status <?php echo htmlspecialchars($status); ?>"
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


                                        <!-- Actions -->

                                        <td>

                                            <div class="action-buttons">


                                                <!-- View Details -->

                                                <a
                                                    href="order-details.php?order_id=<?php echo (int)$order["order_id"]; ?>"
                                                    class="view-btn"
                                                >

                                                    View Details

                                                </a>


                                                <!-- Cancel Order -->

                                                <?php if (
                                                    $status === "pending" ||
                                                    (
                                                        $status === "confirmed" &&
                                                        $paymentStatus !== "paid"
                                                    )
                                                ): ?>


                                                    <form
                                                        method="POST"
                                                        style="margin: 0;"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="cancel_order_id"
                                                            value="<?php echo (int)$order["order_id"]; ?>"
                                                        >


                                                        <button
                                                            type="submit"
                                                            class="cancel-btn"
                                                            onclick="return confirm('Are you sure you want to cancel this order?');"
                                                        >

                                                            Cancel Order

                                                        </button>

                                                    </form>


                                                <?php endif; ?>


                                                <!-- Online Payment -->

                                                <?php if (
                                                    $status === "confirmed" &&
                                                    $paymentStatus === "unpaid" &&
                                                    (
                                                        $paymentMethod === "online payment" ||
                                                        $paymentMethod === "online" ||
                                                        $paymentMethod === "payhere"
                                                    )
                                                ): ?>


                                                    <a
                                                        href="../payment/payhere-checkout.php?order_id=<?php echo (int)$order["order_id"]; ?>"
                                                        class="pay-btn"
                                                    >

                                                        💳 Pay Now

                                                    </a>


                                                <?php elseif ($paymentStatus === "paid"): ?>


                                                    <span class="paid-btn">

                                                        ✓ Paid

                                                    </span>


                                                <?php endif; ?>


                                            </div>

                                        </td>

                                    </tr>


                                <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                <?php else: ?>


                    <div class="empty-orders">


                        <div class="icon">
                            📦
                        </div>


                        <h3>
                            No Orders Yet
                        </h3>


                        <p>
                            You have not placed any rental orders yet.
                        </p>


                        <a
                            href="../products.php"
                            class="shop-btn"
                        >

                            Browse Dresses

                        </a>


                    </div>


                <?php endif; ?>


            </div>

        </div>

    </div>

</div>


</body>

</html>