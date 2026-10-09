<?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../account.php");
    exit;
}

$admin_id = (int)$_SESSION["user_id"];


/* =========================
   DASHBOARD SUMMARY
========================= */

$total_users = 0;
$total_dresses = 0;
$total_orders = 0;
$pending_orders = 0;

// Total registered users
$result = $conn->query("SELECT COUNT(*) AS total FROM users");

if ($result) {
    $total_users = (int)$result->fetch_assoc()["total"];
}

// Total dresses
$result = $conn->query("SELECT COUNT(*) AS total FROM dresses");

if ($result) {
    $total_dresses = (int)$result->fetch_assoc()["total"];
}

// Total orders
$result = $conn->query("SELECT COUNT(*) AS total FROM orders");

if ($result) {
    $total_orders = (int)$result->fetch_assoc()["total"];
}

// Pending orders
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE LOWER(status) = 'pending'
");

if ($result) {
    $pending_orders = (int)$result->fetch_assoc()["total"];
}


/* =========================
   UNREAD NOTIFICATION COUNT
========================= */

$unread_count = 0;

$count_sql = "
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
";

$count_stmt = $conn->prepare($count_sql);

if ($count_stmt) {

    $count_stmt->bind_param("i", $admin_id);
    $count_stmt->execute();

    $count_result = $count_stmt->get_result();

    if ($count_result) {

        $count_data = $count_result->fetch_assoc();

        $unread_count = (int)($count_data["unread_count"] ?? 0);
    }

    $count_stmt->close();
}


/* =========================
   LATEST NOTIFICATIONS
========================= */

$notifications = [];

$notification_sql = "
    SELECT
        notification_id,
        title,
        message,
        notification_type,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
";

$notification_stmt = $conn->prepare($notification_sql);

if ($notification_stmt) {

    $notification_stmt->bind_param("i", $admin_id);
    $notification_stmt->execute();

    $notification_result = $notification_stmt->get_result();

    if ($notification_result) {

        while ($row = $notification_result->fetch_assoc()) {

            $notifications[] = $row;
        }
    }

    $notification_stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>DRESORA Admin Dashboard</title>


    <style>
        
/* =========================
   STORE SUMMARY
========================= */

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 35px;
}

.summary-card {
    background: white;
    padding: 23px;
    border-radius: 13px;
    border: 1px solid #f1eaf0;
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
    transition: 0.2s;
}

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(93,64,92,0.10);
}

.summary-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #f3eaf2;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    margin-bottom: 16px;
}

.summary-number {
    color: #5d405c;
    font-size: 29px;
    font-weight: bold;
    margin-bottom: 7px;
}

.summary-label {
    color: #777;
    font-size: 13px;
}

.summary-card.pending {
    border-left: 4px solid #d99a55;
}

