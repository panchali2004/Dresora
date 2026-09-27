<?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../account.php");
    exit;
}

if ($_SESSION["role"] !== "customer") {
    header("Location: ../account.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$success = "";
$error = "";

/* =========================
   CHANGE PASSWORD
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (
        $current_password === "" ||
        $new_password === "" ||
        $confirm_password === ""
    ) {
        $error = "Please fill in all password fields.";
    }

    elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    }

    elseif (strlen($new_password) < 8) {
        $error = "New password must contain at least 8 characters.";
    }

    else {

        /* Get current hashed password */
        $sql = "SELECT password FROM users WHERE user_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        if (!$user) {

            $error = "User account not found.";

        }

        /* Check current password */
        elseif (!password_verify($current_password, $user["password"])) {

            $error = "Current password is incorrect.";

        }

        else {

            /* Hash new password */
            $hashed_password = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            /* Update password */
            $sql = "UPDATE users SET password = ? WHERE user_id = ?";

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
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Settings - Dresora</title>

    <link rel="stylesheet"
          href="../style.css">

    <style>

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffafc;
        }

        .settings-container {
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
        }

        .settings-header {
            margin-bottom: 30px;
        }

        .settings-header h1 {
            color: #5d405c;
            margin-bottom: 8px;
        }

        .settings-header p {
            color: #777;
        }

        .settings-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        .settings-card h2 {
            color: #5d405c;
            margin-top: 0;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #5d405c;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 15px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #8b5a83;
        }

        .form-note {
            margin-top: 6px;
            font-size: 13px;
            color: #777;
        }

        .success-message {
            background: #e8f7ed;
            color: #257942;
            padding: 12px 15px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .error-message {
            background: #fdeaea;
            color: #b42318;
            padding: 12px 15px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .button-area {
            margin-top: 25px;
        }

        .change-password-btn {
            background: #8b5a83;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
        }

        .change-password-btn:hover {
            background: #5d405c;
        }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #8b5a83;
        }

        .back-btn:hover {
            text-decoration: underline;
        }

    </style>

</head>

<body>

<div class="settings-container">

    <div class="settings-header">

        <h1>Settings</h1>

        <p>
            Manage your account settings and password.
        </p>

    </div>


    <div class="settings-card">

        <h2>Change Password</h2>


        <?php if ($success !== ""): ?>

            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>


        <form method="POST" action="">

            <div class="form-group">

                <label for="current_password">
                    Current Password
                </label>

                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    placeholder="Enter your current password"
                    required
                >

            </div>


            <div class="form-group">

                <label for="new_password">
                    New Password
                </label>

                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    placeholder="Enter your new password"
                    required
                >

                <div class="form-note">
                    Password must contain at least 8 characters.
                </div>

            </div>


            <div class="form-group">

                <label for="confirm_password">
                    Confirm New Password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Confirm your new password"
                    required
                >

            </div>


            <div class="button-area">

                <button
                    type="submit"
                    class="change-password-btn"
                >
                    Change Password
                </button>

            </div>

        </form>


        <a href="profile.php" class="back-btn">
            ← Back to My Profile
        </a>

    </div>

</div>

</body>

</html>