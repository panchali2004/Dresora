<?php

session_start();

require_once "config/database.php";


/* =========================
   CUSTOMER LOGIN CHECK
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: account.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];


/* =========================
   GET SELECTED CART ITEMS
========================= */

$sql = "SELECT 
            cart_item_id,
            product_id,
            product_name,
            category,
            price,
            image,
            size,
            start_date,
            expected_return_date,
            rental_days,
            quantity
        FROM cart_items
        WHERE user_id = ?
        AND selected = 1
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database Error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$cart_items = [];
$total_amount = 0;


while ($row = $result->fetch_assoc()) {

    $quantity = (int)$row["quantity"];
    $price = (float)$row["price"];

    $row["item_total"] = $price * $quantity;

    $total_amount += $row["item_total"];

    $cart_items[] = $row;
}

$stmt->close();


/* =========================
   NO SELECTED ITEMS
========================= */

if (count($cart_items) === 0) {

    header("Location: cart.php");
    exit;
}


/* =========================
   GET USER DETAILS
========================= */

$user_sql = "SELECT 
                name,
                email,
                phone,
                shipping_address
             FROM users
             WHERE user_id = ?";

$user_stmt = $conn->prepare($user_sql);

if (!$user_stmt) {
    die("Database Error: " . $conn->error);
}

$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();

$user_result = $user_stmt->get_result();

$user = $user_result->fetch_assoc();

$user_stmt->close();


/* =========================
   PLACE ORDER
========================= */

$order_success = false;

$order_id = null;

$error_message = "";

