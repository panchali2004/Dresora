
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
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "customer") {
    header("Location: ../account.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

/* =========================
   Cancel Order + Restore Stock
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancel_order_id"])) {

    $cancel_order_id = (int) $_POST["cancel_order_id"];

    if ($cancel_order_id <= 0) {
        die("Invalid order ID.");
    }

    $conn->begin_transaction();

    try {
        /* Check order belongs to customer */
        $check_sql = "
            SELECT status, payment_status
            FROM orders
            WHERE order_id = ? AND user_id = ?
            FOR UPDATE
        ";

        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {
            throw new Exception("Order check failed: " . $conn->error);
        }

        $check_stmt->bind_param("ii", $cancel_order_id, $user_id);

        if (!$check_stmt->execute()) {
            throw new Exception("Unable to check order.");
        }

        $order_check = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if (!$order_check) {
            throw new Exception("Order not found.");
        }

        $current_status = strtolower(
            trim((string)($order_check["status"] ?? ""))
        );

        $current_payment_status = strtolower(
            trim((string)($order_check["payment_status"] ?? "unpaid"))
        );

        /* Paid orders cannot be cancelled */
        if ($current_payment_status === "paid") {
            throw new Exception("Paid orders cannot be cancelled.");
        }

        /* Only pending or confirmed orders can be cancelled */
        if (!in_array($current_status, ["pending", "confirmed"], true)) {
            throw new Exception("This order cannot be cancelled.");
        }

        /* Get order items once */
        $items_sql = "
            SELECT dress_id, size, quantity
            FROM order_items
            WHERE order_id = ?
        ";

        $items_stmt = $conn->prepare($items_sql);

        if (!$items_stmt) {
            throw new Exception("Order items query failed: " . $conn->error);
        }

        $items_stmt->bind_param("i", $cancel_order_id);

        if (!$items_stmt->execute()) {
            throw new Exception("Unable to retrieve order items.");
        }

        $items_result = $items_stmt->get_result();
        $items = [];

        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
        }

        $items_stmt->close();

        if (count($items) === 0) {
            throw new Exception("No order items found.");
        }

        /* Restore stock in dress_size_stock */
        $stock_sql = "
            UPDATE dress_size_stock
            SET quantity = quantity + ?
            WHERE dress_id = ? AND size = ?
        ";

        $stock_stmt = $conn->prepare($stock_sql);

        if (!$stock_stmt) {
            throw new Exception("Stock update preparation failed: " . $conn->error);
        }

        foreach ($items as $item) {
            $quantity = (int)$item["quantity"];
            $dress_id = (int)$item["dress_id"];
            $size = trim((string)$item["size"]);

            if ($quantity <= 0 || $dress_id <= 0 || $size === "") {
                throw new Exception("Invalid order item data.");
            }

            $stock_stmt->bind_param("iis", $quantity, $dress_id, $size);

            if (!$stock_stmt->execute()) {
                throw new Exception("Stock restoration failed: " . $stock_stmt->error);
            }

            if ($stock_stmt->affected_rows !== 1) {
                throw new Exception(
                    "Stock row not found for Dress ID " .
                    $dress_id . ", Size " . $size .
                    ". Please check dress_size_stock."
                );
            }
        }

        $stock_stmt->close();

        /* Update order status */
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
            throw new Exception("Cancel update preparation failed: " . $conn->error);
        }

        $cancel_stmt->bind_param("ii", $cancel_order_id, $user_id);

        if (!$cancel_stmt->execute()) {
            throw new Exception("Unable to cancel order: " . $cancel_stmt->error);
        }

        if ($cancel_stmt->affected_rows !== 1) {
            throw new Exception("Order could not be cancelled.");
        }

        $cancel_stmt->close();

        /* Commit stock and order changes together */
        $conn->commit();

    } catch (Throwable $e) {
        $conn->rollback();

        die(
            "Unable to cancel order: " .
            htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8")
        );
    }

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
    die("Order query failed: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    die("Unable to retrieve orders.");
}

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();

/* =========================
   HTML Escape Helper
========================= */
function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* =========================
   Status Display Helper
