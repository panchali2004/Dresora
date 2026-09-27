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

$user_id = $_SESSION["user_id"];

$success = "";
$error = "";

/* =========================
   Update Profile
========================= */

      
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* =========================
       Change Password
    ========================= */

    if (isset($_POST["change_password"])) {

        $current_password = $_POST["current_password"];
        $new_password = $_POST["new_password"];
        $confirm_password = $_POST["confirm_password"];

        if (
            $current_password === "" ||
            $new_password === "" ||
            $confirm_password === ""
        ) {

            $error = "Please fill in all password fields.";

        } elseif ($new_password !== $confirm_password) {

            $error = "New passwords do not match.";

        } elseif (strlen($new_password) < 8) {

            $error = "Password must contain at least 8 characters.";

        } else {

            /* Get current hashed password */

            $sql = "SELECT password
                    FROM users
                    WHERE user_id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();

            $stmt->close();


            if (!$user_data) {

                $error = "User account not found.";

            } elseif (
                !password_verify(
                    $current_password,
                    $user_data["password"]
                )
            ) {

                $error = "Current password is incorrect.";

            } else {

                /* Create new hashed password */

                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );


                /* Update password */

                $sql = "UPDATE users
                        SET password = ?
                        WHERE user_id = ?";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "si",
                    $hashed_password,
                    $user_id
                );


                if ($stmt->execute()) {

                    $success = "Password changed successfully.";

                } else {

                    $error = "Failed to change password.";
                }

                $stmt->close();
            }
        }


    } else {

        /* =========================
           Update Profile
        ========================= */

        $name = trim($_POST["name"]);
        $phone = trim($_POST["phone"]);
        $shipping_address = trim($_POST["shipping_address"]);
        $billing_address = trim($_POST["billing_address"]);

        if ($name === "") {

            $error = "Please enter your name.";

        } else {

            $sql = "UPDATE users
                    SET name = ?,
                        phone = ?,
                        shipping_address = ?,
                        billing_address = ?
                    WHERE user_id = ?";

            $stmt = $conn->prepare($sql);

            if ($stmt) {

                $stmt->bind_param(
                    "ssssi",
                    $name,
                    $phone,
                    $shipping_address,
                    $billing_address,
                    $user_id
                );

                if ($stmt->execute()) {

                    $success = "Profile details updated successfully.";

                    $_SESSION["name"] = $name;

                } else {

                    $error = "Database Error: " . $stmt->error;
                }

                $stmt->close();

            } else {

                $error = "Database error.";
            }
        }
    }
}
/* =========================
   Get User Details
========================= */