$payment_method = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $payment_method = trim($_POST["payment_method"] ?? "");


    /* =========================
       VALIDATION
    ========================= */

    if (
        $name === "" ||
        $phone === "" ||
        $email === "" ||
        $address === "" ||
        $payment_method === ""
    ) {

        $error_message = "Please fill in all required fields.";

    } elseif (
        $payment_method !== "Cash on Delivery" &&
        $payment_method !== "Online Payment"
    ) {

        $error_message = "Please select a valid payment method.";

    } else {


        /* =========================
           START TRANSACTION
        ========================= */

        $conn->begin_transaction();


        try {


            /* =========================
               INSERT INTO ORDERS
            ========================= */

            $status = "Pending";


            /*
             * We do NOT store expected_return_date
             * in orders because each dress can have
             * its own rental date.
             *
             * Rental dates are stored in order_items.
             */

            $order_sql = "INSERT INTO orders
                            (
                                user_id,
                                order_date,
                                status,
                                payment_method,
                                total_amount
                            )
                          VALUES
                            (
                                ?,
                                NOW(),
                                ?,
                                ?,
                                ?
                            )";


            $order_stmt = $conn->prepare($order_sql);


            if (!$order_stmt) {
                throw new Exception(
                    "Order prepare failed: " . $conn->error
                );
            }


            $order_stmt->bind_param(
                "issd",
                $user_id,
                $status,
                $payment_method,
                $total_amount
            );


            if (!$order_stmt->execute()) {

                throw new Exception(
                    "Order insert failed: " .
                    $order_stmt->error
                );
            }


            /* Get newly created Order ID */

            $order_id = $conn->insert_id;


            $order_stmt->close();


            /* =========================
               INSERT ORDER ITEMS
            ========================= */

            $item_sql = "INSERT INTO order_items
                            (
                                order_id,
                                dress_id,
                                quantity,
                                size,
                                start_date,
                                expected_return_date,
                                rental_days,
                                rental_price
                            )
                         VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?
                            )";


            $item_stmt = $conn->prepare($item_sql);


            if (!$item_stmt) {

                throw new Exception(
                    "Order item prepare failed: " .
                    $conn->error
                );
            }


            /*
             * IMPORTANT:
             * We must loop through every selected cart item.
             */

            foreach ($cart_items as $item) {


                $dress_id = (int)$item["product_id"];

                $quantity = (int)$item["quantity"];

                $size = $item["size"];

                $start_date = $item["start_date"];

                $expected_return_date =
                    $item["expected_return_date"];

                $rental_days =
                    (int)$item["rental_days"];

                $rental_price =
                    (float)$item["price"];


                $item_stmt->bind_param(
                    "iiissiid",
                    $order_id,
                    $dress_id,
                    $quantity,
                    $size,
                    $start_date,
                    $expected_return_date,
                    $rental_days,
                    $rental_price
                );


                if (!$item_stmt->execute()) {

                    throw new Exception(
                        "Order item insert failed: " .
                        $item_stmt->error
                    );
                }
            }

/* =========================
   UPDATE DRESS SIZE STOCK
========================= */

$stock_sql = "
    UPDATE dress_size_stock
    SET quantity = quantity - ?
    WHERE dress_id = ?
    AND size = ?
    AND quantity >= ?
";

$stock_stmt = $conn->prepare($stock_sql);

if (!$stock_stmt) {
    throw new Exception(
        "Stock update prepare failed: " . $conn->error
    );
}

foreach ($cart_items as $item) {

    $dress_id = (int)$item["product_id"];
    $quantity = (int)$item["quantity"];
    $size = trim($item["size"]);

    $stock_stmt->bind_param(
        "iisi",
        $quantity,
        $dress_id,
        $size,
        $quantity
    );

    if (!$stock_stmt->execute()) {
        throw new Exception(
            "Stock update failed: " . $stock_stmt->error
        );
    }

    if ($stock_stmt->affected_rows === 0) {
        throw new Exception(
            "Not enough stock available for Dress ID: "
            . $dress_id
            . " - Size: "
            . $size
        );
    }
}

$stock_stmt->close();

$delete_sql = "
    DELETE FROM cart_items
    WHERE cart_item_id = ?
";

$delete_stmt = $conn->prepare($delete_sql);

foreach ($cart_items as $item) {
    $cart_item_id = (int)$item["cart_item_id"];

    $delete_stmt->bind_param("i", $cart_item_id);

    if (!$delete_stmt->execute()) {
        throw new Exception(
            "Cart item delete failed: " . $delete_stmt->error
        );
    }
}

$delete_stmt->close();


            /* =========================
               COMMIT TRANSACTION
            ========================= */

            $conn->commit();

            $order_success = true;


        } catch (Exception $e) {


            /* =========================
               ROLLBACK
            ========================= */

            $conn->rollback();

            $error_message =
                "Order Error: " .
                $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dresora - Checkout</title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {

            font-family: Arial, sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #fffafc,
                    #f9eef7
                );

            color: #5d405c;

            min-height: 100vh;
        }


        /* =========================
           NAVBAR
        ========================== */

        .navbar {

            background: #5d405c;

            padding: 18px 7%;

            display: flex;

            justify-content: space-between;

            align-items: center;

            box-shadow:
                0 3px 12px
                rgba(93, 64, 92, 0.15);
        }


        .logo {

            color: white;

            font-size: 27px;

            font-weight: bold;

            letter-spacing: 2px;

            text-decoration: none;
        }


        .nav-links {

            display: flex;

            gap: 25px;
        }


        .nav-links a {

            color: white;

            text-decoration: none;

            font-size: 15px;

            transition: 0.2s;
        }


        .nav-links a:hover {

            color: #f5dff0;
        }


        /* =========================
           CONTAINER
        ========================== */

        .container {

            width: 92%;

            max-width: 1150px;

            margin: 45px auto;
        }


        /* =========================
           TITLE
        ========================== */

        .title {

            text-align: center;

            margin-bottom: 35px;
        }


        .title h1 {

            color: #5d405c;

            font-size: 34px;

            margin-bottom: 8px;
        }


        .title p {

            color: #777;

            font-size: 15px;
        }


        /* =========================
           GRID
        ========================== */

        .checkout-grid {

            display: grid;

            grid-template-columns:
                1.35fr 1fr;

            gap: 30px;

            align-items: start;
        }


        /* =========================
           BOX
        ========================== */

        .box {

            background:
                rgba(255, 255, 255, 0.95);

            padding: 28px;

            border-radius: 18px;

            box-shadow:
                0 8px 25px
                rgba(93, 64, 92, 0.10);

            border: 1px solid #f0e1ed;
        }


        .box h2 {

            color: #8b5a83;

            margin-bottom: 22px;

            font-size: 21px;
        }


        /* =========================
           CUSTOMER FORM
        ========================== */

        .form-group {

            margin-bottom: 19px;
        }


        .form-group label {

            display: block;

            margin-bottom: 8px;

            font-weight: bold;

            color: #5d405c;
        }


        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group textarea {

            width: 100%;

            padding: 13px 14px;

            border:
                1px solid #ddd;

            border-radius: 9px;

            font-size: 14px;

            outline: none;

            transition: 0.2s;
        }


        .form-group input:focus,
        .form-group textarea:focus {

            border-color: #a66b9b;

            box-shadow:
                0 0 0 3px
                rgba(166, 107, 155, 0.10);
        }


        .form-group textarea {

            min-height: 95px;

            resize: vertical;
        }


        /* =========================
           PAYMENT
        ========================== */

        .payment-section {

            margin-top: 25px;

            padding-top: 22px;

            border-top:
                1px solid #eee;
        }


        .payment-section h3 {

            color: #5d405c;

            margin-bottom: 15px;

            font-size: 18px;
        }


        .payment-options {

            display: flex;

            gap: 14px;
        }


        .payment-card {

            flex: 1;

            position: relative;

            cursor: pointer;
        }


        .payment-card input {

            position: absolute;

            opacity: 0;
        }


        .payment-content {

            height: 105px;

            padding: 17px;

            border:
                2px solid #eadfea;

            border-radius: 12px;

            background: #fffafc;

            display: flex;

            align-items: center;

            gap: 12px;

            box-sizing: border-box;

            transition: 0.25s;
        }


        .payment-icon {

            font-size: 28px;

            min-width: 35px;
        }


        .payment-content strong {

            display: block;

            color: #5d405c;

            margin-bottom: 5px;

            font-size: 14px;
        }


        .payment-content span {

            display: block;

            color: #777;

            font-size: 12px;

            line-height: 1.4;
        }


        .payment-card input:checked
        + .payment-content {

            border-color: #8b5a83;

            background: #f8eef7;

            box-shadow:
                0 5px 15px
                rgba(139, 90, 131, 0.15);
        }


        .payment-card:hover
        .payment-content {

            border-color: #a66b9b;

            transform: translateY(-2px);
        }


        /* =========================
           ORDER ITEM
        ========================== */

        .order-item {

            display: flex;

            gap: 15px;

            padding: 16px 0;

            border-bottom:
                1px solid #eee;
        }


        .order-item img {

            width: 80px;

            height: 95px;

            object-fit: cover;

            border-radius: 10px;

            border: 1px solid #eee;
        }


        .item-info {

            flex: 1;
        }


        .item-info h3 {

            font-size: 16px;

            margin-bottom: 8px;

            color: #5d405c;
        }


        .item-info p {

            font-size: 13px;

            margin: 5px 0;

            color: #777;
        }


        .item-price {

            font-weight: bold;

            color: #8b5a83 !important;

            font-size: 15px !important;
        }


        /* =========================
           SUMMARY
        ========================== */

        .total-row {

            display: flex;

            justify-content: space-between;

            margin-top: 22px;

            padding-top: 17px;

            border-top:
                2px solid #eee;

            font-size: 21px;

            font-weight: bold;

            color: #5d405c;
        }


        .total-row span:last-child {

            color: #8b5a83;
        }


        /* =========================
           BUTTON
        ========================== */

        .place-btn {

            width: 100%;

            margin-top: 25px;

            padding: 15px;

            border: none;

            border-radius: 10px;

            background:
                linear-gradient(
                    135deg,
                    #8b5a83,
                    #a66b9b
                );

            color: white;

            font-size: 16px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.25s;

            box-shadow:
                0 5px 15px
                rgba(139, 90, 131, 0.25);
        }


        .place-btn:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 8px 20px
                rgba(139, 90, 131, 0.30);
        }


        /* =========================
           ERROR
        ========================== */

        .error {

            background: #ffe7e7;

            color: #b00020;

            padding: 14px;

            border-radius: 10px;

            margin-bottom: 25px;

            border:
                1px solid #f3bcbc;
        }


        /* =========================
           SUCCESS
        ========================== */

        .success {

            max-width: 650px;

            margin: 70px auto;

            text-align: center;

            background: white;

            padding: 55px 35px;

            border-radius: 20px;

            box-shadow:
                0 8px 30px
                rgba(93, 64, 92, 0.12);

            border:
                1px solid #f0e1ed;
        }


        .success-icon {

            font-size: 55px;

            margin-bottom: 15px;
        }


        .success h1 {

            color: #8b5a83;

            margin-bottom: 15px;
        }


        .success p {

            margin: 10px 0;

            color: #666;
        }


        .order-number {

            color: #5d405c;

            font-size: 18px;
        }


        .home-btn {

            display: inline-block;

            margin-top: 22px;

            padding: 13px 28px;

            background:
                linear-gradient(
                    135deg,
                    #8b5a83,
                    #a66b9b
                );

            color: white;

            text-decoration: none;

            border-radius: 9px;

            font-weight: bold;
        }


        /* =========================
           RESPONSIVE
        ========================== */

        @media (max-width: 800px) {

            .checkout-grid {

                grid-template-columns: 1fr;
            }


            .payment-options {

                flex-direction: column;
            }


            .nav-links {

                gap: 10px;
            }


            .nav-links a {

                font-size: 13px;
            }
        }


        @media (max-width: 500px) {

            .navbar {

                padding: 15px 5%;
            }


            .logo {

                font-size: 21px;
            }


            .nav-links {

                gap: 7px;
            }


            .container {

                width: 94%;

                margin-top: 30px;
            }


            .box {

                padding: 20px;
            }


            .title h1 {

                font-size: 28px;
            }
        }

    </style>

