<?php

session_start();

require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    echo "Please login first.";
    exit;
}

$user_id = $_SESSION["user_id"];

$product_id = $_POST["product_id"];
$product_name = $_POST["product_name"];
$category = $_POST["category"];
$price = $_POST["price"];
$image = $_POST["image"];
$size = $_POST["size"];
$start_date = $_POST["start_date"];

$rental_days = 5;
$allowed_rental_days = 5;
$late_fee_per_day = 500;

// Calculate expected return date from start date
$start = new DateTime($start_date, new DateTimeZone("Asia/Colombo"));
$start->modify("+5 days");

$expected_return_date = $start->format("Y-m-d");

$base_rental_price = $_POST["base_rental_price"];
$quantity = $_POST["quantity"];


$check_sql = "SELECT cart_item_id, quantity
              FROM cart_items
              WHERE user_id = ?
              AND product_id = ?
              AND size = ?
              AND start_date = ?";

$check_stmt = $conn->prepare($check_sql);

if (!$check_stmt) {
    echo "Check error: " . $conn->error;
    exit;
}

$check_stmt->bind_param(
    "iiss",
    $user_id,
    $product_id,
    $size,
    $start_date
);

if (!$check_stmt->execute()) {
    echo "Check execute error: " . $check_stmt->error;
    exit;
}

$result = $check_stmt->get_result();


if ($result->num_rows > 0) {

    $row = $result->fetch_assoc();

    $new_quantity = $row["quantity"] + $quantity;

   $update_sql = "UPDATE cart_items
               SET quantity = ?
               WHERE cart_item_id = ?
               AND user_id = ?";
    $update_stmt = $conn->prepare($update_sql);

    if (!$update_stmt) {
        echo "Update error: " . $conn->error;
        exit;
    }

   $update_stmt->bind_param(
    "iii",
    $new_quantity,
    $row["cart_item_id"],
    $user_id
);

    if (!$update_stmt->execute()) {
        echo "Update execute error: " . $update_stmt->error;
        exit;
    }

    $update_stmt->close();

} else {

    $insert_sql = "INSERT INTO cart_items
    (
        user_id,
        product_id,
        product_name,
        category,
        price,
        image,
        size,
        start_date,
        expected_return_date,
        rental_days,
        allowed_rental_days,
        base_rental_price,
        late_fee_per_day,
        quantity,
        selected
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $insert_stmt = $conn->prepare($insert_sql);

    if (!$insert_stmt) {
        echo "Insert prepare error: " . $conn->error;
        exit;
    }

    $selected = 1;

   $insert_stmt->bind_param(
    "iissdssssiiddii",
        $user_id,
        $product_id,
        $product_name,
        $category,
        $price,
        $image,
        $size,
        $start_date,
        $expected_return_date,
        $rental_days,
        $allowed_rental_days,
        $base_rental_price,
        $late_fee_per_day,
        $quantity,
        $selected
    );

    if (!$insert_stmt->execute()) {
        echo "Insert execute error: " . $insert_stmt->error;
        exit;
    }

    $insert_stmt->close();
}

$check_stmt->close();
$conn->close();

echo "success";

?>