@media (max-width: 900px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 450px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
}

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family: Arial, sans-serif;

            background: #fffafc;

            color: #333;
        }


        /* =========================
           HEADER
        ========================== */

        .header {

            background: #5d405c;

            color: white;

            padding: 18px 35px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            box-shadow:
                0 3px 12px rgba(0,0,0,0.12);

            position: sticky;

            top: 0;

            z-index: 1000;
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .brand-icon {

            width: 42px;

            height: 42px;

            border-radius: 10px;

            background: #8b5a83;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 21px;
        }


        .brand-text h2 {

            margin: 0;

            font-size: 20px;
        }


        .brand-text span {

            font-size: 10px;

            letter-spacing: 2px;

            opacity: 0.75;
        }


        .header-right {

            display: flex;

            align-items: center;

            gap: 14px;
        }


        /* =========================
           NOTIFICATION BUTTON
        ========================== */

        .notification-wrapper {

            position: relative;
        }


        .notification-btn {

            width: 42px;

            height: 42px;

            border: none;

            border-radius: 10px;

            background: rgba(255,255,255,0.12);

            color: white;

            font-size: 20px;

            cursor: pointer;

            display: flex;

            align-items: center;

            justify-content: center;

            transition: 0.2s;
        }


        .notification-btn:hover {

            background: #8b5a83;

            transform: translateY(-1px);
        }


        .notification-badge {

            position: absolute;

            top: -5px;

            right: -5px;

            min-width: 19px;

            height: 19px;

            padding: 2px 5px;

            background: #d96b91;

            color: white;

            border-radius: 50%;

            font-size: 10px;

            font-weight: bold;

            display: flex;

            align-items: center;

            justify-content: center;

            border: 2px solid #5d405c;
        }


        /* =========================
           NOTIFICATION PANEL
        ========================== */

        .notification-panel {

            display: none;

            position: absolute;

            top: 55px;

            right: 0;

            width: 370px;

            background: white;

            border-radius: 14px;

            box-shadow:
                0 12px 35px rgba(0,0,0,0.18);

            overflow: hidden;

            color: #333;

            z-index: 2000;
        }


        .notification-panel.show {

            display: block;

            animation: notificationOpen 0.18s ease;
        }


        @keyframes notificationOpen {

            from {

                opacity: 0;

                transform: translateY(-5px);
            }

            to {

                opacity: 1;

                transform: translateY(0);
            }
        }


        .notification-panel-header {

            padding: 17px 18px;

            border-bottom: 1px solid #eee;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .notification-panel-header h3 {

            margin: 0;

            color: #5d405c;

            font-size: 16px;
        }


        .notification-panel-header span {

            font-size: 11px;

            color: #999;
        }


        .notification-list {

            max-height: 330px;

            overflow-y: auto;
        }


        .notification-item {

            padding: 15px 18px;

            border-bottom: 1px solid #f1f1f1;

            transition: 0.2s;

            cursor: pointer;
        }


        .notification-item:hover {

            background: #fff7fb;
        }


        .notification-item.unread {

            background: #fff6fa;

            border-left: 4px solid #a66b9b;
        }


        .notification-item-title {

            display: flex;

            align-items: center;

            gap: 7px;

            color: #5d405c;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 6px;
        }


        .unread-dot {

            width: 7px;

            height: 7px;

            border-radius: 50%;

            background: #a66b9b;

            flex-shrink: 0;
        }


        .notification-item-message {

            color: #777;

            font-size: 12px;

            line-height: 1.5;

            margin: 0 0 7px;
        }


        .notification-item-date {

            color: #aaa;

            font-size: 10px;
        }


        .notification-panel-footer {

            padding: 12px 15px;

            background: #faf8fa;

            text-align: center;
        }


        .notification-panel-footer a {

            color: #8b5a83;

            text-decoration: none;

            font-size: 12px;

            font-weight: bold;
        }


        .notification-panel-footer a:hover {

            text-decoration: underline;
        }


        .no-notifications {

            padding: 35px 20px;

            text-align: center;

            color: #999;

            font-size: 13px;
        }


        /* =========================
           LOGOUT
        ========================== */

        .logout {

            color: white;

            text-decoration: none;

            background: #8b5a83;

            padding: 10px 18px;

            border-radius: 7px;

            font-size: 13px;

            transition: 0.2s;
        }


        .logout:hover {

            background: #a66b9b;
        }


        /* =========================
           MAIN CONTAINER
        ========================== */

        .container {

            width: 92%;

            max-width: 1200px;

            margin: 35px auto 60px;
        }


        .welcome-box {

            background: white;

            padding: 25px 28px;

            border-radius: 14px;

            box-shadow:
                0 4px 15px rgba(93,64,92,0.07);

            margin-bottom: 35px;

            border-left: 5px solid #a66b9b;
        }


        .welcome-box h1 {

            margin: 0 0 7px;

            color: #5d405c;

            font-size: 25px;
        }


        .welcome-box p {

            margin: 0;

            color: #888;

            font-size: 13px;
        }


        /* =========================
           SECTION
        ========================== */

        .section {

            margin-bottom: 40px;
        }


        .section-title {

            color: #5d405c;

            margin: 0 0 18px;

            font-size: 20px;
        }


        /* =========================
           CARDS
        ========================== */

        .cards {

            display: grid;

            grid-template-columns:
                repeat(auto-fit, minmax(230px, 1fr));

            gap: 20px;
        }


        .card {

            background: white;

            padding: 24px;

            min-height: 165px;

            border-radius: 13px;

            box-shadow:
                0 4px 14px rgba(0,0,0,0.07);

            text-decoration: none;

            color: #333;

            transition: 0.2s;

            border: 1px solid #f1eaf0;
        }


        .card:hover {

            transform: translateY(-4px);

            box-shadow:
                0 8px 20px rgba(93,64,92,0.12);

            border-color: #dcc9d9;
        }


        .card-icon {

            width: 45px;

            height: 45px;

            border-radius: 10px;

            background: #f3eaf2;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 21px;

            margin-bottom: 15px;
        }


        .card h3 {

            color: #5d405c;

            margin: 0 0 8px;

            font-size: 16px;
        }


        .card p {

            color: #777;

            line-height: 1.5;

            font-size: 12px;

            margin: 0 0 15px;
        }


        .card-button {

            display: inline-block;

            background: #8b5a83;

            color: white;

            padding: 8px 13px;

            border-radius: 6px;

            font-size: 11px;
        }


        /* =========================
           WEBSITE CARDS
        ========================== */

        .website-card {

            min-height: 145px;
        }


        /* =========================
           RESPONSIVE
        ========================== */

        @media (max-width: 700px) {

            .header {

                padding: 15px 18px;
            }


            .brand-text h2 {

                font-size: 16px;
            }


            .brand-text span {

                display: none;
            }


            .logout {

                padding: 8px 12px;

                font-size: 11px;
            }


            .notification-panel {

                position: fixed;

                top: 70px;

                left: 15px;

                right: 15px;

                width: auto;
            }


            .container {

                width: 90%;
            }

        }

    </style>

</head>


<body>


<!-- =========================
     HEADER
========================== -->

<div class="header">


    <div class="brand">

        <div class="brand-icon">
            👗
        </div>

        <div class="brand-text">

            <h2>
                DRESORA Admin
            </h2>

            <span>
                DRESS RENTAL MANAGEMENT
            </span>

        </div>

    </div>


    <div class="header-right">


        <!-- =========================
             NOTIFICATION BUTTON
        ========================== -->

        <div class="notification-wrapper">

            <button
                type="button"
                class="notification-btn"
                onclick="toggleNotifications()"
            >

                🔔

                <?php if ($unread_count > 0): ?>

                    <span class="notification-badge">

                        <?php echo $unread_count; ?>

                    </span>

                <?php endif; ?>

            </button>


            <!-- =========================
                 NOTIFICATION PANEL
            ========================== -->

            <div
                id="notificationPanel"
                class="notification-panel"
            >


                <div class="notification-panel-header">

                    <h3>
                        Notifications
                    </h3>

                    <span>

                        <?php

                        echo $unread_count;

                        ?>

                        unread

                    </span>

                </div>


                <div class="notification-list">


                    <?php if (count($notifications) > 0): ?>


                        <?php foreach ($notifications as $notification): ?>


                            <?php

                            $is_unread =
                                (int)$notification["is_read"] === 0;

                            $notification_type =
                                strtolower(
                                    trim(
                                        $notification[
                                            "notification_type"
                                        ] ?? ""
                                    )
                                );

                            ?>


                            <div
                                class="notification-item
                                <?php
                                echo $is_unread
                                    ? "unread"
                                    : "";
                                ?>"
                            >


                                <div
                                    class="notification-item-title"
                                >

                                    <?php if ($is_unread): ?>

                                        <span
                                            class="unread-dot"
                                        ></span>

                                    <?php endif; ?>


                                    <?php

                                    echo htmlspecialchars(
                                        $notification["title"]
                                    );

                                    ?>

                                </div>


                                <p
                                    class="notification-item-message"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $notification["message"]
                                    );

                                    ?>

                                </p>


                                <div
                                    class="notification-item-date"
                                >

                                    <?php

                                    echo date(
                                        "M d, Y h:i A",
                                        strtotime(
                                            $notification[
                                                "created_at"
                                            ]
                                        )
                                    );

                                    ?>

                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <div class="no-notifications">

                            🔔

                            <br><br>

                            No notifications yet.

                        </div>


                    <?php endif; ?>


                </div>


                <div class="notification-panel-footer">

                    <a href="notifications.php">

                        View all notifications →

                    </a>

                </div>


            </div>

        </div>


        <!-- LOGOUT -->

        <a
            href="../customer/logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</div>


