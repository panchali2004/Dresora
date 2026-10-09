<?php

session_start();

require_once "../config/database.php";


/* =========================
   ADMIN ONLY
========================= */

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../account.php");
    exit;
}


/* =========================
   GET ORDER ID
========================= */

if (!isset($_GET["order_id"]) || !is_numeric($_GET["order_id"])) {
    header("Location: orders.php");
    exit;
}

$order_id = (int) $_GET["order_id"];

$message = "";
$messageType = "";

/* =========================
   CONFIRM / REJECT ORDER
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    if ($action === "confirm") {

        $new_status = "confirmed";

        $stmt = $conn->prepare("
            UPDATE orders
            SET status = ?
            WHERE order_id = ?
            AND status = 'pending'
        ");

        if (!$stmt) {
            $message = "Database error: " . $conn->error;
            $messageType = "error";
        } else {
            $stmt->bind_param("si", $new_status, $order_id);

            if ($stmt->execute() && $stmt->affected_rows === 1) {
                $message = "Order confirmed successfully.";
                $messageType = "success";
            } else {
                $message = "Unable to confirm this order.";
                $messageType = "error";
            }

            $stmt->close();
        }

    } elseif ($action === "reject") {

        $conn->begin_transaction();

        try {

            // 1. Lock and check order status
            $check_stmt = $conn->prepare("
                SELECT status
                FROM orders
                WHERE order_id = ?
                FOR UPDATE
            ");

            if (!$check_stmt) {
                throw new Exception($conn->error);
            }

            $check_stmt->bind_param("i", $order_id);
            $check_stmt->execute();

            $check_result = $check_stmt->get_result();
            $check_order = $check_result->fetch_assoc();
            $check_stmt->close();

            if (!$check_order) {
                throw new Exception("Order not found.");
            }

            if (strtolower($check_order["status"]) !== "pending") {
                throw new Exception(
                    "Only pending orders can be rejected."
                );
            }

            // 2. Get order items
            $items_stmt = $conn->prepare("
                SELECT dress_id, size, quantity
                FROM order_items
                WHERE order_id = ?
            ");

            if (!$items_stmt) {
                throw new Exception($conn->error);
            }

            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();

            $items_result = $items_stmt->get_result();

            $order_items = [];

            while ($item = $items_result->fetch_assoc()) {
                $order_items[] = $item;
            }

            $items_stmt->close();

            if (count($order_items) === 0) {
                throw new Exception("No order items found.");
            }

            // 3. Restore stock for each dress and size
            $stock_stmt = $conn->prepare("
                UPDATE dress_size_stock
                SET quantity = quantity + ?
                WHERE dress_id = ?
                AND size = ?
            ");

            if (!$stock_stmt) {
                throw new Exception($conn->error);
            }

            foreach ($order_items as $item) {

                $quantity = (int) $item["quantity"];
                $dress_id = (int) $item["dress_id"];
                $size = trim($item["size"]);

                if ($quantity <= 0) {
                    throw new Exception(
                        "Invalid quantity for Dress ID: " . $dress_id
                    );
                }

                $stock_stmt->bind_param(
                    "iis",
                    $quantity,
                    $dress_id,
                    $size
                );

                if (!$stock_stmt->execute()) {
                    throw new Exception($stock_stmt->error);
                }

                if ($stock_stmt->affected_rows !== 1) {
                    throw new Exception(
                        "Matching stock row not found for Dress ID: " .
                        $dress_id . ", Size: " . $size
                    );
                }
            }

            $stock_stmt->close();

            // 4. Update order status only after stock restoration
            $new_status = "rejected";

            $status_stmt = $conn->prepare("
                UPDATE orders
                SET status = ?
                WHERE order_id = ?
                AND status = 'pending'
            ");

            if (!$status_stmt) {
                throw new Exception($conn->error);
            }

            $status_stmt->bind_param(
                "si",
                $new_status,
                $order_id
            );

            if (!$status_stmt->execute() ||
                $status_stmt->affected_rows !== 1) {
                throw new Exception(
                    "Unable to update order status."
                );
            }

            $status_stmt->close();

            // 5. Commit all changes together
            $conn->commit();

            $message = "Order rejected and stock restored successfully.";
            $messageType = "success";

        } catch (Throwable $e) {

            $conn->rollback();

            $message = "Reject failed: " . $e->getMessage();
            $messageType = "error";
        }
    }
}
/* =========================
   GET ORDER DETAILS
========================= */