$sql = "SELECT
            user_id,
            name,
            email,
            phone,
            shipping_address,
            billing_address,
            role,
            created_at
        FROM users
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../account.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile - DRESORA</title>

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
           Main Content
        ========================= */

        .main {
            margin-left: 240px;
            min-height: 100vh;
        }

        /* =========================
           Top Navigation
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

        .profile-container {
            max-width: 850px;
            margin: 0 auto;
        }

        .profile-card {
            background: white;

            border-radius: 15px;

            padding: 35px;

            box-shadow: 0 5px 20px rgba(93, 64, 92, 0.08);
        }

        .profile-header {
            margin-bottom: 30px;
        }

        .profile-header h1 {
            font-size: 25px;
            margin-bottom: 8px;
            color: #5d405c;
        }

        .profile-header p {
            color: #777;
            font-size: 14px;
        }

        /* =========================
           Messages
        ========================= */

        .success-message {
            background: #e9f8ef;
            color: #267342;

            padding: 12px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;
        }

        .error-message {
            background: #fdecec;
            color: #b33a3a;

            padding: 12px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;
        }

        /* =========================
           Form
        ========================= */

        .form-row {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;

            margin-bottom: 8px;

            font-size: 14px;

            font-weight: 600;

            color: #5d405c;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;

            padding: 12px 14px;

            border: 1px solid #ddd;

            border-radius: 8px;

            font-size: 14px;

            outline: none;

            transition: 0.3s;

            font-family: Arial, sans-serif;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #a66b9b;

            box-shadow: 0 0 0 3px rgba(166, 107, 155, 0.1);
        }

        .form-group input[readonly] {
            background: #f7f7f7;
            color: #777;
            cursor: not-allowed;
        }

        .form-group textarea {
            min-height: 110px;
            resize: vertical;
        }

        .form-note {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }

        /* =========================
           Buttons
        ========================= */

        .button-area {
            margin-top: 10px;

            display: flex;
            justify-content: flex-end;
        }

        .save-btn {
            border: none;

            background: #8b5a83;
            color: white;

            padding: 13px 28px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.3s;
        }

        .save-btn:hover {
            background: #6f4569;
        }

        /* =========================
           Account Info
        ========================= */

        .account-info {
            margin-top: 25px;

            background: #fff6fa;

            padding: 20px;

            border-radius: 10px;
        }

        .account-info h3 {
            margin-bottom: 12px;
            font-size: 16px;
        }

        .account-info p {
            font-size: 13px;
            color: #666;
            margin-bottom: 6px;
        }
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

    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
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

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
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

            .profile-card {
                padding: 22px;
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
            <a href="profile.php" class="active">
                👤 My Profile
            </a>
        </li>

        <li>
            <a href="cart.html">
                🛒 My Cart
            </a>
        </li>

        <li>
            <a href="rental-requests.php">
                📋 Rental Requests
            </a>
        </li>

        <li>
            <a href="orders.php">
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
        <a href="#" onclick="openLogoutModal(); return false;">
    🚪 Logout
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

        <h2>My Profile</h2>

        <div class="user-name">
            👤 <?php echo htmlspecialchars($user["name"]); ?>
        </div>

    </div>


    <!-- Content -->

    <div class="content">

        <div class="profile-container">

            <div class="profile-card">

                <div class="profile-header">

                    <h1>Profile Information</h1>

                    <p>
                        Manage your personal information and rental addresses.
                    </p>

                </div>


                <?php if ($success): ?>

                    <div class="success-message">
                        <?php echo htmlspecialchars($success); ?>
                    </div>

                <?php endif; ?>


                <?php if ($error): ?>

                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>

                <?php endif; ?>


                <!-- Profile Form -->

                <form method="POST" action="">

                    <div class="form-row">

                        <!-- Name -->

                        <div class="form-group">

                            <label for="name">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="<?php echo htmlspecialchars($user["name"]); ?>"
                                required
                            >

                        </div>


                        <!-- Email -->

                        <div class="form-group">

                            <label for="email">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                value="<?php echo htmlspecialchars($user["email"]); ?>"
                                readonly
                            >

                            <div class="form-note">
                                Email address cannot be changed here.
                            </div>

                        </div>


                        <!-- Phone -->

                        <div class="form-group">

                            <label for="phone">
                                Phone Number
                            </label>

                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                value="<?php echo htmlspecialchars($user["phone"] ?? ""); ?>"
                                placeholder="Enter your phone number"
                            >

                        </div>


                        <!-- Shipping Address -->

                        <div class="form-group">

                            <label for="shipping_address">
                                Shipping Address
                            </label>

                            <textarea
                                id="shipping_address"
                                name="shipping_address"
                                placeholder="Enter your shipping address"
                            ><?php echo htmlspecialchars($user["shipping_address"] ?? ""); ?></textarea>

                        </div>


                        <!-- Billing Address -->

                        <div class="form-group full">

                            <label for="billing_address">
                                Billing Address
                            </label>

                            <textarea
                                id="billing_address"
                                name="billing_address"
                                placeholder="Enter your billing address"
                            ><?php echo htmlspecialchars($user["billing_address"] ?? ""); ?></textarea>

                        </div>

                    </div>


                    <!-- Save -->

                    <div class="button-area">

                        <button
                            type="submit"
                            class="save-btn"
                        >
                            Save Changes
                        </button>

                    </div>

                </form>

          <!-- Account Information -->

                <div class="account-info">

                    <h3>Account Information</h3>

                    <p>
                        <strong>Account Type:</strong>
                        <?php echo ucfirst(htmlspecialchars($user["role"])); ?>
                    </p>

                    <p>
                        <strong>Member Since:</strong>
                        <?php
                        echo date(
                            "F d, Y",
                            strtotime($user["created_at"])
                        );
                        ?>
                    </p>

                </div>

            </div>

        </div>

    </div>

</div>
<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="logout-modal">
    <div class="logout-box">
        <h3>Logout</h3>

        <p>Are you sure you want to logout?</p>

        <div class="logout-buttons">
            <button onclick="closeLogoutModal()" class="cancel-btn">
                Cancel
            </button>

            <a href="logout.php" class="confirm-logout-btn">
                Logout
            </a>
        </div>
    </div>
</div>

</body>
<script>
function openLogoutModal() {
    document.getElementById("logoutModal").style.display = "flex";
}

function closeLogoutModal() {
    document.getElementById("logoutModal").style.display = "none";
}
</script>
</html>