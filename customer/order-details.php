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


/* =========================
   Check Order ID
========================= */

if (
    !isset($_GET["order_id"]) ||
    !is_numeric($_GET["order_id"])
) {

    header("Location: my-orders.php");

    exit;
}


$order_id = (int)$_GET["order_id"];

$user_id = (int)$_SESSION["user_id"];


/* =========================
   Get Order Details
========================= */

$order_sql = "SELECT
                order_id,
                user_id,
                total_amount,
                payment_method,
                status,
                order_date
              FROM orders
              WHERE order_id = ?
              AND user_id = ?";


$order_stmt = $conn->prepare($order_sql);


if (!$order_stmt) {

    die("Database Error: " . $conn->error);
}


$order_stmt->bind_param(
    "ii",
    $order_id,
    $user_id
);


$order_stmt->execute();


$order_result = $order_stmt->get_result();


if ($order_result->num_rows === 0) {

    $order_stmt->close();

    header("Location: my-orders.php");

    exit;
}


$order = $order_result->fetch_assoc();


$order_stmt->close();


/* =========================
   Get Order Items
========================= */

$items_sql = "SELECT
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

              ORDER BY oi.order_item_id ASC";


$items_stmt = $conn->prepare($items_sql);


if (!$items_stmt) {

    die("Database Error: " . $conn->error);
}


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


/* =========================
   Status Class
========================= */