$order_sql = "
    SELECT
        o.order_id,
        o.order_date,
        o.status,
        o.payment_method,
        o.total_amount,
        u.name,
        u.email,
        u.phone
    FROM orders o
    INNER JOIN users u
        ON o.user_id = u.user_id
    WHERE o.order_id = ?
";

$order_stmt = $conn->prepare($order_sql);

$order_stmt->bind_param(
    "i",
    $order_id
);

$order_stmt->execute();

$order_result = $order_stmt->get_result();

$order = $order_result->fetch_assoc();

$order_stmt->close();


/* =========================
   ORDER NOT FOUND
========================= */

if (!$order) {
    header("Location: orders.php");
    exit;
}


/* =========================
   GET ORDER ITEMS
========================= */

$items_sql = "
    SELECT
        oi.order_item_id,
        oi.quantity,
        oi.size,
        oi.start_date,
        oi.expected_return_date,
        oi.rental_days,
        oi.rental_price,

        d.dress_name,
        d.description,
        d.colour,
        d.image_url

    FROM order_items oi

    INNER JOIN dresses d
        ON oi.dress_id = d.dress_id

    WHERE oi.order_id = ?

    ORDER BY oi.order_item_id ASC
";

$items_stmt = $conn->prepare($items_sql);

$items_stmt->bind_param(
    "i",
    $order_id
);

$items_stmt->execute();

$items_result = $items_stmt->get_result();

$items = [];

while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}

