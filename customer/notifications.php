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

if ($_SESSION["role"] !== "customer") {
    header("Location: ../account.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];


/* =========================
   Mark All As Read
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["mark_all_read"])) {

        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ?
              AND is_read = 0
        ");

        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        header("Location: notifications.php");
        exit;
    }


    /* =========================
       Mark Single Notification Read
    ========================= */

    if (isset($_POST["notification_id"])) {

        $notification_id = (int)$_POST["notification_id"];

        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE notification_id = ?
              AND user_id = ?
        ");

        $stmt->bind_param(
            "ii",
            $notification_id,
            $user_id
        );

        $stmt->execute();
        $stmt->close();

        header("Location: notifications.php");
        exit;
    }
}


/* =========================
   Get Notifications
========================= */

$sql = "
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
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();


/* =========================
   Unread Count
========================= */

$countStmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
");

$countStmt->bind_param(
    "i",
    $user_id
);

$countStmt->execute();

$countResult = $countStmt->get_result();

$countData = $countResult->fetch_assoc();

$unreadCount = (int)($countData["unread_count"] ?? 0);

$countStmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Notifications - DRESORA</title>


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

            display: flex;

            align-items: center;

            justify-content: space-between;

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
           Notification Badge
        ========================= */

        .notification-badge {

            min-width: 22px;

            height: 22px;

            padding: 2px 7px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #a66b9b;

            color: white;

            border-radius: 50%;

            font-size: 11px;

            font-weight: bold;
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


        .back-btn {

            text-decoration: none;

            background: #8b5a83;

            color: white;

            padding: 9px 16px;

            border-radius: 7px;

            font-size: 13px;
        }


        .back-btn:hover {

            background: #6f4569;
        }


        /* =========================
           Content
        ========================= */

        .content {

            padding: 35px;
        }


        .notification-container {

            max-width: 850px;

            margin: 0 auto;
        }


        .notification-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;
        }


        .notification-header h1 {

            font-size: 25px;

            color: #5d405c;
        }


        .notification-header p {

            margin-top: 6px;

            color: #777;

            font-size: 13px;
        }


        /* =========================
           Mark All Button
        ========================= */

        .mark-all-form {

            margin: 0;
        }


        .mark-all-btn {

            border: none;

            background: #8b5a83;

            color: white;

            padding: 10px 15px;

            border-radius: 7px;

            cursor: pointer;

            font-size: 12px;

            font-weight: 600;
        }


        .mark-all-btn:hover {

            background: #6f4569;
        }


        /* =========================
           Notification Card
        ========================= */

        .notification-list {

            display: flex;

            flex-direction: column;

            gap: 12px;
        }


        .notification-card {

            background: white;

            border-radius: 12px;

            padding: 20px;

            box-shadow:
                0 4px 15px rgba(93, 64, 92, 0.08);

            border-left: 4px solid transparent;

            transition: 0.3s;
        }


        .notification-card:hover {

            transform: translateY(-1px);

            box-shadow:
                0 6px 18px rgba(93, 64, 92, 0.12);
        }


        /* Unread */

        .notification-card.unread {

            background: #fff6fa;

            border-left-color: #a66b9b;
        }


        .notification-top {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 15px;

            margin-bottom: 8px;
        }


        .notification-title {

            display: flex;

            align-items: center;

            gap: 8px;
        }


        .notification-title h3 {

            color: #5d405c;

            font-size: 16px;
        }


        .unread-dot {

            width: 8px;

            height: 8px;

            border-radius: 50%;

            background: #a66b9b;

            display: inline-block;
        }


        .notification-date {

            color: #999;

            font-size: 11px;

            white-space: nowrap;
        }


        .notification-message {

            color: #666;

            font-size: 13px;

            line-height: 1.6;

            margin-bottom: 12px;
        }


        /* =========================
           Notification Type
        ========================= */

        .notification-type {

            display: inline-block;

            padding: 5px 9px;

            border-radius: 15px;

            font-size: 10px;

            font-weight: bold;

            text-transform: uppercase;
        }


        .notification-type.order_confirmed {

            background: #e7f4ff;

            color: #2876a8;
        }


        .notification-type.order_cancelled {

            background: #fdecec;

            color: #b33a3a;
        }


        .notification-type.default {

            background: #f1eaf0;

            color: #6f4569;
        }


        /* =========================
           Read Button
        ========================= */

        .notification-footer {

            display: flex;

            justify-content: flex-end;

            margin-top: 5px;
        }


        .read-btn {

            border: none;

            background: transparent;

            color: #8b5a83;

            cursor: pointer;

            font-size: 11px;

            font-weight: 600;
        }


        .read-btn:hover {

            color: #5d405c;

            text-decoration: underline;
        }


        /* =========================
           Empty State
        ========================= */

        .empty-box {

            background: white;

            border-radius: 12px;

            padding: 55px 30px;

            text-align: center;

            box-shadow:
                0 4px 15px rgba(93, 64, 92, 0.08);
        }


        .empty-icon {

            font-size: 45px;

            margin-bottom: 15px;
        }


        .empty-box h3 {

            color: #5d405c;

            margin-bottom: 8px;
        }


        .empty-box p {

            color: #888;

            font-size: 13px;
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


            .notification-header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;
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

                padding: 20px;
            }


            .notification-top {

                flex-direction: column;

                gap: 5px;
            }


            .notification-date {

                white-space: normal;
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

                <span>👤 My Profile</span>

            </a>

        </li>


        <li>

            <a href="../cart.php">

                <span>🛒 My Cart</span>

            </a>

        </li>


        <li>

            <a href="rental-requests.php">

                <span>📋 Rental Requests</span>

            </a>

        </li>


        <li>

            <a href="my-orders.php">

                <span>📦 My Orders</span>

            </a>

        </li>


        <li>

            <a href="wishlist.php">

                <span>❤️ Wishlist</span>

            </a>

        </li>


        <li>

            <a href="notifications.php" class="active">

                <span>🔔 Notifications</span>

                <?php if ($unreadCount > 0): ?>

                    <span class="notification-badge">

                        <?php echo $unreadCount; ?>

                    </span>

                <?php endif; ?>

            </a>

        </li>


        <li>

            <a href="settings.php">

                <span>⚙️ Change Password</span>

            </a>

        </li>


        <li>

            <a
                href="#"
                onclick="openLogoutModal(); return false;"
            >

                <span>🚪 Logout</span>

            </a>

        </li>

    </ul>

</div>


<!-- =========================
     Main
========================= -->

<div class="main">


    <!-- Topbar -->

    <div class="topbar">

        <h2>Notifications</h2>


        <a
            href="profile.php"
            class="back-btn"
        >
            ← My Profile
        </a>

    </div>


    <!-- Content -->

    <div class="content">

        <div class="notification-container">


            <!-- Header -->

            <div class="notification-header">

                <div>

                    <h1>Notifications</h1>

                    <p>

                        <?php if ($unreadCount > 0): ?>

                            You have
                            <strong>
                                <?php echo $unreadCount; ?>
                            </strong>
                            unread notification<?php
                            echo $unreadCount > 1 ? "s" : "";
                            ?>.

                        <?php else: ?>

                            You have no unread notifications.

                        <?php endif; ?>

                    </p>

                </div>


                <?php if ($unreadCount > 0): ?>

                    <form
                        method="POST"
                        class="mark-all-form"
                    >

                        <input
                            type="hidden"
                            name="mark_all_read"
                            value="1"
                        >

                        <button
                            type="submit"
                            class="mark-all-btn"
                        >
                            ✓ Mark All as Read
                        </button>

                    </form>

                <?php endif; ?>

            </div>


            <!-- Notification List -->

            <div class="notification-list">


                <?php if ($result && $result->num_rows > 0): ?>


                    <?php while ($notification = $result->fetch_assoc()): ?>


                        <?php

                        $isUnread =
                            (int)$notification["is_read"] === 0;

                        $type =
                            strtolower(
                                trim(
                                    $notification["notification_type"]
                                    ?? ""
                                )
                            );

                        ?>


                        <div
                            class="notification-card
                            <?php echo $isUnread ? "unread" : ""; ?>"
                        >


                            <div class="notification-top">


                                <div class="notification-title">

                                    <?php if ($isUnread): ?>

                                        <span
                                            class="unread-dot"
                                        ></span>

                                    <?php endif; ?>


                                    <h3>

                                        <?php
                                        echo htmlspecialchars(
                                            $notification["title"]
                                        );
                                        ?>

                                    </h3>

                                </div>


                                <span class="notification-date">

                                    <?php

                                    echo date(
                                        "M d, Y h:i A",
                                        strtotime(
                                            $notification["created_at"]
                                        )
                                    );

                                    ?>

                                </span>

                            </div>


                            <p class="notification-message">

                                <?php

                                echo nl2br(
                                    htmlspecialchars(
                                        $notification["message"]
                                    )
                                );

                                ?>

                            </p>


                            <span
                                class="notification-type
                                <?php
                                echo in_array(
                                    $type,
                                    [
                                        "order_confirmed",
                                        "order_cancelled"
                                    ],
                                    true
                                )
                                ? htmlspecialchars($type)
                                : "default";
                                ?>"
                            >

                                <?php

                                if ($type === "order_confirmed") {

                                    echo "Order Confirmed";

                                } elseif ($type === "order_cancelled") {

                                    echo "Order Cancelled";

                                } else {

                                    echo htmlspecialchars(
                                        $type !== ""
                                        ? $type
                                        : "Notification"
                                    );
                                }

                                ?>

                            </span>


                            <?php if ($isUnread): ?>

                                <div class="notification-footer">

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="notification_id"
                                            value="<?php
                                            echo (int)
                                                $notification[
                                                    "notification_id"
                                                ];
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="read-btn"
                                        >
                                            Mark as read
                                        </button>

                                    </form>

                                </div>

                            <?php endif; ?>


                        </div>


                    <?php endwhile; ?>


                <?php else: ?>


                    <div class="empty-box">

                        <div class="empty-icon">
                            🔔
                        </div>

                        <h3>
                            No Notifications
                        </h3>

                        <p>
                            You don't have any notifications yet.
                        </p>

                    </div>


                <?php endif; ?>


            </div>

        </div>

    </div>

</div>


<!-- =========================
     Logout Modal
========================= -->

<div
    id="logoutModal"
    class="logout-modal"
>

    <div class="logout-box">

        <h3>Logout</h3>

        <p>
            Are you sure you want to logout?
        </p>

        <div class="logout-buttons">

            <button
                onclick="closeLogoutModal()"
                class="cancel-btn"
            >
                Cancel
            </button>


            <a
                href="logout.php"
                class="confirm-logout-btn"
            >
                Logout
            </a>

        </div>

    </div>

</div>


<style>

/* =========================
   Logout Modal
========================= */

.logout-modal {

    display: none;

    position: fixed;

    top: 0;
    left: 0;

    width: 100%;
    height: 100%;

    background: rgba(0, 0, 0, 0.5);

    justify-content: center;

    align-items: center;

    z-index: 9999;
}


.logout-box {

    background: white;

    width: 380px;

    padding: 30px;

    border-radius: 15px;

    text-align: center;

    box-shadow:
        0 8px 30px rgba(0, 0, 0, 0.2);
}


.logout-box h3 {

    color: #5d405c;

    font-size: 22px;

    margin-bottom: 12px;
}


.logout-box p {

    color: #666;

    font-size: 14px;

    margin-bottom: 25px;
}


.logout-buttons {

    display: flex;

    justify-content: center;

    gap: 12px;
}


.cancel-btn,
.confirm-logout-btn {

    padding: 11px 25px;

    border-radius: 8px;

    font-size: 14px;

    cursor: pointer;

    text-decoration: none;

    border: none;
}


.cancel-btn {

    background: #eeeeee;

    color: #555;
}


.cancel-btn:hover {

    background: #dddddd;
}


.confirm-logout-btn {

    background: #8b5a83;

    color: white;
}


.confirm-logout-btn:hover {

    background: #6f4569;
}

</style>


<script>

function openLogoutModal() {

    document.getElementById(
        "logoutModal"
    ).style.display = "flex";
}


function closeLogoutModal() {

    document.getElementById(
        "logoutModal"
    ).style.display = "none";
}

</script>


</body>

</html>