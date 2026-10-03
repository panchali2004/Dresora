<?php

session_start();

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| PayHere Sandbox Configuration
|--------------------------------------------------------------------------
*/

$merchant_id = "1238462";

/*
 * IMPORTANT:
 * Put your PayHere Merchant Secret here.
 * Do NOT share your Merchant Secret with anyone.
 */
$merchant_secret = "Mzk2NzEzNDI4MTY3NTgxMjg1MjQwMTI0Mzc1MzMzNjAwMjA0Mzk=";


/*
|--------------------------------------------------------------------------
| Login Check
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: ../account.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Get Order ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["order_id"]) || !is_numeric($_GET["order_id"])) {
    die("Invalid order ID.");
}

$order_id = (int) $_GET["order_id"];


/*
|--------------------------------------------------------------------------
| Get Order
|--------------------------------------------------------------------------
|
| Only allow:
| - Current logged-in user's order
| - Confirmed order
| - Online Payment
| - Unpaid order
|
*/

$sql = "SELECT
            order_id,
            user_id,
            total_amount,
            payment_method,
            payment_status,
            status
        FROM orders
        WHERE order_id = ?
        AND user_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Order query failed: " . $conn->error);
}

$stmt->bind_param("ii", $order_id, $user_id);

$stmt->execute();

$result = $stmt->get_result();

$order = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Check Order Exists
|--------------------------------------------------------------------------
*/

if (!$order) {
    die("Order not found.");
}


/*
|--------------------------------------------------------------------------
| Check Order Status
|--------------------------------------------------------------------------
*/

$status = strtolower(trim($order["status"] ?? ""));

$payment_method = strtolower(
    trim($order["payment_method"] ?? "")
);

$payment_status = strtolower(
    trim($order["payment_status"] ?? "unpaid")
);


/*
|--------------------------------------------------------------------------
| Only Confirmed Orders Can Be Paid
|--------------------------------------------------------------------------
*/

if ($status !== "confirmed") {
    die("This order has not been confirmed by the admin yet.");
}


/*
|--------------------------------------------------------------------------
| Only Online Payment Orders Can Be Paid
|--------------------------------------------------------------------------
*/

if (
    $payment_method !== "online payment" &&
    $payment_method !== "online" &&
    $payment_method !== "payhere"
) {
    die("This order is not an online payment order.");
}


/*
|--------------------------------------------------------------------------
| Prevent Paying Already Paid Order
|--------------------------------------------------------------------------
*/

if ($payment_status === "paid") {
    die("This order has already been paid.");
}


/*
|--------------------------------------------------------------------------
| Get Customer Details
|--------------------------------------------------------------------------
*/

$user_sql = "SELECT
                name,
                email,
                phone,
                shipping_address
             FROM users
             WHERE user_id = ?
             LIMIT 1";

$user_stmt = $conn->prepare($user_sql);

if (!$user_stmt) {
    die("Customer query failed: " . $conn->error);
}

$user_stmt->bind_param("i", $user_id);

$user_stmt->execute();

$user_result = $user_stmt->get_result();

$user = $user_result->fetch_assoc();

$user_stmt->close();


if (!$user) {
    die("Customer details not found.");
}


/*
|--------------------------------------------------------------------------
| Split Customer Name
|--------------------------------------------------------------------------
*/

$full_name = trim($user["name"] ?? "");

$name_parts = preg_split('/\s+/', $full_name);

$first_name = $name_parts[0] ?? "Customer";

if (count($name_parts) > 1) {

    array_shift($name_parts);

    $last_name = implode(" ", $name_parts);

} else {

    $last_name = "Customer";
}


/*
|--------------------------------------------------------------------------
| Customer Information
|--------------------------------------------------------------------------
*/

$email = trim($user["email"] ?? "");

$phone = trim($user["phone"] ?? "");

$address = trim($user["shipping_address"] ?? "");


/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid customer email address.");
}

if ($phone === "") {
    die("Customer phone number is required.");
}

if ($address === "") {
    $address = "Dresora Customer";
}


/*
|--------------------------------------------------------------------------
| Payment Amount
|--------------------------------------------------------------------------
*/

$amount = (float) $order["total_amount"];

if ($amount <= 0) {
    die("Invalid payment amount.");
}


/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/

$currency = "LKR";


/*
|--------------------------------------------------------------------------
| Format Amount
|--------------------------------------------------------------------------
*/

$amount_formatted = number_format(
    $amount,
    2,
    ".",
    ""
);


/*
|--------------------------------------------------------------------------
| Generate PayHere Hash
|--------------------------------------------------------------------------
|
| PayHere official formula:
|
| hash =
| strtoupper(
|     md5(
|         merchant_id
|         + order_id
|         + amount
|         + currency
|         + strtoupper(md5(merchant_secret))
|     )
| )
|
*/

$hashed_secret = strtoupper(
    md5($merchant_secret)
);

$hash = strtoupper(
    md5(
        $merchant_id .
        $order_id .
        $amount_formatted .
        $currency .
        $hashed_secret
    )
);


/*
|--------------------------------------------------------------------------
| PayHere URLs
|--------------------------------------------------------------------------
|
| Sandbox payment gateway
|
*/

$payhere_url = "https://sandbox.payhere.lk/pay/checkout";


/*
|--------------------------------------------------------------------------
| Return URL
|--------------------------------------------------------------------------
|
| Customer will be redirected here after payment.
|
*/

