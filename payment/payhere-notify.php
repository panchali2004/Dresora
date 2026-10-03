
<?php

require_once "../config/database.php";

/*
|--------------------------------------------------------------------------
| PayHere Configuration
|--------------------------------------------------------------------------
*/

$merchant_id = "1238462";

/*
 * IMPORTANT:
 * Use the same Merchant Secret used in payhere-checkout.php
 * DO NOT share this secret with anyone.
 */
$merchant_secret = "Mzk2NzEzNDI4MTY3NTgxMjg1MjQwMTI0Mzc1MzMzNjAwMjA0Mzk=";


/*
|--------------------------------------------------------------------------
| Check Required POST Parameters
|--------------------------------------------------------------------------
*/

if (
    !isset($_POST["merchant_id"]) ||
    !isset($_POST["order_id"]) ||
    !isset($_POST["payhere_amount"]) ||
    !isset($_POST["payhere_currency"]) ||
    !isset($_POST["status_code"]) ||
    !isset($_POST["md5sig"])
) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Get PayHere Notification Data
|--------------------------------------------------------------------------
*/

$received_merchant_id = trim($_POST["merchant_id"]);

$order_id = (int) $_POST["order_id"];

$payhere_amount = trim($_POST["payhere_amount"]);

$payhere_currency = trim($_POST["payhere_currency"]);

$status_code = trim($_POST["status_code"]);

$received_md5sig = strtoupper(
    trim($_POST["md5sig"])
);


/*
|--------------------------------------------------------------------------
| Verify Merchant ID
|--------------------------------------------------------------------------
*/

if ($received_merchant_id !== $merchant_id) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Generate Local MD5 Signature
|--------------------------------------------------------------------------
|
| PayHere official formula:
|
| md5sig =
| strtoupper(
|     md5(
|         merchant_id
|         + order_id
|         + payhere_amount
|         + payhere_currency
|         + status_code
|         + strtoupper(md5(merchant_secret))
|     )
| )
|
*/

$local_md5sig = strtoupper(
    md5(
        $merchant_id .
        $order_id .
        $payhere_amount .
        $payhere_currency .
        $status_code .
        strtoupper(md5($merchant_secret))
    )
);


/*
|--------------------------------------------------------------------------
| Verify Payment Notification
|--------------------------------------------------------------------------
*/

if (!hash_equals($local_md5sig, $received_md5sig)) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Only Status Code 2 = Successful Payment
|--------------------------------------------------------------------------
*/

if ($status_code !== "2") {
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Order From Database
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            order_id,
            total_amount,
            payment_method,
            payment_status,
            status
        FROM orders
        WHERE order_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit;
}

$stmt->bind_param("i", $order_id);

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
    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Order Status
|--------------------------------------------------------------------------
*/

$order_status = strtolower(
    trim($order["status"] ?? "")
);

$payment_method = strtolower(
    trim($order["payment_method"] ?? "")
);

$payment_status = strtolower(
    trim($order["payment_status"] ?? "unpaid")
);


/*
|--------------------------------------------------------------------------
| Only Confirmed Orders Can Become Paid
|--------------------------------------------------------------------------
*/

if ($order_status !== "confirmed") {
    exit;
}


/*
|--------------------------------------------------------------------------
| Only Online Payment Orders
|--------------------------------------------------------------------------
*/

if (
    $payment_method !== "online payment" &&
    $payment_method !== "online" &&
    $payment_method !== "payhere"
) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Prevent Duplicate Payment Updates
|--------------------------------------------------------------------------
*/

if ($payment_status === "paid") {
    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Payment Amount
|--------------------------------------------------------------------------
|
| Compare the amount received from PayHere
| with the amount stored in our database.
|
*/

$database_amount = number_format(
    (float) $order["total_amount"],
    2,
    ".",
    ""
);

$received_amount = number_format(
    (float) $payhere_amount,
    2,
    ".",
    ""
);


if ($database_amount !== $received_amount) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Verify Currency
|--------------------------------------------------------------------------
*/

if (strtoupper($payhere_currency) !== "LKR") {
    exit;
}


/*
|--------------------------------------------------------------------------
| Update Payment Status
|--------------------------------------------------------------------------
*/

$update_sql = "UPDATE orders
               SET payment_status = 'paid'
               WHERE order_id = ?
               AND payment_status = 'unpaid'";

$update_stmt = $conn->prepare($update_sql);

if (!$update_stmt) {
    exit;
}

$update_stmt->bind_param("i", $order_id);

$update_stmt->execute();

$update_stmt->close();


/*
|--------------------------------------------------------------------------
| End
|--------------------------------------------------------------------------
*/

exit;

?>