========================= */
function normaliseStatus($value, $default = "pending") {
    $value = strtolower(trim((string)($value ?? "")));
    return $value === "" ? $default : $value;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        /* Sidebar */
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

        /* Main */
        .main {
            margin-left: 240px;
            min-height: 100vh;
        }

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

        /* Content */
        .content {
            padding: 35px;
        }

        .orders-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .orders-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(93, 64, 92, 0.08);
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

        /* Order table */
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
            vertical-align: middle;
        }

        .orders-table tbody tr:hover {
            background: #fffafc;
        }

        .order-id {
            color: #8b5a83;
            font-weight: bold;
        }

        /* Order status badges */
        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status.pending {
            background: #fff4d6;
            color: #9a7200;
        }

        .status.confirmed {
            background: #e7f4ff;
            color: #2876a8;
        }

        .status.delivered,
        .status.completed {
            background: #e9f8ef;
            color: #267342;
        }

        .status.cancelled,
        .status.rejected {
            background: #fdecec;
            color: #b33a3a;
        }

        .status.unknown {
            background: #eeeeee;
            color: #555555;
        }

        .payment-method {
            font-size: 13px;
            color: #666;
        }

        .amount {
            font-weight: bold;
            color: #5d405c;
            white-space: nowrap;
        }

        /* Empty orders */
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

        /* Buttons */
        .view-btn,
        .pay-btn {
            display: inline-block;
            text-decoration: none;
            color: white;
            padding: 8px 14px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            transition: 0.3s;
            white-space: nowrap;
        }

        .view-btn {
            background: #8b5a83;
        }

        .view-btn:hover {
            background: #6f4569;
        }

        .pay-btn {
            background: #a66b9b;
            box-shadow: 0 3px 8px rgba(166, 107, 155, 0.20);
        }

        .pay-btn:hover {
            background: #8b5a83;
            transform: translateY(-1px);
        }

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
            white-space: nowrap;
        }

        .cancel-btn:hover {
            background: #b33a3a;
            color: white;
        }

        .paid-btn {
            display: inline-block;
            background: #e9f8ef;
            color: #267342;
            padding: 8px 14px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .action-buttons {
            display: flex;
            gap: 7px;
            align-items: center;
            flex-wrap: wrap;
        }

        .action-buttons form {
            margin: 0;
        }

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

            .topbar {
                padding: 0 20px;
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

            .content {
                padding: 15px;
            }

            .orders-card {
                padding: 20px;
            }

            .topbar {
                padding: 0 15px;
            }
        }
    </style>
</head>

<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <span>DRESORA</span>
        <small>DRESS RENTAL</small>
    </div>

    <ul>
        <li><a href="profile.php">👤 My Profile</a></li>
        <li><a href="../cart.php">🛒 My Cart</a></li>
        <li><a href="rental-requests.php">📋 Rental Requests</a></li>
        <li><a href="my-orders.php" class="active">📦 My Orders</a></li>
        <li><a href="wishlist.php">❤️ Wishlist</a></li>
        <li><a href="notifications.php">🔔 Notifications</a></li>
        <li><a href="settings.php">⚙️ Change Password</a></li>
        <li><a href="logout.php">🚪 Logout</a></li>
    </ul>
</div>

<!-- Main -->
<div class="main">

    <!-- Topbar -->
    <div class="topbar">
        <h2>My Orders</h2>

        <div class="user-name">
            👤 <?= e($_SESSION["name"] ?? "Customer") ?>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="orders-container">
            <div class="orders-card">

                <div class="orders-header">
                    <h1>My Orders</h1>
                    <p>View your rental order history and order details.</p>
                </div>

                <?php if (!empty($orders)): ?>

                    <div class="table-container">
                        <table class="orders-table">

                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Total Amount</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($orders as $order): ?>

                                <?php
                                $status = normaliseStatus($order["status"] ?? null);
                                $paymentStatus = normaliseStatus(
                                    $order["payment_status"] ?? null,
                                    "unpaid"
                                );

                                $paymentMethod = strtolower(
                                    trim((string)($order["payment_method"] ?? ""))
                                );

                                $allowedStatuses = [
                                    "pending",
                                    "confirmed",
                                    "delivered",
                                    "completed",
                                    "cancelled",
                                    "rejected"
                                ];

                                $statusClass = in_array($status, $allowedStatuses, true)
                                    ? $status
                                    : "unknown";

                                $statusLabel = ucfirst(
                                    in_array($status, $allowedStatuses, true)
                                        ? $status
                                        : "Unknown"
                                );
                                ?>

                                <tr>
                                    <!-- Order ID -->
                                    <td>
                                        <span class="order-id">
                                            #<?= (int)$order["order_id"] ?>
                                        </span>
                                    </td>

                                    <!-- Date -->
                                    <td>
                                        <?php
                                        $timestamp = strtotime((string)$order["order_date"]);
                                        echo $timestamp !== false
                                            ? date("M d, Y", $timestamp)
                                            : "N/A";
                                        ?>
                                    </td>

                                    <!-- Amount -->
                                    <td>
                                        <span class="amount">
                                            Rs. <?= number_format((float)$order["total_amount"], 2) ?>
                                        </span>
                                    </td>

                                    <!-- Payment Method -->
                                    <td>
                                        <span class="payment-method">
                                            <?= e($order["payment_method"] ?? "N/A") ?>
                                        </span>
                                    </td>

                                    <!-- Order Status -->
                                    <td>
                                        <span class="status <?= e($statusClass) ?>">
                                            <?= e($statusLabel) ?>
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td>
                                        <div class="action-buttons">

                                            <!-- View Details -->
                                            <a
                                                href="order-details.php?order_id=<?= (int)$order["order_id"] ?>"
                                                class="view-btn"
                                            >
                                                View Details
                                            </a>

                                            <!-- Cancel Order -->
                                            <?php
                                            $canCancel =
                                                $paymentStatus !== "paid" &&
                                                in_array($status, ["pending", "confirmed"], true);
                                            ?>

                                            <?php if ($canCancel): ?>
                                                <form method="POST">
                                                    <input
                                                        type="hidden"
                                                        name="cancel_order_id"
                                                        value="<?= (int)$order["order_id"] ?>"
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
                                                in_array(
                                                    $paymentMethod,
                                                    ["online payment", "online", "payhere"],
                                                    true
                                                )
                                            ): ?>
                                                <a
                                                    href="../payment/payhere-checkout.php?order_id=<?= (int)$order["order_id"] ?>"
                                                    class="pay-btn"
                                                >
                                                    💳 Pay Now
                                                </a>

                                            <?php elseif ($paymentStatus === "paid"): ?>
                                                <span class="paid-btn">✓ Paid</span>
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
                        <div class="icon">📦</div>

                        <h3>No Orders Yet</h3>

                        <p>You have not placed any rental orders yet.</p>

                        <a href="../products.php" class="shop-btn">
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