</head>


<body>


<!-- =========================
     NAVBAR
========================= -->

<nav class="navbar">

    <a href="index.php" class="logo">
        DRESORA
    </a>


    <div class="nav-links">

        <a href="index.php">
            Home
        </a>

        <a href="products.php">
            Shop
        </a>

        <a href="cart.php">
            Cart
        </a>

        <a href="account.php">
            Account
        </a>

    </div>

</nav>


<div class="container">


<?php if ($order_success): ?>


    <!-- =========================
         SUCCESS MESSAGE
    ========================== -->

    <div class="success">

        <div class="success-icon">
            🎉
        </div>


        <h1>
            Rental Order Successful!
        </h1>


        <p>
            Thank you for renting with Dresora.
        </p>


        <p class="order-number">

            Your Order ID is:

            <strong>
                #<?php echo htmlspecialchars($order_id); ?>
            </strong>

        </p>


        <p>

            Payment Method:

            <strong>
                <?php
                echo htmlspecialchars(
                    $payment_method
                );
                ?>
            </strong>

        </p>


        <p>

            Your order status is

            <strong>
                Pending
            </strong>.

        </p>


        <a
            href="index.php"
            class="home-btn"
        >
            Back to Home
        </a>

    </div>


<?php else: ?>


    <!-- =========================
         TITLE
    ========================== -->

    <div class="title">

        <h1>
            Complete Your Rental
        </h1>

        <p>
            Review your rental details and confirm your order.
        </p>

    </div>


    <?php if ($error_message !== ""): ?>

        <div class="error">

            <?php
            echo htmlspecialchars($error_message);
            ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        action="checkout.php"
    >


        <div class="checkout-grid">


            <!-- =========================
                 CUSTOMER DETAILS
            ========================== -->

            <div class="box">

                <h2>
                    Customer Details
                </h2>


                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>


                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?php
                        echo htmlspecialchars(
                            $user["name"] ?? ""
                        );
                        ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="phone">
                        Phone Number
                    </label>


                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?php
                        echo htmlspecialchars(
                            $user["phone"] ?? ""
                        );
                        ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="email">
                        Email
                    </label>


                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php
                        echo htmlspecialchars(
                            $user["email"] ?? ""
                        );
                        ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="address">
                        Shipping Address
                    </label>


                    <textarea
                        id="address"
                        name="address"
                        required
                    ><?php
                    echo htmlspecialchars(
                        $user["shipping_address"] ?? ""
                    );
                    ?></textarea>

                </div>


                <!-- =========================
                     PAYMENT METHOD
                ========================== -->

                <div class="payment-section">

                    <h3>
                        Payment Method
                    </h3>


                    <div class="payment-options">


                        <label class="payment-card">

                            <input
                                type="radio"
                                name="payment_method"
                                value="Cash on Delivery"
                                required
                            >


                            <div class="payment-content">

                                <div class="payment-icon">
                                    💵
                                </div>


                                <div>

                                    <strong>
                                        Cash on Delivery
                                    </strong>

                                    <span>
                                        Pay when your rental is delivered.
                                    </span>

                                </div>

                            </div>

                        </label>


                        <label class="payment-card">

                            <input
                                type="radio"
                                name="payment_method"
                                value="Online Payment"
                            >


                            <div class="payment-content">

                                <div class="payment-icon">
                                    💳
                                </div>


                                <div>

                                    <strong>
                                        Online Payment
                                    </strong>

                                    <span>
                                        Pay securely online.
                                    </span>

                                </div>

                            </div>

                        </label>


                    </div>

                </div>


            </div>


            <!-- =========================
                 ORDER SUMMARY
            ========================== -->

            <div class="box">

                <h2>
                    Rental Summary
                </h2>


                <?php foreach ($cart_items as $item): ?>


                    <div class="order-item">


                        <img
                            src="<?php
                            echo htmlspecialchars(
                                $item["image"]
                            );
                            ?>"
                            alt="<?php
                            echo htmlspecialchars(
                                $item["product_name"]
                            );
                            ?>"
                        >


                        <div class="item-info">


                            <h3>

                                <?php
                                echo htmlspecialchars(
                                    $item["product_name"]
                                );
                                ?>

                            </h3>


                            <p>

                                Size:

                                <?php
                                echo htmlspecialchars(
                                    $item["size"]
                                );
                                ?>

                            </p>


                            <p>

                                Rental Start Date:

                                <?php
                                echo htmlspecialchars(
                                    $item["start_date"]
                                );
                                ?>

                            </p>


                            <p>

                                Expected Return Date:

                                <?php
                                echo htmlspecialchars(
                                    $item["expected_return_date"]
                                );
                                ?>

                            </p>


                            <p>

                                Rental Days:

                                <?php
                                echo (int)$item["rental_days"];
                                ?>

                            </p>


                            <p>

                                Quantity:

                                <?php
                                echo (int)$item["quantity"];
                                ?>

                            </p>


                            <p class="item-price">

                                Rs.

                                <?php

                                echo number_format(
                                    $item["item_total"],
                                    2
                                );

                                ?>

                            </p>


                        </div>

                    </div>


                <?php endforeach; ?>


                <!-- =========================
                     TOTAL
                ========================== -->

                <div class="total-row">

                    <span>
                        Total
                    </span>


                    <span>

                        Rs.

                        <?php

                        echo number_format(
                            $total_amount,
                            2
                        );

                        ?>

                    </span>

                </div>


                <!-- =========================
                     CONFIRM BUTTON
                ========================== -->

                <button
                    type="submit"
                    class="place-btn"
                >
                    Confirm Rental Order
                </button>


            </div>


        </div>


    </form>


<?php endif; ?>


</div>


</body>

</html>