<!-- =========================
     MAIN
========================== -->

<div class="container">


    <!-- WELCOME -->

    <div class="welcome-box">

        <h1>
            Welcome, Admin 👋
        </h1>

        <p>
            Manage your DRESORA dress rental website
            from the dashboard.
        </p>

    </div>

    
<!-- =========================
     STORE SUMMARY
========================== -->

<div class="section">

    <h2 class="section-title">
        Store Summary
    </h2>

    <div class="summary-grid">

        <!-- TOTAL USERS -->
        <div class="summary-card">
            <div class="summary-icon">👥</div>

            <div class="summary-number">
                <?php echo number_format($total_users); ?>
            </div>

            <div class="summary-label">
                Total Users
            </div>
        </div>

        <!-- TOTAL DRESSES -->
        <div class="summary-card">
            <div class="summary-icon">👗</div>

            <div class="summary-number">
                <?php echo number_format($total_dresses); ?>
            </div>

            <div class="summary-label">
                Total Dresses
            </div>
        </div>

        <!-- TOTAL ORDERS -->
        <div class="summary-card">
            <div class="summary-icon">📦</div>

            <div class="summary-number">
                <?php echo number_format($total_orders); ?>
            </div>

            <div class="summary-label">
                Total Orders
            </div>
        </div>

        <!-- PENDING ORDERS -->
      