$items_stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Order #<?php echo $order_id; ?> - DRESORA Admin
    </title>


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
            width: 92%;

            max-width: 1100px;

            margin: 35px auto;
        }


        .page-title {
            color: #5d405c;

            margin-bottom: 25px;
        }


        .message {
            padding: 13px 16px;

            margin-bottom: 20px;

            border-radius: 7px;

            font-weight: bold;
        }


        .message.success {
            background: #e8f7e8;

            color: #2e7d32;

            border: 1px solid #b7dfb9;
        }


        .message.error {
            background: #fdeaea;

            color: #c62828;

            border: 1px solid #efb6b6;
        }


        .card {
            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px rgba(0,0,0,0.08);

            margin-bottom: 25px;
        }


        .card h2 {
            color: #5d405c;

            margin-top: 0;
        }


        .order-info {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 15px;
        }


        .info-box {
            background: #fffafc;

            padding: 15px;

            border-radius: 8px;

            border: 1px solid #eee;
        }


        .info-box strong {
            display: block;

            color: #5d405c;

            margin-bottom: 5px;
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


        .status.rejected {
            background: #f5e5e5;

            color: #8b3030;
        }


        .status.completed {
            background: #e9f8ef;

            color: #267342;
        }


        .status.cancelled {
            background: #fdecec;

            color: #b33a3a;
        }


        /* =========================
           ITEM
        ========================= */

        .item {
            display: flex;

            gap: 20px;

            padding: 20px 0;

            border-bottom: 1px solid #eee;
        }


        .item:last-child {
            border-bottom: none;
        }


        .item-image {
            width: 130px;

            height: 160px;

            object-fit: cover;

            border-radius: 8px;

            background: #f5edf4;
        }


        .item-details {
            flex: 1;
        }


        .item-details h3 {
            color: #5d405c;

            margin-top: 0;

            margin-bottom: 8px;
        }


        .item-details p {
            margin: 6px 0;

            color: #666;

            font-size: 14px;
        }


        .rental-info {
            margin-top: 15px;

            padding: 15px;

            background: #fff0f7;

            border-radius: 8px;
        }


        .rental-info-title {
            color: #5d405c;

            font-weight: bold;

            margin-bottom: 10px;
        }


        .rental-info p {
            margin: 6px 0;
        }


        .item-price {
            color: #5d405c;

            font-weight: bold;

            font-size: 16px;
        }


        /* =========================
           TOTAL
        ========================= */

        .total-box {
            text-align: right;

            padding-top: 20px;

            border-top: 2px solid #eadfea;
        }


        .total-box span {
            font-size: 22px;

            font-weight: bold;

            color: #5d405c;
        }


        /* =========================
           ACTION BUTTONS
        ========================= */

        .actions {
            display: flex;

            gap: 12px;

            margin-top: 25px;
        }


        .confirm-btn,
        .reject-btn {
            border: none;

            padding: 12px 25px;

            border-radius: 7px;

            color: white;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;
        }


        .confirm-btn {
            background: #267342;
        }


        .confirm-btn:hover {
            background: #1e5c34;
        }


        .reject-btn {
            background: #b33a3a;
        }


        .reject-btn:hover {
            background: #922e2e;
        }


        @media (max-width: 700px) {

            .order-info {
                grid-template-columns: 1fr;
            }


            .item {
                flex-direction: column;
            }


            .item-image {
                width: 100%;

                height: 250px;
            }


            .actions {
                flex-direction: column;
            }

        }

    </style>

</head>


<body>


<header class="header">

    <h2>DRESORA Admin</h2>

    <a href="orders.php" class="back-btn">
        ← Orders
    </a>

</header>


<div class="container">


    <h1 class="page-title">

        Order #<?php echo $order_id; ?>

    </h1>


    <?php if ($message !== ""): ?>

        <div class="message <?php echo $messageType; ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <!-- =========================
         CUSTOMER / ORDER INFO
    ========================== -->

    <div class="card">

        <h2>Order Information</h2>


        <div class="order-info">


            <div class="info-box">

                <strong>Customer</strong>

                <?php
                echo htmlspecialchars($order["name"]);
                ?>

            </div>


            <div class="info-box">

                <strong>Email</strong>

                <?php
                echo htmlspecialchars($order["email"]);
                ?>

            </div>


            <div class="info-box">

                <strong>Phone</strong>

                <?php
                echo htmlspecialchars(
                    $order["phone"] ?? "Not provided"
                );
                ?>

            </div>


            <div class="info-box">

                <strong>Order Date</strong>

                <?php

                $order_timestamp = strtotime(
                    $order["order_date"]
                );

                if ($order_timestamp !== false) {

                    echo date(
                        "M d, Y h:i A",
                        $order_timestamp
                    );

                } else {

                    echo "Not specified";
                }

                ?>

            </div>


            <div class="info-box">

                <strong>Payment Method</strong>

                <?php
                echo htmlspecialchars(
                    $order["payment_method"]
                );
                ?>

            </div>


            <div class="info-box">

                <strong>Status</strong>

                <?php

                $status = strtolower(
                    $order["status"]
                );

                ?>

                <span class="status <?php echo htmlspecialchars($status); ?>">

                    <?php

                    echo ucfirst(
                        htmlspecialchars(
                            $order["status"]
                        )
                    );

                    ?>

                </span>

            </div>


        </div>


        <?php if (strtolower($order["status"]) === "pending"): ?>

            <div class="actions">


                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="confirm"
                    >

                    <button
                        type="submit"
                        class="confirm-btn"
                        onclick="return confirm('Are you sure you want to confirm this order?');"
                    >

                        ✓ Confirm Order

                    </button>

                </form>


                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="reject"
                    >

                    <button
                        type="submit"
                        class="reject-btn"
                        onclick="return confirm('Are you sure you want to reject this order?');"
                    >

                        ✕ Reject Order

                    </button>

                </form>


            </div>

        <?php endif; ?>


    </div>


    <!-- =========================
         RENTAL ITEMS
    ========================== -->

    <div class="card">

        <h2>Rental Items</h2>


        <?php if (count($items) > 0): ?>


            <?php foreach ($items as $item): ?>


                <div class="item">


                    <?php

                    $firstImage = "";

                    if (!empty($item["image_url"])) {

                        $imageList = explode(
                            ",",
                            $item["image_url"]
                        );

                        $firstImage = trim(
                            $imageList[0]
                        );
                    }

                    ?>


                    <?php if ($firstImage !== ""): ?>

                        <img
                            src="../<?php echo htmlspecialchars($firstImage); ?>"
                            alt="<?php echo htmlspecialchars($item["dress_name"]); ?>"
                            class="item-image"
                        >

                    <?php else: ?>

                        <div class="item-image">
                            No Image
                        </div>

                    <?php endif; ?>


                    <div class="item-details">


                        <h3>

                            <?php

                            echo htmlspecialchars(
                                $item["dress_name"]
                            );

                            ?>

                        </h3>


                        <p>

                            <strong>
                                Colour:
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $item["colour"]
                            );

                            ?>

                        </p>


                        <p>

                            <strong>
                                Size:
                            </strong>

                            <?php

                            echo htmlspecialchars(
                                $item["size"]
                            );

                            ?>

                        </p>


                        <p>

                            <strong>
                                Quantity:
                            </strong>

                            <?php

                            echo (int)$item["quantity"];

                            ?>

                        </p>


                        <div class="rental-info">


                            <div class="rental-info-title">

                                📅 Rental Information

                            </div>


                            <!-- =========================
                                 START DATE
                            ========================== -->

                            <p>

                                <strong>
                                    Rental Start Date:
                                </strong>


                                <?php

                                $start_date_display = "Not specified";


                                if (
                                    !empty($item["start_date"]) &&
                                    $item["start_date"] !== "0000-00-00"
                                ) {

                                    $start_obj = DateTime::createFromFormat(
                                        "Y-m-d",
                                        $item["start_date"],
                                        new DateTimeZone("Asia/Colombo")
                                    );


                                    if ($start_obj !== false) {

                                        $start_date_display =
                                            $start_obj->format(
                                                "M d, Y"
                                            );
                                    }
                                }


                                echo htmlspecialchars(
                                    $start_date_display
                                );

                                ?>

                            </p>


                            <!-- =========================
                                 EXPECTED RETURN DATE
                            ========================== -->

                            <p>

                                <strong>
                                    Expected Return Date:
                                </strong>


                                <?php

                                /*
                                =========================================
                                EXPECTED RETURN DATE

                                1. If database has a valid date,
                                   use it.

                                2. If database has 0000-00-00,
                                   calculate from start_date + 5 days.

                                This prevents:
                                Nov 30, -0001
                                =========================================
                                */

                                $return_date_display = "Not specified";


                                /* =========================
                                   CHECK SAVED DATE
                                ========================= */

                                if (
                                    !empty($item["expected_return_date"]) &&
                                    $item["expected_return_date"] !== "0000-00-00"
                                ) {

                                    $return_obj =
                                        DateTime::createFromFormat(
                                            "Y-m-d",
                                            $item["expected_return_date"],
                                            new DateTimeZone("Asia/Colombo")
                                        );


                                    if ($return_obj !== false) {

                                        $return_date_display =
                                            $return_obj->format(
                                                "M d, Y"
                                            );
                                    }
                                }


                                /* =========================
                                   FALLBACK

                                   START DATE + 5 DAYS
                                ========================= */

                                if (
                                    $return_date_display === "Not specified" &&
                                    !empty($item["start_date"]) &&
                                    $item["start_date"] !== "0000-00-00"
                                ) {

                                    $start_obj =
                                        DateTime::createFromFormat(
                                            "Y-m-d",
                                            $item["start_date"],
                                            new DateTimeZone("Asia/Colombo")
                                        );


                                    if ($start_obj !== false) {

                                        $start_obj->modify("+5 days");

                                        $return_date_display =
                                            $start_obj->format(
                                                "M d, Y"
                                            );
                                    }
                                }


                                echo htmlspecialchars(
                                    $return_date_display
                                );

                                ?>

                            </p>


                            <!-- =========================
                                 RENTAL PERIOD
                            ========================== -->

                            <p>

                                <strong>
                                    Rental Period:
                                </strong>


                                <?php

                                if (
                                    !empty(
                                        $item["rental_days"]
                                    )
                                ) {

                                    echo
                                        (int)$item["rental_days"]
                                        . " days";

                                } else {

                                    echo "5 days";
                                }

                                ?>

                            </p>


                            <!-- =========================
                                 LATE RETURN FEE
                            ========================== -->

                            <p>

                                <strong>
                                    Late Return Fee:
                                </strong>

                                Rs. 500 per day

                            </p>


                        </div>


                        <!-- =========================
                             ITEM PRICE
                        ========================== -->

                        <p class="item-price">

                            Rs.

                            <?php

                            echo number_format(
                                (float)$item["rental_price"],
                                2
                            );

                            ?>

                            ×

                            <?php

                            echo (int)$item["quantity"];

                            ?>

                        </p>


                    </div>


                </div>


            <?php endforeach; ?>


            <!-- =========================
                 TOTAL
            ========================== -->

            <div class="total-box">

                Total Amount:

                <span>

                    Rs.

                    <?php

                    echo number_format(
                        (float)$order["total_amount"],
                        2
                    );

                    ?>

                </span>

            </div>


        <?php else: ?>

            <p>
                No rental items found for this order.
            </p>

        <?php endif; ?>


    </div>


</div>


</body>

</html>