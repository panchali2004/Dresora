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

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");

    if ($name === "" || $phone === "" || $email === "" || $address === "") {

        $error_message = "Please fill in all required fields.";

    } else {

        /*
         * Start transaction
         */
        $conn->begin_transaction();

        try {

            /* =========================
               INSERT INTO ORDERS
            ========================= */

            $status = "Pending";

            $order_sql = "INSERT INTO orders
                            (user_id, order_date, status, total_amount)
                          VALUES
                            (?, NOW(), ?, ?)";

            $order_stmt = $conn->prepare($order_sql);

            $order_stmt->bind_param(
                "isd",
                $user_id,
                $status,
                $total_amount
            );

            $order_stmt->execute();

            $order_id = $conn->insert_id;

            $order_stmt->close();


            /* =========================
               INSERT ORDER ITEMS
            ========================= */

            $item_sql = "INSERT INTO order_items
                            (order_id, dress_id, quantity, size, rental_price)
                         VALUES
                            (?, ?, ?, ?, ?)";

            $item_stmt = $conn->prepare($item_sql);

            foreach ($cart_items as $item) {

                $dress_id = (int)$item["product_id"];
                $quantity = (int)$item["quantity"];
                $size = $item["size"];
                $rental_price = (float)$item["price"];

                $item_stmt->bind_param(
                    "iiisd",
                    $order_id,
                    $dress_id,
                    $quantity,
                    $size,
                    $rental_price
                );

                $item_stmt->execute();
            }

            $item_stmt->close();


            /* =========================
               REMOVE SELECTED CART ITEMS
            ========================= */

            $delete_sql = "DELETE FROM cart_items
                           WHERE user_id = ?
                           AND selected = 1";

            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $user_id);
            $delete_stmt->execute();
            $delete_stmt->close();


            /* =========================
               COMMIT
            ========================= */

            $conn->commit();

            $order_success = true;

       } catch (Exception $e) {

    $conn->rollback();

    $error_message = "Order Error: " . $e->getMessage();
}
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Dresora - Checkout</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #fffafc;
            color: #5d405c;
        }

        .navbar {
            background: #8b5a83;
            padding: 18px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: white;
            font-size: 26px;
            font-weight: bold;
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
        }

        .nav-links a:hover {
            text-decoration: underline;
        }

        .container {
            width: 90%;
            max-width: 1100px;
            margin: 40px auto;
        }

        .title {
            text-align: center;
            margin-bottom: 30px;
        }

        .title h1 {
            color: #5d405c;
            margin-bottom: 8px;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 30px;
        }

        .box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(93, 64, 92, 0.10);
        }

        .box h2 {
            margin-bottom: 20px;
            color: #8b5a83;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 90px;
            resize: vertical;
        }

        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .order-item img {
            width: 75px;
            height: 90px;
            object-fit: cover;
            border-radius: 8px;
        }

        .item-info {
            flex: 1;
        }

        .item-info h3 {
            font-size: 16px;
            margin-bottom: 7px;
        }

        .item-info p {
            font-size: 13px;
            margin: 4px 0;
            color: #777;
        }

        .item-price {
            font-weight: bold;
            color: #8b5a83;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #eee;
            font-size: 20px;
            font-weight: bold;
        }

        .place-btn {
            width: 100%;
            margin-top: 25px;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: #8b5a83;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .place-btn:hover {
            background: #5d405c;
        }

        .error {
            background: #ffe5e5;
            color: #b00020;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            text-align: center;
            background: white;
            padding: 50px 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(93, 64, 92, 0.10);
        }

        .success h1 {
            color: #8b5a83;
            margin-bottom: 15px;
        }

        .success p {
            margin: 10px 0;
        }

        .home-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background: #8b5a83;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }

        @media (max-width: 800px) {

            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .nav-links {
                gap: 10px;
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

        <a href="index.php">Home</a>

        <a href="products.php">Shop</a>

        <a href="cart.php">Cart</a>

        <a href="account.php">Account</a>

    </div>

</nav>


<div class="container">


<?php if ($order_success): ?>

    <!-- =========================
         SUCCESS MESSAGE
    ========================= -->

    <div class="success">

        <h1>Rental Order Successful! 🎉</h1>

        <p>
            Thank you for renting with Dresora.
        </p>

        <p>
            Your Order ID is:
            <strong>#<?php echo htmlspecialchars($order_id); ?></strong>
        </p>

        <p>
            Your order status is
            <strong>Pending</strong>.
        </p>

        <a href="index.php" class="home-btn">
            Back to Home
        </a>

    </div>


<?php else: ?>


    <div class="title">

        <h1>Checkout</h1>

        <p>
            Complete your details to confirm your rental.
        </p>

    </div>


    <?php if ($error_message !== ""): ?>

        <div class="error">
            <?php echo htmlspecialchars($error_message); ?>
        </div>

    <?php endif; ?>


    <form method="POST" action="checkout.php">

        <div class="checkout-grid">


            <!-- =========================
                 CUSTOMER DETAILS
            ========================= -->

            <div class="box">

                <h2>Customer Details</h2>


                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?php echo htmlspecialchars($user["name"] ?? ""); ?>"
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
                        value="<?php echo htmlspecialchars($user["phone"] ?? ""); ?>"
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
                        value="<?php echo htmlspecialchars($user["email"] ?? ""); ?>"
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
                    ><?php echo htmlspecialchars($user["shipping_address"] ?? ""); ?></textarea>

                </div>

            </div>


            <!-- =========================
                 ORDER SUMMARY
            ========================= -->

            <div class="box">

                <h2>Order Summary</h2>


                <?php foreach ($cart_items as $item): ?>

                    <div class="order-item">

                        <img
                            src="<?php echo htmlspecialchars($item["image"]); ?>"
                            alt="<?php echo htmlspecialchars($item["product_name"]); ?>"
                        >


                        <div class="item-info">

                            <h3>
                                <?php echo htmlspecialchars($item["product_name"]); ?>
                            </h3>

                            <p>
                                Size:
                                <?php echo htmlspecialchars($item["size"]); ?>
                            </p>

                            <p>
                                Quantity:
                                <?php echo (int)$item["quantity"]; ?>
                            </p>

                            <p>
                                Rental Days:
                                <?php echo (int)$item["rental_days"]; ?>
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


                <div class="total-row">

                    <span>Total</span>

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