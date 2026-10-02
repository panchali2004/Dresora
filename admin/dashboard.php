
<?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../account.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Dresora Admin Dashboard</title>

    <style>

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
            padding: 20px 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            margin: 0;
        }

        .logout {
            color: white;
            text-decoration: none;
            background: #8b5a83;
            padding: 10px 18px;
            border-radius: 6px;
        }

        .logout:hover {
            background: #a66b9b;
        }

        /* =========================
           CONTAINER
        ========================== */

        .container {
            width: 92%;
            max-width: 1200px;
            margin: 35px auto;
        }

        .welcome {
            color: #5d405c;
            margin-bottom: 30px;
        }

        /* =========================
           SECTION
        ========================== */

        .section {
            margin-bottom: 40px;
        }

        .section-title {
            color: #5d405c;
            margin-bottom: 18px;
        }

        /* =========================
           CARDS
        ========================== */

        .cards {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .card {
            background: white;
            padding: 25px;
            width: 250px;
            min-height: 150px;

            border-radius: 12px;

            box-shadow: 0 3px 10px rgba(0,0,0,0.08);

            text-decoration: none;
            color: #333;

            transition: 0.2s;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }

        .card h3 {
            color: #5d405c;
            margin-top: 0;
        }

        .card p {
            color: #666;
            line-height: 1.5;
        }

        .card-button {
            display: inline-block;
            margin-top: 10px;

            background: #8b5a83;
            color: white;

            padding: 8px 15px;

            border-radius: 6px;

            font-size: 14px;
        }

        /* =========================
           WEBSITE LINKS
        ========================== */

        .website-card {
            width: 200px;
            min-height: 120px;
        }

        .website-card h3 {
            margin-bottom: 8px;
        }

        /* =========================
           RESPONSIVE
        ========================== */

        @media (max-width: 700px) {

            .container {
                width: 90%;
            }

            .card,
            .website-card {
                width: 100%;
            }

            .header {
                padding: 15px 20px;
            }

        }

    </style>

</head>


<body>


<!-- =========================
     HEADER
========================== -->

<div class="header">

    <h2>
        DRESORA Admin Dashboard
    </h2>
<a href="../customer/logout.php" class="logout">
    Logout
</a>

</div>


<div class="container">


    <!-- =========================
         WELCOME
    ========================== -->

    <h1 class="welcome">
        Welcome, Admin 👋
    </h1>


    <!-- =========================
         ADMIN MANAGEMENT
    ========================== -->

    <div class="section">

        <h2 class="section-title">
            Admin Management
        </h2>

        <div class="cards">


            <!-- MANAGE DRESSES -->

            <a href="dresses.php" class="card">

                <h3>
                    👗 Products
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

          <a href="orders.php" class="card">

    <h3>
        📦 Rentals
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

            <a href="#" class="card">

                <h3>
                    👥 Users
                </h3>

                <p>
                    View registered customers
                    and user information.
                </p>

                <span class="card-button">
                    Coming Soon
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

            <a href="../index.php" class="card website-card">

                <h3>
                    🏠 Home
                </h3>

                <p>
                    View the DRESORA home page.
                </p>

                <span class="card-button">
                    Open
                </span>

            </a>


            <!-- SHOP -->

            <a href="../products.php" class="card website-card">

                <h3>
                    👗 Shop
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

            <a href="../about.html" class="card website-card">

                <h3>
                    ℹ️ About Us
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

            <a href="../contact.html" class="card website-card">

                <h3>
                    📞 Contact
                </h3>

                <p>
                    View the contact page.
                </p>

                <span class="card-button">
                    Open
                </span>

            </a>


          


            <!-- ACCOUNT -->

            <a href="../account.php" class="card website-card">

                <h3>
                    👤 Account
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

</body>

</html>