<a href="orders.php?status=pending"
   class="summary-card pending"
   style="text-decoration:none; display:block;">

    <div class="summary-icon">⏳</div>

    <div class="summary-number">
        <?php echo number_format($pending_orders); ?>
    </div>

    <div class="summary-label">
        Pending Orders
    </div>

</a>

    </div>

</div>


    <!-- =========================
         ADMIN MANAGEMENT
    ========================== -->

    <div class="section">

        <h2 class="section-title">
            Admin Management
        </h2>


        <div class="cards">


            <!-- PRODUCTS -->

            <a
                href="dresses.php"
                class="card"
            >

                <div class="card-icon">
                    👗
                </div>

                <h3>
                    Products
                </h3>

                <p>
                    Add, edit and delete dresses
                    available for rental.
                </p>

                <span class="card-button">
                    Manage Dresses
                </span>

            </a>


            <!-- RENTALS -->

            <a
                href="orders.php"
                class="card"
            >

                <div class="card-icon">
                    📦
                </div>

                <h3>
                    Rentals
                </h3>

                <p>
                    Manage customer rental
                    orders and requests.
                </p>

                <span class="card-button">
                    Manage Orders
                </span>

            </a>


        
<!-- USERS -->

<a
    href="users.php"
    class="card"
>

    <div class="card-icon">
        👥
    </div>

    <h3>
        Users
    </h3>

    <p>
        View registered customers
        and user information.
    </p>

    <span class="card-button">
        Manage Users
    </span>

</a>

        </div>

    </div>


    <!-- =========================
         WEBSITE PAGES
    ========================== -->

    <div class="section">

        <h2 class="section-title">
            Website Pages
        </h2>


        <div class="cards">


            <!-- HOME -->

            <a
                href="../index.php"
                class="card website-card"
            >

                <div class="card-icon">
                    🏠
                </div>

                <h3>
                    Home
                </h3>

                <p>
                    View the DRESORA home page.
                </p>

                <span class="card-button">
                    Open
                </span>

            </a>


            <!-- SHOP -->

            <a
                href="../products.php"
                class="card website-card"
            >

                <div class="card-icon">
                    👗
                </div>

                <h3>
                    Shop
                </h3>

                <p>
                    View all dresses available
                    on the website.
                </p>

                <span class="card-button">
                    Open
                </span>

            </a>


            <!-- ABOUT -->

            <a
                href="../about.html"
                class="card website-card"
            >

                <div class="card-icon">
                    ℹ️
                </div>

                <h3>
                    About Us
                </h3>

                <p>
                    View the DRESORA
                    About page.
                </p>

                <span class="card-button">
                    Open
                </span>

            </a>


            <!-- CONTACT -->

            <a
                href="../contact.html"
                class="card website-card"
            >

                <div class="card-icon">
                    📞
                </div>

                <h3>
                    Contact
                </h3>

                <p>
                    View the contact page.
                </p>

                <span class="card-button">
                    Open
                </span>

            </a>


            <!-- ACCOUNT -->

            <a
                href="../account.php"
                class="card website-card"
            >

                <div class="card-icon">
                    👤
                </div>

                <h3>
                    Account
                </h3>

                <p>
                    Open the customer account page.
                </p>

                <span class="card-button">
                    Open
                </span>

            </a>


        </div>

    </div>


</div>


<script>

/* =========================
   NOTIFICATION TOGGLE
========================= */

function toggleNotifications() {

    const panel =
        document.getElementById(
            "notificationPanel"
        );

    panel.classList.toggle("show");
}


/* =========================
   CLOSE WHEN CLICK OUTSIDE
========================= */

document.addEventListener(
    "click",
    function(event) {

        const wrapper =
            document.querySelector(
                ".notification-wrapper"
            );

        const panel =
            document.getElementById(
                "notificationPanel"
            );


        if (
            wrapper &&
            !wrapper.contains(event.target)
        ) {

            panel.classList.remove("show");
        }

    }
);

</script>


</body>

</html>