$return_url =
    "http://localhost/Dresora/customer/my-orders.php";


/*
|--------------------------------------------------------------------------
| Cancel URL
|--------------------------------------------------------------------------
|
| Customer will be redirected here if payment is cancelled.
|
*/

$cancel_url =
    "http://localhost/Dresora/customer/my-orders.php";


/*
|--------------------------------------------------------------------------
| Notify URL
|--------------------------------------------------------------------------
|
| IMPORTANT:
| PayHere server must be able to access this URL publicly.
|
| localhost will NOT work for PayHere server notifications.
|
| We will configure a public URL later.
|
*/

$notify_url =
    "http://localhost/Dresora/payment/payhere-notify.php";


/*
|--------------------------------------------------------------------------
| Item Description
|--------------------------------------------------------------------------
*/

$items = "Dresora Dress Rental - Order #" . $order_id;

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Redirecting to PayHere</title>

    <style>

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #fffafc;
            color: #5d405c;

            display: flex;
            justify-content: center;
            align-items: center;

            min-height: 100vh;
        }

        .payment-box {
            width: 90%;
            max-width: 500px;

            background: white;

            padding: 40px;

            border-radius: 15px;

            text-align: center;

            box-shadow: 0 5px 25px rgba(139, 90, 131, 0.15);
        }

        .payment-box h2 {
            color: #8b5a83;
            margin-bottom: 15px;
        }

        .payment-box p {
            color: #666;
            line-height: 1.6;
        }

        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #8b5a83;
            margin: 20px 0;
        }

        .pay-button {
            border: none;

            background: #8b5a83;
            color: white;

            padding: 12px 30px;

            border-radius: 8px;

            font-size: 16px;

            cursor: pointer;
        }

        .pay-button:hover {
            background: #5d405c;
        }

        .cancel-link {
            display: inline-block;

            margin-top: 15px;

            color: #8b5a83;

            text-decoration: none;
        }

    </style>

</head>

<body>

<div class="payment-box">

    <h2>💳 Dresora Online Payment</h2>

    <p>
        You are about to make an online payment for
        <strong>Order #<?php echo $order_id; ?></strong>.
    </p>

    <div class="amount">
        LKR <?php echo htmlspecialchars($amount_formatted); ?>
    </div>

    <p>
        You will be redirected to the PayHere Sandbox
        payment gateway.
    </p>


    <!--
    |--------------------------------------------------------------------------
    | PayHere Checkout Form
    |--------------------------------------------------------------------------
    -->

    <form method="post"
          action="<?php echo htmlspecialchars($payhere_url); ?>">

        <!-- Merchant ID -->
        <input
            type="hidden"
            name="merchant_id"
            value="<?php echo htmlspecialchars($merchant_id); ?>"
        >


        <!-- Return URL -->
        <input
            type="hidden"
            name="return_url"
            value="<?php echo htmlspecialchars($return_url); ?>"
        >


        <!-- Cancel URL -->
        <input
            type="hidden"
            name="cancel_url"
            value="<?php echo htmlspecialchars($cancel_url); ?>"
        >


        <!-- Notify URL -->
        <input
            type="hidden"
            name="notify_url"
            value="<?php echo htmlspecialchars($notify_url); ?>"
        >


        <!-- Order ID -->
        <input
            type="hidden"
            name="order_id"
            value="<?php echo $order_id; ?>"
        >


        <!-- Item -->
        <input
            type="hidden"
            name="items"
            value="<?php echo htmlspecialchars($items); ?>"
        >


        <!-- Currency -->
        <input
            type="hidden"
            name="currency"
            value="<?php echo htmlspecialchars($currency); ?>"
        >


        <!-- Amount -->
        <input
            type="hidden"
            name="amount"
            value="<?php echo htmlspecialchars($amount_formatted); ?>"
        >


        <!-- Hash -->
        <input
            type="hidden"
            name="hash"
            value="<?php echo htmlspecialchars($hash); ?>"
        >


        <!-- First Name -->
        <input
            type="hidden"
            name="first_name"
            value="<?php echo htmlspecialchars($first_name); ?>"
        >


        <!-- Last Name -->
        <input
            type="hidden"
            name="last_name"
            value="<?php echo htmlspecialchars($last_name); ?>"
        >


        <!-- Email -->
        <input
            type="hidden"
            name="email"
            value="<?php echo htmlspecialchars($email); ?>"
        >


        <!-- Phone -->
        <input
            type="hidden"
            name="phone"
            value="<?php echo htmlspecialchars($phone); ?>"
        >


        <!-- Address -->
        <input
            type="hidden"
            name="address"
            value="<?php echo htmlspecialchars($address); ?>"
        >


        <!-- City -->
        <input
            type="hidden"
            name="city"
            value="Sri Lanka"
        >


        <!-- Country -->
        <input
            type="hidden"
            name="country"
            value="Sri Lanka"
        >


        <button
            type="submit"
            class="pay-button"
        >
            Continue to PayHere
        </button>

    </form>


    <a
        href="../customer/my-orders.php"
        class="cancel-link"
    >
        ← Back to My Orders
    </a>

</div>


<!--
|--------------------------------------------------------------------------
| Auto Submit
|--------------------------------------------------------------------------
|
| This automatically redirects the customer to PayHere.
|
-->

<script>

    window.onload = function () {

        document.querySelector(
            'form'
        ).submit();

    };

</script>

</body>

</html>