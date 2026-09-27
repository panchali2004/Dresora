<?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dresora Admin Dashboard</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #fffafc;
        }

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

        .container {
            padding: 40px;
        }

        .cards {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .card {
            background: white;
            padding: 25px;
            width: 220px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .card h3 {
            color: #5d405c;
        }
    </style>
</head>

<body>

<div class="header">
    <h2>DRESORA Admin Dashboard</h2>
    <a href="../logout.php" class="logout">Logout</a>
</div>

<div class="container">

    <h1>Welcome, Admin 👋</h1>

    <div class="cards">

        <div class="card">
            <h3>Products</h3>
            <p>Manage dresses and products.</p>
        </div>

        <div class="card">
            <h3>Rentals</h3>
            <p>Manage customer rental orders.</p>
        </div>

        <div class="card">
            <h3>Users</h3>
            <p>View registered customers.</p>
        </div>

    </div>

</div>

</body>
</html>