$status = strtolower(
    $order["status"]
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

    <title>Order Details - DRESORA</title>


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


        .order-container {

            max-width: 1000px;

            margin: 0 auto;
        }


        /* =========================
           Back Button
        ========================= */

        .back-btn {

            display: inline-block;

            text-decoration: none;

            color: #8b5a83;

            font-size: 14px;

            font-weight: 600;

            margin-bottom: 20px;
        }


        .back-btn:hover {

            color: #5d405c;
        }


        /* =========================
           Card
        ========================= */

        .card {

            background: white;

            border-radius: 15px;

            padding: 30px;

            margin-bottom: 25px;

            box-shadow:
                0 5px 20px
                rgba(93, 64, 92, 0.08);
        }


        /* =========================
           Order Header
        ========================= */

        .order-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;

            padding-bottom: 20px;

            border-bottom: 1px solid #eee;
        }


        .order-header h1 {

            font-size: 24px;

            color: #5d405c;
        }


        .order-date {

            color: #777;

            font-size: 13px;

            margin-top: 6px;
        }


        /* =========================
           Status
        ========================= */

        .status {

            display: inline-block;

            padding: 7px 14px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;

            text-transform: capitalize;
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


        .status.cancelled,
        .status.rejected {

            background: #fdecec;

            color: #b33a3a;
        }


        /* =========================
           Order Information
        ========================= */

        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;
        }


        .info-box {

            background: #fffafc;

            padding: 18px;

            border-radius: 10px;
        }


        .info-box label {

            display: block;

            font-size: 12px;

            color: #888;

            margin-bottom: 6px;
        }


        .info-box strong {

            font-size: 15px;

            color: #5d405c;
        }


        /* =========================
           Items Title
        ========================= */

        .section-title {

            font-size: 20px;

            color: #5d405c;

            margin-bottom: 20px;
        }


        /* =========================
           Product Item
        ========================= */

        .item {

            display: flex;

            gap: 20px;

            padding: 20px 0;

            border-bottom: 1px solid #eee;
        }


        .item:last-child {

            border-bottom: none;

            padding-bottom: 0;
        }


        .item-image {

            width: 110px;

            height: 130px;

            border-radius: 10px;

            overflow: hidden;

            background: #f7eef5;

            flex-shrink: 0;
        }


        .item-image img {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        .no-image {

            width: 100%;

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #aaa;

            font-size: 13px;
        }


        .item-details {

            flex: 1;
        }


        .item-details h3 {

            color: #5d405c;

            font-size: 17px;

            margin-bottom: 8px;
        }


        .item-details p {

            color: #777;

            font-size: 13px;

            margin-bottom: 7px;

            line-height: 1.5;
        }


        .item-details p strong {

            color: #5d405c;
        }


        /* =========================
           Rental Information
        ========================= */

        .rental-info {

            margin-top: 12px;

            padding: 14px 16px;

            background: #fffafc;

            border-left: 4px solid #8b5a83;

            border-radius: 8px;
        }


        .rental-info-title {

            font-size: 14px;

            font-weight: bold;

            color: #8b5a83;

            margin-bottom: 9px;
        }


        .rental-info p {

            margin-bottom: 5px;

            font-size: 13px;
        }


        .rental-info p:last-child {

            margin-bottom: 0;
        }


        .item-price {

            font-weight: bold;

            color: #8b5a83;

            font-size: 16px;

            margin-top: 12px;
        }


        /* =========================
           Total
        ========================= */

        .total-section {

            margin-top: 25px;

            padding-top: 20px;

            border-top: 2px solid #eadfea;

            display: flex;

            justify-content: flex-end;

            align-items: center;

            gap: 20px;
        }


        .total-label {

            font-size: 16px;

            color: #777;
        }


        .total-amount {

            font-size: 23px;

            font-weight: bold;

            color: #5d405c;
        }


        /* =========================
           Empty Items
        ========================= */

        .empty-items {

            text-align: center;

            padding: 30px;

            color: #777;
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


            .info-grid {

                grid-template-columns: 1fr;
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


            .card {

                padding: 20px;
            }


            .order-header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;
            }


            .item {

                flex-direction: column;
            }


            .item-image {

                width: 100px;

                height: 120px;
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

            <a
                href="my-orders.php"
                class="active"
            >

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
            Order Details
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

        <div class="order-container">


            <!-- Back -->

            <a
                href="my-orders.php"
                class="back-btn"
            >

                ← Back to My Orders

            </a>


            <!-- =========================
                 Order Information
            ========================= -->

            <div class="card">


                <div class="order-header">


                    <div>

                        <h1>

                            Order #

                            <?php

                            echo htmlspecialchars(
                                $order["order_id"]
                            );

                            ?>

                        </h1>


                        <div class="order-date">

                            Ordered on

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

                                echo "Not available";
                            }

                            ?>

                        </div>

                    </div>


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


                </div>


                <div class="info-grid">


                    <div class="info-box">

                        <label>
                            Order ID
                        </label>


                        <strong>

                            #

                            <?php

                            echo htmlspecialchars(
                                $order["order_id"]
                            );

                            ?>

                        </strong>

                    </div>


                    <div class="info-box">

                        <label>
                            Payment Method
                        </label>


                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $order["payment_method"]
                            );

                            ?>

                        </strong>

                    </div>


                    <div class="info-box">

                        <label>
                            Order Status
                        </label>


                        <strong>

                            <?php

                            echo ucfirst(
                                htmlspecialchars(
                                    $order["status"]
                                )
                            );

                            ?>

                        </strong>

                    </div>


                </div>

            </div>


            <!-- =========================
                 Ordered Items
            ========================= -->

            <div class="card">


                <h2 class="section-title">

                    Ordered Dresses

                </h2>


                <?php if (count($items) > 0): ?>


                    <?php foreach ($items as $item): ?>


                        <div class="item">


                            <!-- =========================
                                 Image
                            ========================== -->

                            <div class="item-image">

                                <?php

                                $firstImage = "";

                                if (
                                    !empty(
                                        $item["image_url"]
                                    )
                                ) {

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
                                    >

                                <?php else: ?>

                                    <div class="no-image">

                                        No Image

                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- =========================
                                 Details
                            ========================== -->

                            <div class="item-details">


                                <h3>

                                    <?php

                                    echo htmlspecialchars(
                                        $item["dress_name"]
                                    );

                                    ?>

                                </h3>


                                <?php if (
                                    !empty(
                                        $item["colour"]
                                    )
                                ): ?>


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


                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $item["size"]
                                    )
                                ): ?>


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


                                <?php endif; ?>


                                <p>

                                    <strong>
                                        Quantity:
                                    </strong>

                                    <?php

                                    echo (int)
                                        $item["quantity"];

                                    ?>

                                </p>


                                <!-- =========================
                                     Rental Information
                                ========================== -->

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

                                        $start_date_display =
                                            "Not available";


                                        if (
                                            !empty(
                                                $item["start_date"]
                                            ) &&
                                            $item["start_date"] !==
                                                "0000-00-00"
                                        ) {

                                            $start_obj =
                                                DateTime::createFromFormat(
                                                    "Y-m-d",
                                                    $item["start_date"],
                                                    new DateTimeZone(
                                                        "Asia/Colombo"
                                                    )
                                                );


                                            if (
                                                $start_obj !== false
                                            ) {

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
                                        =================================
                                        EXPECTED RETURN DATE FIX

                                        If database has a valid date:
                                            use that date.

                                        If database has:
                                            0000-00-00
                                        or invalid/missing date:

                                            Start Date + 5 days

                                        This prevents:
                                            Nov 30, -0001
                                        =================================
                                        */

                                        $return_date_display =
                                            "Not available";


                                        /* =========================
                                           USE SAVED RETURN DATE
                                        ========================= */

                                        if (
                                            !empty(
                                                $item[
                                                    "expected_return_date"
                                                ]
                                            ) &&
                                            $item[
                                                "expected_return_date"
                                            ] !== "0000-00-00"
                                        ) {

                                            $return_obj =
                                                DateTime::createFromFormat(
                                                    "Y-m-d",
                                                    $item[
                                                        "expected_return_date"
                                                    ],
                                                    new DateTimeZone(
                                                        "Asia/Colombo"
                                                    )
                                                );


                                            if (
                                                $return_obj !== false
                                            ) {

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
                                            $return_date_display ===
                                                "Not available" &&
                                            !empty(
                                                $item["start_date"]
                                            ) &&
                                            $item["start_date"] !==
                                                "0000-00-00"
                                        ) {

                                            $start_obj =
                                                DateTime::createFromFormat(
                                                    "Y-m-d",
                                                    $item["start_date"],
                                                    new DateTimeZone(
                                                        "Asia/Colombo"
                                                    )
                                                );


                                            if (
                                                $start_obj !== false
                                            ) {

                                                $start_obj->modify(
                                                    "+5 days"
                                                );


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
                                                $item[
                                                    "rental_days"
                                                ]
                                            )
                                        ) {

                                            echo (int)
                                                $item[
                                                    "rental_days"
                                                ];

                                        } else {

                                            echo "5";
                                        }

                                        ?>

                                        days

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
                                     Rental Price
                                ========================== -->

                                <div class="item-price">

                                    Rs.

                                    <?php

                                    echo number_format(
                                        (float)$item[
                                            "rental_price"
                                        ],
                                        2
                                    );

                                    ?>

                                    ×

                                    <?php

                                    echo (int)
                                        $item[
                                            "quantity"
                                        ];

                                    ?>

                                </div>


                            </div>


                        </div>


                    <?php endforeach; ?>


                    <!-- =========================
                         Total
                    ========================== -->

                    <div class="total-section">


                        <span class="total-label">

                            Total Amount

                        </span>


                        <span class="total-amount">

                            Rs.

                            <?php

                            echo number_format(
                                (float)$order[
                                    "total_amount"
                                ],
                                2
                            );

                            ?>

                        </span>


                    </div>


                <?php else: ?>


                    <div class="empty-items">

                        No items found for this order.

                    </div>


                <?php endif; ?>


            </div>


        </div>

    </div>

</div>


</body>

</html>