<?php
session_start();
require_once "../config/database.php";

/* =========================
   ADMIN LOGIN CHECK
========================= */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../account.php");
    exit;
}

$admin_id = (int) $_SESSION["user_id"];


/* =========================
   MARK ALL AS READ
========================= */
if (isset($_POST["mark_all_read"])) {

    $sql = "
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->close();

    header("Location: notifications.php");
    exit;
}


/* =========================
   MARK SINGLE NOTIFICATION AS READ
========================= */
if (isset($_POST["mark_read"])) {

    $notification_id = (int) $_POST["notification_id"];

    $sql = "
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ?
        AND user_id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $admin_id);
    $stmt->execute();
    $stmt->close();

    header("Location: notifications.php");
    exit;
}


/* =========================
   GET NOTIFICATIONS
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
$stmt->bind_param("i", $admin_id);
$stmt->execute();

$result = $stmt->get_result();

$notifications = [];

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

$stmt->close();


/* =========================
   UNREAD COUNT
========================= */
$unread_count = 0;

foreach ($notifications as $notification) {

    if ((int)$notification["is_read"] === 0) {
        $unread_count++;
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

    <title>Dresora - Admin Notifications</title>


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #fffafc;

            color: #5d405c;

        }


        /* =========================
           HEADER
        ========================= */

        .header {

            height: 75px;

            background: white;

            border-bottom:
                1px solid #eadce8;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 45px;

        }


        .logo {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .logo-icon {

            width: 42px;
            height: 42px;

            background: #8b5a83;

            color: white;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

        }


        .logo-text h2 {

            font-size: 20px;

            letter-spacing: 1px;

            color: #5d405c;

        }


        .logo-text p {

            font-size: 11px;

            color: #a66b9b;

            margin-top: 2px;

        }


        .back-btn {

            text-decoration: none;

            color: #8b5a83;

            border: 1px solid #d9bfd5;

            padding: 9px 16px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            transition: 0.2s;

        }


        .back-btn:hover {

            background: #8b5a83;

            color: white;

        }


        /* =========================
           MAIN
        ========================= */

        .main {

            max-width: 1000px;

            margin: auto;

            padding: 45px 25px;

        }


        /* =========================
           PAGE TITLE
        ========================= */

        .page-top {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 30px;

        }


        .page-title h1 {

            font-size: 27px;

            color: #5d405c;

            margin-bottom: 7px;

        }


        .page-title p {

            font-size: 13px;

            color: #93828f;

        }


        .unread-count {

            display: inline-block;

            margin-top: 10px;

            padding: 5px 11px;

            border-radius: 20px;

            background: #f7e8f3;

            color: #8b5a83;

            font-size: 11px;

            font-weight: bold;

        }


        .mark-all-btn {

            border: none;

            background: #8b5a83;

            color: white;

            padding: 10px 16px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.2s;

        }


        .mark-all-btn:hover {

            background: #70466a;

        }


        /* =========================
           NOTIFICATION LIST
        ========================= */

        .notification-list {

            display: flex;

            flex-direction: column;

            gap: 13px;

        }


        .notification-card {

            background: white;

            border: 1px solid #eadce8;

            border-radius: 16px;

            padding: 20px 22px;

            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 20px;

            transition: 0.2s;

        }


        .notification-card:hover {

            box-shadow:
                0 8px 25px
                rgba(93, 64, 92, 0.08);

            transform: translateY(-2px);

        }


        .notification-card.unread {

            background: #fff7fc;

            border-left:
                4px solid #8b5a83;

        }


        .notification-content {

            display: flex;

            gap: 15px;

            flex: 1;

        }


        .notification-icon {

            width: 45px;

            height: 45px;

            flex-shrink: 0;

            border-radius: 13px;

            background: #f7e8f3;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;

        }


        .notification-details {

            flex: 1;

        }


        .notification-title {

            font-size: 14px;

            font-weight: bold;

            color: #5d405c;

            margin-bottom: 6px;

        }


        .notification-message {

            font-size: 13px;

            color: #756873;

            line-height: 1.6;

        }


        .notification-time {

            margin-top: 8px;

            font-size: 10px;

            color: #a596a2;

        }


        /* =========================
           READ BUTTON
        ========================= */

        .read-form {

            flex-shrink: 0;

        }


        .read-btn {

            border: 1px solid #d9bfd5;

            background: white;

            color: #8b5a83;

            padding: 7px 11px;

            border-radius: 15px;

            font-size: 10px;

            cursor: pointer;

        }


        .read-btn:hover {

            background: #8b5a83;

            color: white;

        }


        .read-label {

            font-size: 10px;

            color: #aaa;

            padding: 7px 11px;

        }


        /* =========================
           EMPTY
        ========================= */

        .empty-box {

            background: white;

            border: 1px solid #eadce8;

            border-radius: 18px;

            padding: 70px 20px;

            text-align: center;

        }


        .empty-icon {

            font-size: 42px;

            margin-bottom: 15px;

        }


        .empty-box h3 {

            font-size: 17px;

            color: #5d405c;

            margin-bottom: 7px;

        }


        .empty-box p {

            font-size: 12px;

            color: #9b8b97;

        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 650px) {

            .header {

                padding: 0 20px;

            }

            .logo-text {

                display: none;

            }

            .main {

                padding: 30px 15px;

            }

            .page-top {

                align-items: flex-start;

                flex-direction: column;

                gap: 18px;

            }

            .notification-card {

                flex-direction: column;

            }

            .read-form {

                margin-left: 60px;

            }

        }

    </style>

</head>


<body>


<!-- =========================
     HEADER
========================= -->

<header class="header">

    <div class="logo">

        <div class="logo-icon">
            👗
        </div>

        <div class="logo-text">

            <h2>DRESORA</h2>

            <p>Admin Notifications</p>

        </div>

    </div>


    <a
        href="dashboard.php"
        class="back-btn"
    >
        ← Dashboard
    </a>

</header>



<!-- =========================
     MAIN
========================= -->

<main class="main">


    <div class="page-top">

        <div class="page-title">

            <h1>
                Notifications
            </h1>

            <p>
                Stay updated with important activities
                in your Dresora system.
            </p>


            <span class="unread-count">

                <?php echo $unread_count; ?>

                unread notification<?php
                    echo ($unread_count == 1) ? "" : "s";
                ?>

            </span>

        </div>


        <?php if ($unread_count > 0): ?>

            <form method="POST">

                <button
                    type="submit"
                    name="mark_all_read"
                    class="mark-all-btn"
                >
                    ✓ Mark all as read
                </button>

            </form>

        <?php endif; ?>

    </div>



    <!-- =========================
         NOTIFICATIONS
    ========================= -->

    <?php if (count($notifications) > 0): ?>

        <div class="notification-list">

            <?php foreach ($notifications as $notification): ?>

                <div
                    class="notification-card
                    <?php
                        echo (
                            (int)$notification["is_read"] === 0
                        )
                        ? "unread"
                        : "";
                    ?>"
                >

                    <div class="notification-content">


                        <div class="notification-icon">

                            <?php

                            if (
                                $notification["notification_type"]
                                === "payment_received"
                            ) {

                                echo "💳";

                            } elseif (
                                $notification["notification_type"]
                                === "payment_success"
                            ) {

                                echo "✓";

                            } else {

                                echo "🔔";

                            }

                            ?>

                        </div>


                        <div class="notification-details">


                            <div class="notification-title">

                                <?php
                                echo htmlspecialchars(
                                    $notification["title"]
                                );
                                ?>

                            </div>


                            <div class="notification-message">

                                <?php
                                echo htmlspecialchars(
                                    $notification["message"]
                                );
                                ?>

                            </div>


                            <div class="notification-time">

                                <?php
                                echo date(
                                    "M d, Y • h:i A",
                                    strtotime(
                                        $notification["created_at"]
                                    )
                                );
                                ?>

                            </div>


                        </div>

                    </div>


                    <?php
                    if ((int)$notification["is_read"] === 0):
                    ?>

                        <form
                            method="POST"
                            class="read-form"
                        >

                            <input
                                type="hidden"
                                name="notification_id"
                                value="<?php
                                    echo (int)
                                        $notification["notification_id"];
                                ?>"
                            >

                            <button
                                type="submit"
                                name="mark_read"
                                class="read-btn"
                            >
                                Mark as read
                            </button>

                        </form>

                    <?php else: ?>

                        <span class="read-label">
                            ✓ Read
                        </span>

                    <?php endif; ?>


                </div>

            <?php endforeach; ?>

        </div>


    <?php else: ?>


        <div class="empty-box">

            <div class="empty-icon">
                🔔
            </div>

            <h3>
                No notifications
            </h3>

            <p>
                You don't have any notifications yet.
            </p>

        </div>


    <?php endif; ?>


</main>


</body>

</html>