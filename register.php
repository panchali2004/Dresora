<?php

require_once "config/database.php";

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get form data
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $shipping_address = trim($_POST["shipping_address"]);
   
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];


    /* =========================
       Basic Validation
    ========================= */

    if (
        $name === "" ||
        $email === "" ||
        $phone === "" ||
        $shipping_address === "" ||
       
        $password === "" ||
        $confirm_password === ""
    ) {

        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } elseif (strlen($password) < 8) {

        $error = "Password must contain at least 8 characters.";

    } else {

        /* =========================
           Check Existing Email
        ========================= */

        $check_sql = "SELECT user_id FROM users WHERE email = ?";

        $check_stmt = $conn->prepare($check_sql);

        $check_stmt->bind_param("s", $email);

        $check_stmt->execute();

        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {

            $error = "An account with this email already exists.";

        } else {

            /* =========================
               Password Hash
            ========================= */

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            /* =========================
               Insert User
            ========================= */

            $sql = "INSERT INTO users
                    (
                        name,
                        email,
                        password,
                        phone,
                        shipping_address,
                        billing_address,
                        role
                    )
                    VALUES (?, ?, ?, ?, ?, ?, 'customer')";

            $stmt = $conn->prepare($sql);

            if ($stmt) {

                $stmt->bind_param(
                    "ssssss",
                    $name,
                    $email,
                    $hashed_password,
                    $phone,
                    $shipping_address,
                    $billing_address
                );

                if ($stmt->execute()) {

                    $success = "Account created successfully! You can now login.";

                    // Clear form values
                    $name = "";
                    $email = "";
                    $phone = "";
                    $shipping_address = "";
                    $billing_address = "";

                } else {

                    $error = "Registration failed. Please try again.";
                }

                $stmt->close();

            } else {

                $error = "Database error. Please try again.";
            }
        }

        $check_stmt->close();
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

    <title>Create Account - DRESORA</title>


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
           Navigation
        ========================= */

        nav {
            height: 70px;

            background: white;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 7%;

            box-shadow: 0 2px 10px rgba(0,0,0,0.05);

            position: sticky;

            top: 0;

            z-index: 100;
        }


        .logo {
            text-decoration: none;

            color: #5d405c;

            font-weight: bold;

            letter-spacing: 2px;

            font-size: 24px;
        }


        .logo small {
            display: block;

            font-size: 9px;

            letter-spacing: 3px;

            text-align: center;

            color: #a66b9b;
        }


        nav ul {
            list-style: none;

            display: flex;

            gap: 25px;

            align-items: center;
        }


        nav ul li a {
            text-decoration: none;

            color: #5d405c;

            font-size: 14px;

            transition: 0.3s;
        }


        nav ul li a:hover {
            color: #a66b9b;
        }


        /* =========================
           Register Section
        ========================= */

        .register-section {
            min-height: calc(100vh - 70px);

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 45px 20px;
        }


        .register-card {
            width: 100%;

            max-width: 650px;

            background: white;

            padding: 40px;

            border-radius: 18px;

            box-shadow:
                0 8px 30px rgba(93, 64, 92, 0.10);
        }


        .register-header {
            text-align: center;

            margin-bottom: 30px;
        }


        .register-header h1 {
            font-size: 28px;

            color: #5d405c;

            margin-bottom: 8px;
        }


        .register-header p {
            color: #777;

            font-size: 14px;
        }


        /* =========================
           Messages
        ========================= */

        .success-message {
            background: #e9f8ef;

            color: #267342;

            padding: 13px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;

            text-align: center;
        }


        .error-message {
            background: #fdecec;

            color: #b33a3a;

            padding: 13px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;

            text-align: center;
        }


        /* =========================
           Form
        ========================= */

        .form-row {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 18px;
        }


        .form-group {
            margin-bottom: 18px;
        }


        .form-group.full {
            grid-column: 1 / -1;
        }


        .form-group label {
            display: block;

            margin-bottom: 7px;

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

            outline: none;

            font-size: 14px;

            font-family: Arial, sans-serif;

            transition: 0.3s;
        }


        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #a66b9b;

            box-shadow:
                0 0 0 3px rgba(166, 107, 155, 0.10);
        }


        .form-group textarea {
            min-height: 90px;

            resize: vertical;
        }


        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #aaa;
        }


        .password-note {
            font-size: 11px;

            color: #888;

            margin-top: 5px;
        }


        /* =========================
           Button
        ========================= */

        .register-btn {
            width: 100%;

            border: none;

            background: #8b5a83;

            color: white;

            padding: 14px;

            border-radius: 8px;

            font-size: 15px;

            font-weight: 600;

            cursor: pointer;

            margin-top: 5px;

            transition: 0.3s;
        }


        .register-btn:hover {
            background: #6f4569;
        }


        /* =========================
           Login Link
        ========================= */

        .login-link {
            text-align: center;

            margin-top: 22px;

            font-size: 14px;

            color: #777;
        }


        .login-link a {
            color: #8b5a83;

            font-weight: 600;

            text-decoration: none;
        }


        .login-link a:hover {
            text-decoration: underline;
        }


        /* =========================
           Responsive
        ========================= */

        @media (max-width: 650px) {

            nav {
                padding: 0 20px;
            }

            nav ul {
                gap: 12px;
            }

            nav ul li a {
                font-size: 12px;
            }

            .register-card {
                padding: 25px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

        }

    </style>

</head>


<body>


<!-- =========================
     Navigation
========================= -->

<nav>

    <a href="index.php" class="logo">

        DRESORA

        <small>DRESS RENTAL</small>

    </a>


    <ul>

        <li>
            <a href="index.php">Home</a>
        </li>

        <li>
            <a href="products.php">Shop</a>
        </li>

        <li>
            <a href="about.html">About</a>
        </li>

        <li>
            <a href="contact.html">Contact</a>
        </li>

        <li>
            <a href="account.php">Login</a>
        </li>

    </ul>

</nav>


<!-- =========================
     Register Section
========================= -->

<section class="register-section">


    <div class="register-card">


        <div class="register-header">

            <h1>Create Account</h1>

            <p>
                Create your DRESORA account and start renting your favourite dresses.
            </p>

        </div>


        <!-- Success Message -->

        <?php if ($success): ?>

            <div class="success-message">

                <?php echo htmlspecialchars($success); ?>

            </div>

        <?php endif; ?>


        <!-- Error Message -->

        <?php if ($error): ?>

            <div class="error-message">

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <!-- Registration Form -->

        <form
            method="POST"
            action=""
        >

            <div class="form-row">


                <!-- Full Name -->

                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter your full name"
                        value="<?php echo htmlspecialchars($name ?? ''); ?>"
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
                        name="email"
                        placeholder="Enter your email"
                        value="<?php echo htmlspecialchars($email ?? ''); ?>"
                        required
                    >

                </div>


                <!-- Phone -->

                <div class="form-group">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        placeholder="Enter your phone number"
                        value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                        required
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
        required
    ><?php echo htmlspecialchars($shipping_address ?? ''); ?></textarea>

</div>




                <!-- Password -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Create a password"
                        required
                    >

                    <div class="password-note">
                        Password must contain at least 8 characters.
                    </div>

                </div>


                <!-- Confirm Password -->

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm your password"
                        required
                    >

                </div>


                <!-- Button -->

                <div class="form-group full">

                    <button
                        type="submit"
                        class="register-btn"
                    >
                        Create Account
                    </button>

                </div>

            </div>

        </form>


        <!-- Login -->

        <div class="login-link">

            Already have an account?

            <a href="account.php">
                Login here
            </a>

        </div>


    </div>

</section>


</body>

</html>