<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Login Check
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {

    header("Location: ../account.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Get Order ID
|--------------------------------------------------------------------------
*/

if (
    !isset($_GET["order_id"]) ||
    !is_numeric($_GET["order_id"])
) {

    die("Invalid order ID.");
}

$order_id = (int) $_GET["order_id"];


/*
|--------------------------------------------------------------------------
| Get Order
|--------------------------------------------------------------------------
|
| Only get the order belonging to the
| currently logged-in customer.
|
*/

$sql = "SELECT
            order_id,
            user_id,
            total_amount,
            payment_method,
            payment_status,
            status
        FROM orders
        WHERE order_id = ?
        AND user_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    die("Order query failed: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $order_id,
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

$order = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Check Order Exists
|--------------------------------------------------------------------------
*/

if (!$order) {

    die("Order not found.");
}


/*
|--------------------------------------------------------------------------
| Get Order Information
|--------------------------------------------------------------------------
*/

$order_status = strtolower(
    trim($order["status"] ?? "")
);

$payment_method = strtolower(
    trim($order["payment_method"] ?? "")
);

$payment_status = strtolower(
    trim($order["payment_status"] ?? "unpaid")
);

$total_amount = (float) $order["total_amount"];


/*
|--------------------------------------------------------------------------
| Check Payment Method
|--------------------------------------------------------------------------
|
| Only Online Payment orders should be
| marked as paid here.
|
*/

if (
    $payment_method !== "online payment" &&
    $payment_method !== "online" &&
    $payment_method !== "payhere"
) {

    die("This order is not an online payment order.");
}


/*
|--------------------------------------------------------------------------
| Check Order Status
|--------------------------------------------------------------------------
|
| Only confirmed orders can be paid.
|
*/

if ($order_status !== "confirmed") {

    die("This order has not been confirmed by the admin.");
}


/*
|--------------------------------------------------------------------------
| Update Payment Status
|--------------------------------------------------------------------------
|
| If the order is still unpaid,
| change it to paid.
|
*/

if ($payment_status === "unpaid") {

    $update_sql = "
        UPDATE orders
        SET payment_status = 'paid'
        WHERE order_id = ?
        AND user_id = ?
        AND payment_status = 'unpaid'
    ";

    $update_stmt = $conn->prepare($update_sql);

    if (!$update_stmt) {

        die(
            "Payment update failed: " .
            $conn->error
        );
    }

    $update_stmt->bind_param(
        "ii",
        $order_id,
        $user_id
    );

    $update_stmt->execute();

    $update_stmt->close();

    /* Update local value as well */
    $payment_status = "paid";
}


/*
|--------------------------------------------------------------------------
| Customer Notification
|--------------------------------------------------------------------------
*/

$customer_title = "Payment Successful";

$customer_message =
    "Your payment for Order #" .
    $order_id .
    " has been completed successfully. " .
    "Amount: Rs. " .
    number_format($total_amount, 2);

$customer_type = "payment_success";


/*
|--------------------------------------------------------------------------
| Insert Customer Notification
|--------------------------------------------------------------------------
|
| IMPORTANT:
| The actual column is notification_type,
| NOT type.
|
*/

$customer_sql = "
    INSERT INTO notifications
    (
        user_id,
        title,
        message,
        notification_type,
        is_read,
        created_at
    )
    VALUES (?, ?, ?, ?, 0, NOW())
";

$customer_stmt = $conn->prepare($customer_sql);

if ($customer_stmt) {

    $customer_stmt->bind_param(
        "isss",
        $user_id,
        $customer_title,
        $customer_message,
        $customer_type
    );

    $customer_stmt->execute();

    $customer_stmt->close();
}


/*
|--------------------------------------------------------------------------
| Get Admin Users
|--------------------------------------------------------------------------
*/

$admin_sql = "
    SELECT user_id
    FROM users
    WHERE role = 'admin'
";

$admin_result = $conn->query($admin_sql);


/*
|--------------------------------------------------------------------------
| Admin Notification
|--------------------------------------------------------------------------
*/

$admin_title = "Payment Received";

$admin_message =
    "Customer has successfully paid Rs. " .
    number_format($total_amount, 2) .
    " for Order #" .
    $order_id .
    ".";

$admin_type = "payment_received";


/*
|--------------------------------------------------------------------------
| Insert Notification for All Admins
|--------------------------------------------------------------------------
*/

if ($admin_result) {

    $admin_notification_sql = "
        INSERT INTO notifications
        (
            user_id,
            title,
            message,
            notification_type,
            is_read,
            created_at
        )
        VALUES (?, ?, ?, ?, 0, NOW())
    ";

    $admin_notification_stmt =
        $conn->prepare(
            $admin_notification_sql
        );

    if ($admin_notification_stmt) {

        while (
            $admin = $admin_result->fetch_assoc()
        ) {

            $admin_id = (int) $admin["user_id"];

            $admin_notification_stmt->bind_param(
                "isss",
                $admin_id,
                $admin_title,
                $admin_message,
                $admin_type
            );

            $admin_notification_stmt->execute();
        }

        $admin_notification_stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Display Amount
|--------------------------------------------------------------------------
*/

$display_amount = number_format(
    $total_amount,
    2
);

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Payment Successful - Dresora</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            padding: 0;

            font-family: Arial, sans-serif;

            background: #fffafc;

            color: #5d405c;

            min-height: 100vh;

            display: flex;

            justify-content: center;

            align-items: center;
        }


        .success-container {

            width: 90%;

            max-width: 550px;

            background: #ffffff;

            padding: 45px 35px;

            border-radius: 20px;

            text-align: center;

            box-shadow:
                0 8px 30px
                rgba(139, 90, 131, 0.15);
        }


        .success-icon {

            width: 80px;

            height: 80px;

            margin: 0 auto 20px;

            border-radius: 50%;

            background: #8b5a83;

            color: white;

            display: flex;

            justify-content: center;

            align-items: center;

            font-size: 42px;

            font-weight: bold;
        }


        h1 {

            margin: 10px 0;

            color: #8b5a83;

            font-size: 30px;
        }


        .success-message {

            color: #666;

            font-size: 16px;

            line-height: 1.6;

            margin-bottom: 25px;
        }


        .order-box {

            background: #fffafc;

            border: 1px solid #ead9e7;

            border-radius: 12px;

            padding: 20px;

            margin: 25px 0;

            text-align: left;
        }


        .order-row {

            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding: 8px 0;

            border-bottom: 1px solid #eee;
        }


        .order-row:last-child {

            border-bottom: none;
        }


        .label {

            color: #777;

            font-weight: normal;
        }


        .value {

            color: #5d405c;

            font-weight: bold;

            text-align: right;
        }


        .paid {

            color: #4d8b65;
        }


        .amount {

            color: #8b5a83;

            font-size: 22px;

            font-weight: bold;
        }


        .buttons {

            margin-top: 25px;
        }


        .orders-button {

            display: inline-block;

            background: #8b5a83;

            color: white;

            padding: 13px 28px;

            border-radius: 8px;

            text-decoration: none;

            font-size: 16px;

            font-weight: bold;

            transition: 0.2s;
        }


        .orders-button:hover {

            background: #5d405c;
        }


        .home-button {

            display: inline-block;

            margin-left: 8px;

            padding: 12px 25px;

            border: 1px solid #8b5a83;

            border-radius: 8px;

            color: #8b5a83;

            text-decoration: none;

            font-size: 16px;
        }


        .home-button:hover {

            background: #fffafc;
        }


        .thank-you {

            margin-top: 25px;

            color: #888;

            font-size: 14px;
        }


        @media (max-width: 600px) {

            .success-container {

                padding: 35px 20px;
            }


            h1 {

                font-size: 25px;
            }


            .order-row {

                flex-direction: column;

                gap: 3px;
            }


            .value {

                text-align: left;
            }


            .home-button {

                margin-left: 0;

                margin-top: 10px;
            }

        }

    </style>

</head>


<body>


<div class="success-container">


    <!-- Success Icon -->

    <div class="success-icon">

        ✓

    </div>


    <!-- Heading -->

    <h1>

        Payment Successful!

    </h1>


    <!-- Message -->

    <p class="success-message">

        Your online payment has been completed successfully.

        Your order has been updated and your payment
        has been recorded.

    </p>


    <!-- Order Details -->

    <div class="order-box">


        <div class="order-row">

            <span class="label">
                Order ID
            </span>

            <span class="value">

                #<?php echo $order_id; ?>

            </span>

        </div>


        <div class="order-row">

            <span class="label">
                Amount
            </span>

            <span class="value amount">

                LKR <?php echo $display_amount; ?>

            </span>

        </div>


        <div class="order-row">

            <span class="label">
                Payment Method
            </span>

            <span class="value">

                Online Payment

            </span>

        </div>


        <div class="order-row">

            <span class="label">
                Payment Status
            </span>

            <span class="value paid">

                ✓ Paid

            </span>

        </div>


        <div class="order-row">

            <span class="label">
                Order Status
            </span>

            <span class="value">

                Confirmed

            </span>

        </div>


    </div>


    <!-- Buttons -->

    <div class="buttons">


        <a
            href="../customer/my-orders.php"
            class="orders-button"
        >

            View My Orders

        </a>


        <a
            href="../index.php"
            class="home-button"
        >

            Home

        </a>


    </div>


    <!-- Thank You -->

    <div class="thank-you">

        Thank you for choosing Dresora 💜

    </div>


</div>


</body>

</html>