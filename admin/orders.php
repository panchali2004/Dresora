<?php

session_start();

require_once "../config/database.php";

// Admin only
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../account.php");
    exit;
}


/* =========================
   GET CUSTOMER ORDERS
========================= */

$sql = "SELECT
            o.order_id,
            o.user_id,
            o.order_date,
            o.status,
            o.payment_method,
            o.total_amount,
            u.name,
            u.email
        FROM orders o
        INNER JOIN users u
            ON o.user_id = u.user_id
        ORDER BY o.order_date DESC";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Manage Orders - DRESORA</title>

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

        .header {
            background: #5d405c;
            color: white;
            padding: 18px 35px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            margin: 0;
        }

        .back-btn {
            color: white;
            text-decoration: none;
            background: #8b5a83;
            padding: 10px 18px;
            border-radius: 6px;
        }

        .back-btn:hover {
            background: #a66b9b;
        }

        .container {
            width: 94%;
            max-width: 1300px;
            margin: 35px auto;
        }

        .page-title {
            color: #5d405c;
            margin-bottom: 25px;
        }

        .table-box {
            background: white;
            padding: 25px;
            border-radius: 12px;

            box-shadow:
                0 3px 12px rgba(0,0,0,0.08);

            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #f5edf4;
            color: #5d405c;
        }

        .order-id {
            color: #8b5a83;
            font-weight: bold;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
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

        .status.cancelled {
            background: #fdecec;
            color: #b33a3a;
        }

        .status.rejected {
            background: #f5e5e5;
            color: #8b3030;
        }

        .amount {
            font-weight: bold;
            color: #5d405c;
        }

        .view-btn {
            display: inline-block;
            text-decoration: none;

            background: #8b5a83;
            color: white;

            padding: 8px 14px;
            border-radius: 6px;

            font-size: 12px;
            font-weight: 600;
        }

        .view-btn:hover {
            background: #5d405c;
        }

    </style>

</head>

<body>

<header class="header">

    <h2>DRESORA Admin</h2>

    <a href="dashboard.php" class="back-btn">
        ← Dashboard
    </a>

</header>


<div class="container">

    <h1 class="page-title">
        Customer Orders
    </h1>


    <div class="table-box">

        <table>

            <thead>

                <tr>

                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($order = $result->fetch_assoc()): ?>

                        <?php
                        $status = strtolower($order["status"]);
                        ?>

                        <tr>

                            <td>
                                <span class="order-id">
                                    #<?php echo (int)$order["order_id"]; ?>
                                </span>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $order["name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $order["email"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo date(
                                    "M d, Y",
                                    strtotime($order["order_date"])
                                );
                                ?>
                            </td>

                            <td>

                                <span class="amount">

                                    Rs.
                                    <?php
                                    echo number_format(
                                        (float)$order["total_amount"],
                                        2
                                    );
                                    ?>

                                </span>

                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $order["payment_method"]
                                );
                                ?>
                            </td>

                            <td>

                                <span class="status <?php echo htmlspecialchars($status); ?>">

                                    <?php
                                    echo ucfirst(
                                        htmlspecialchars(
                                            $order["status"]
                                        )
                                    );
                                    ?>

                                </span>

                            </td>

                            <td>

                                <a
                                    href="order-details.php?order_id=<?php echo (int)$order["order_id"]; ?>"
                                    class="view-btn"
                                >
                                    View
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="8">
                            No customer orders found.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>

</html>