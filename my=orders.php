<?php

session_start();

require_once "config/database.php";

/* =========================
   CHECK LOGIN
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: account.php");
    exit;
}

$user_id = $_SESSION["user_id"];

/* =========================
   LOAD USER ORDERS
========================= */

$sql = "SELECT 
            order_id,
            total_amount,
            payment_method,
            status,
            created_at
        FROM orders
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Orders - DRESORA</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffafc;
            color: #444;
        }

        .navbar {
            background: #5d405c;
            padding: 18px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .logo small {
            display: block;
            font-size: 9px;
            letter-spacing: 3px;
            font-weight: normal;
        }

        .nav-links {
            display: flex;
            gap: 25px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .container {
            width: 88%;
            max-width: 1100px;
            margin: 50px auto;
        }

        .title {
            text-align: center;
            margin-bottom: 35px;
        }

        .title h1 {
            color: #5d405c;
            margin-bottom: 8px;
        }

        .title p {
            color: #777;
        }

        .order-card {
            background: white;
            border: 1px solid #eadfea;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 5px 18px rgba(93, 64, 92, 0.08);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .order-id {
            color: #5d405c;
            font-size: 18px;
            font-weight: bold;
        }

        .status {
            padding: 7px 14px;
            border-radius: 20px;
            background: #f8eef7;
            color: #8b5a83;
            font-size: 13px;
            font-weight: bold;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .detail-box {
            background: #fffafc;
            padding: 15px;
            border-radius: 10px;
        }

        .detail-box span {
            display: block;
            font-size: 12px;
            color: #888;
            margin-bottom: 5px;
        }

        .detail-box strong {
            color: #5d405c;
            font-size: 14px;
        }

        .no-orders {
            text-align: center;
            background: white;
            border: 1px solid #eadfea;
            border-radius: 14px;
            padding: 45px 20px;
        }

        .no-orders h2 {
            color: #5d405c;
        }

        .shop-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 22px;
            background: #8b5a83;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }

        @media (max-width: 700px) {

            .navbar {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                gap: 12px;
            }

            .container {
                width: 92%;
            }

            .order-details {
                grid-template-columns: 1fr;
            }

            .order-header {
                align-items: flex-start;
                gap: 10px;
            }

        }

    </style>

</head>

<body>

<header class="navbar">

    <div class="logo">

        DRESORA

        <small>DRESS RENTAL</small>

    </div>

    <ul class="nav-links">

        <li>
            <a href="index.php">Home</a>
        </li>

        <li>
            <a href="products.php">Shop</a>
        </li>

        <li>
            <a href="cart.php">Cart</a>
        </li>

        <li>
            <a href="my-orders.php">My Orders</a>
        </li>

        <li>
            <a href="account.php">Account</a>
        </li>

    </ul>

</header>


<div class="container">

    <div class="title">

        <h1>My Orders</h1>

        <p>
            View your rental order history.
        </p>

    </div>


    <?php if ($result->num_rows > 0): ?>

        <?php while ($order = $result->fetch_assoc()): ?>

            <div class="order-card">

                <div class="order-header">

                    <div class="order-id">

                        Order #<?php echo (int)$order["order_id"]; ?>

                    </div>

                    <div class="status">

                        <?php echo htmlspecialchars($order["status"]); ?>

                    </div>

                </div>


                <div class="order-details">

                    <div class="detail-box">

                        <span>Total Amount</span>

                        <strong>
                            Rs.
                            <?php
                            echo number_format(
                                (float)$order["total_amount"],
                                2
                            );
                            ?>
                        </strong>

                    </div>


                    <div class="detail-box">

                        <span>Payment Method</span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $order["payment_method"]
                            );
                            ?>
                        </strong>

                    </div>


                    <div class="detail-box">

                        <span>Order Date</span>

                        <strong>
                            <?php
                            echo date(
                                "d M Y",
                                strtotime($order["created_at"])
                            );
                            ?>
                        </strong>

                    </div>

                </div>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="no-orders">

            <h2>No Orders Yet</h2>

            <p>
                You haven't placed any rental orders yet.
            </p>

            <a href="products.php" class="shop-btn">
                Explore Dresses
            </a>

        </div>

    <?php endif; ?>

</div>

</body>

</html>

<?php
$stmt->close();
?>