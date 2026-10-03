<?php
session_start();

require_once "../config/database.php";

/* =========================================================
   LOGIN CHECK
========================================================= */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../account.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];

/* =========================================================
   CUSTOMER CHECK
========================================================= */
if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin") {
    header("Location: ../admin/dashboard.php");
    exit();
}


/* =========================================================
   PROFILE UPDATE
========================================================= */
$profileMessage = "";
$profileMessageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profile"])) {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $shipping_address = trim($_POST["shipping_address"] ?? "");
    $billing_address = trim($_POST["billing_address"] ?? "");

    if ($name === "" || $email === "") {

        $profileMessage = "Name and email are required.";
        $profileMessageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $profileMessage = "Please enter a valid email address.";
        $profileMessageType = "error";

    } else {

        /* Check whether email already belongs to another user */
        $checkStmt = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE email = ?
              AND user_id != ?
            LIMIT 1
        ");

        $checkStmt->bind_param("si", $email, $user_id);
        $checkStmt->execute();

        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {

            $profileMessage = "This email address is already used by another account.";
            $profileMessageType = "error";

        } else {

            $updateStmt = $conn->prepare("
                UPDATE users
                SET
                    name = ?,
                    email = ?,
                    phone = ?,
                    shipping_address = ?,
                    billing_address = ?
                WHERE user_id = ?
            ");

            $updateStmt->bind_param(
                "sssssi",
                $name,
                $email,
                $phone,
                $shipping_address,
                $billing_address,
                $user_id
            );

            if ($updateStmt->execute()) {

                $_SESSION["name"] = $name;
                $_SESSION["email"] = $email;

                $profileMessage = "Profile updated successfully.";
                $profileMessageType = "success";

            } else {

                $profileMessage = "Failed to update profile.";
                $profileMessageType = "error";
            }

            $updateStmt->close();
        }

        $checkStmt->close();
    }
}


/* =========================================================
   LOAD USER DETAILS
========================================================= */
$userStmt = $conn->prepare("
    SELECT
        user_id,
        name,
        email,
        phone,
        shipping_address,
        billing_address,
        role
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$userStmt->bind_param("i", $user_id);
$userStmt->execute();

$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

$userStmt->close();


if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}


/* =========================================================
   NOTIFICATION UNREAD COUNT
========================================================= */
$notificationCount = 0;

$notificationStmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
");

$notificationStmt->bind_param("i", $user_id);
$notificationStmt->execute();

$notificationResult = $notificationStmt->get_result();
$notificationData = $notificationResult->fetch_assoc();

$notificationCount = (int)($notificationData["unread_count"] ?? 0);

$notificationStmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Profile - Dresora</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #fffafc;
            color: #5d405c;
        }

        a {
            text-decoration: none;
            color: inherit;
        }


        /* =====================================================
           HEADER
        ===================================================== */

        .header {
            width: 100%;
            background: white;
            border-bottom: 1px solid #eee;
            padding: 18px 60px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 27px;
            font-weight: bold;
            color: #8b5a83;
            letter-spacing: 2px;
        }

        .logo span {
            display: block;
            font-size: 11px;
            letter-spacing: 3px;
            color: #a66b9b;
            margin-top: 3px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .home-btn {
            padding: 10px 18px;
            border: 1px solid #a66b9b;
            border-radius: 8px;
            color: #8b5a83;
            font-size: 14px;
            transition: 0.3s;
        }

        .home-btn:hover {
            background: #a66b9b;
            color: white;
        }


        /* =====================================================
           MAIN LAYOUT
        ===================================================== */

        .container {
            max-width: 1250px;
            margin: 40px auto;
            padding: 0 25px;

            display: flex;
            gap: 30px;
        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {
            width: 270px;
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(139, 90, 131, 0.08);
            height: fit-content;
        }

        .profile-mini {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
        }

        .profile-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #f4e7f1;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 12px;

            font-size: 32px;
        }

        .profile-mini h3 {
            font-size: 17px;
            color: #5d405c;
            margin-bottom: 5px;
        }

        .profile-mini p {
            font-size: 12px;
            color: #999;
            word-break: break-word;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar li {
            margin-bottom: 5px;
        }

        .sidebar li a {
            padding: 13px 14px;
            border-radius: 9px;

            display: flex;
            align-items: center;

            color: #666;
            font-size: 14px;

            transition: 0.3s;
        }

        .sidebar li a:hover {
            background: #f8edf6;
            color: #8b5a83;
        }

        .sidebar li a.active {
            background: #f4e7f1;
            color: #8b5a83;
            font-weight: bold;
        }


        /* =====================================================
           NOTIFICATION BADGE
        ===================================================== */

        .notification-link {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            gap: 10px;
        }

        .notification-link span:first-child {
            display: flex;
            align-items: center;
        }

        .notification-badge {
            min-width: 22px;
            height: 22px;

            padding: 2px 7px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #a66b9b;
            color: #ffffff;

            border-radius: 50%;

            font-size: 11px;
            font-weight: bold;
            line-height: 1;
        }


        /* =====================================================
           CONTENT
        ===================================================== */

        .content {
            flex: 1;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h1 {
            color: #5d405c;
            font-size: 30px;
            margin-bottom: 7px;
        }

        .page-title p {
            color: #999;
            font-size: 14px;
        }


        /* =====================================================
           MESSAGE
        ===================================================== */

        .message {
            padding: 14px 18px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            background: #eaf7ed;
            color: #287a3e;
            border: 1px solid #c7e8cf;
        }

        .message.error {
            background: #fff0f0;
            color: #b33a3a;
            border: 1px solid #f0caca;
        }


        /* =====================================================
           CARD
        ===================================================== */

        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(139, 90, 131, 0.08);
            margin-bottom: 25px;
        }

        .card-title {
            font-size: 20px;
            color: #8b5a83;
            margin-bottom: 25px;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }


        /* =====================================================
           FORM
        ===================================================== */

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 5px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            color: #5d405c;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;

            border: 1px solid #ddd;
            border-radius: 8px;

            font-family: inherit;
            font-size: 14px;

            outline: none;
            transition: 0.3s;
        }

        .form-group textarea {
            min-height: 90px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #a66b9b;
            box-shadow: 0 0 0 3px rgba(166, 107, 155, 0.08);
        }

        .save-btn {
            margin-top: 22px;

            border: none;
            background: #8b5a83;
            color: white;

            padding: 12px 25px;

            border-radius: 8px;

            cursor: pointer;

            font-size: 14px;
            font-weight: bold;

            transition: 0.3s;
        }

        .save-btn:hover {
            background: #a66b9b;
        }


        /* =====================================================
           LOGOUT MODAL
        ===================================================== */

        .modal-overlay {
            display: none;

            position: fixed;
            inset: 0;

            background: rgba(40, 25, 38, 0.5);

            align-items: center;
            justify-content: center;

            z-index: 9999;
        }

        .modal-overlay.show {
            display: flex;
        }

        .logout-modal {
            width: 90%;
            max-width: 420px;

            background: white;
            border-radius: 15px;

            padding: 30px;

            text-align: center;

            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        .logout-icon {
            width: 60px;
            height: 60px;

            margin: 0 auto 15px;

            border-radius: 50%;

            background: #f4e7f1;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 27px;
        }

        .logout-modal h2 {
            color: #5d405c;
            margin-bottom: 10px;
        }

        .logout-modal p {
            color: #888;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .modal-btn {
            padding: 11px 22px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .cancel-btn {
            background: #eee;
            color: #555;
        }

        .confirm-logout-btn {
            background: #8b5a83;
            color: white;
        }

        .confirm-logout-btn:hover {
            background: #a66b9b;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 850px) {

            .header {
                padding: 15px 25px;
            }

            .container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }
        }

    </style>

</head>

<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="header">

    <a href="../index.php" class="logo">
        DRESORA
        <span>DRESS RENTAL</span>
    </a>

    <div class="header-right">

        <a href="../index.php" class="home-btn">
            🏠 Home
        </a>

    </div>

</header>


<!-- =========================================================
     MAIN
========================================================= -->

<div class="container">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">

        <div class="profile-mini">

            <div class="profile-icon">
                👤
            </div>

            <h3>
                <?php echo htmlspecialchars($user["name"]); ?>
            </h3>

            <p>
                <?php echo htmlspecialchars($user["email"]); ?>
            </p>

        </div>


        <ul>

            <li>
                <a href="profile.php" class="active">
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
                <a href="my-orders.php">
                    📦 My Orders
                </a>
            </li>


            <li>
                <a href="wishlist.php">
                    ❤️ Wishlist
                </a>
            </li>


            <li>

                <a
                    href="notifications.php"
                    class="notification-link"
                >

                    <span>
                        🔔 Notifications
                    </span>

                    <?php if ($notificationCount > 0): ?>

                        <span class="notification-badge">
                            <?php echo $notificationCount; ?>
                        </span>

                    <?php endif; ?>

                </a>

            </li>


            <li>
                <a href="settings.php">
                    ⚙️ Change Password
                </a>
            </li>


            <li>
                <a
                    href="#"
                    onclick="openLogoutModal(); return false;"
                >
                    🚪 Logout
                </a>
            </li>

        </ul>

    </aside>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <main class="content">

        <div class="page-title">

            <h1>
                My Profile
            </h1>

            <p>
                Manage your personal information and account settings.
            </p>

        </div>


        <!-- =================================================
             PROFILE MESSAGE
        ================================================== -->

        <?php if ($profileMessage !== ""): ?>

            <div class="message <?php echo $profileMessageType; ?>">

                <?php echo htmlspecialchars($profileMessage); ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             PERSONAL INFORMATION
        ================================================== -->

        <div class="card">

            <h2 class="card-title">
                Personal Information
            </h2>

            <form
                method="POST"
                action=""
            >

                <div class="form-grid">


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

                        <label for="email">
                            Email Address
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

                        <label for="phone">
                            Phone Number
                        </label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="<?php echo htmlspecialchars($user["phone"] ?? ""); ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Account Type
                        </label>

                        <input
                            type="text"
                            value="<?php echo htmlspecialchars(ucfirst($user["role"] ?? "customer")); ?>"
                            readonly
                        >

                    </div>


                    <div class="form-group full">

                        <label for="shipping_address">
                            Shipping Address
                        </label>

                        <textarea
                            id="shipping_address"
                            name="shipping_address"
                        ><?php echo htmlspecialchars($user["shipping_address"] ?? ""); ?></textarea>

                    </div>


                    <div class="form-group full">

                        <label for="billing_address">
                            Billing Address
                        </label>

                        <textarea
                            id="billing_address"
                            name="billing_address"
                        ><?php echo htmlspecialchars($user["billing_address"] ?? ""); ?></textarea>

                    </div>

                </div>


                <button
                    type="submit"
                    name="update_profile"
                    class="save-btn"
                >
                    Save Changes
                </button>

            </form>

        </div>

    </main>

</div>


<!-- =========================================================
     LOGOUT MODAL
========================================================= -->

<div
    class="modal-overlay"
    id="logoutModal"
>

    <div class="logout-modal">

        <div class="logout-icon">
            🚪
        </div>

        <h2>
            Logout?
        </h2>

        <p>
            Are you sure you want to logout from your account?
        </p>

        <div class="modal-buttons">

            <button
                type="button"
                class="modal-btn cancel-btn"
                onclick="closeLogoutModal()"
            >
                Cancel
            </button>

            <button
                type="button"
                class="modal-btn confirm-logout-btn"
                onclick="confirmLogout()"
            >
                Yes, Logout
            </button>

        </div>

    </div>

</div>


<script>

    /* =========================================================
       LOGOUT MODAL
    ========================================================= */

    function openLogoutModal() {

        document
            .getElementById("logoutModal")
            .classList.add("show");

    }


    function closeLogoutModal() {

        document
            .getElementById("logoutModal")
            .classList.remove("show");

    }


    function confirmLogout() {

        window.location.href = "logout.php";

    }


    /* Close modal when clicking outside */

    document
        .getElementById("logoutModal")
        .addEventListener("click", function(event) {

            if (event.target === this) {

                closeLogoutModal();

            }

        });


    /* Close modal with ESC */

    document.addEventListener("keydown", function(event) {

        if (event.key === "Escape") {

            closeLogoutModal();

        }

    });

</script>


</body>
</html>