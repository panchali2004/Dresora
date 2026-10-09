
<?php
session_start();

require_once "config/database.php";

// Customer login check
if (
    !isset($_SESSION["user_id"]) ||
    (
        isset($_SESSION["role"]) &&
        strtolower($_SESSION["role"]) === "admin"
    )
) {
    header("Location: account.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: products.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

$dress_id = filter_input(
    INPUT_POST,
    "dress_id",
    FILTER_VALIDATE_INT
);

if (!$dress_id || $dress_id <= 0) {
    header("Location: products.php");
    exit;
}

// Check that the dress exists
$stmt = $conn->prepare(
    "SELECT dress_id FROM dresses WHERE dress_id = ?"
);
$stmt->bind_param("i", $dress_id);
$stmt->execute();

$dressExists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$dressExists) {
    header("Location: products.php");
    exit;
}

// Find the customer's wishlist
$stmt = $conn->prepare(
    "SELECT wishlist_id
     FROM wishlist
     WHERE user_id = ?
     LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$wishlist = $result->fetch_assoc();
$stmt->close();

if ($wishlist) {
    $wishlist_id = (int) $wishlist["wishlist_id"];
} else {
    // Create a wishlist for this customer
    $stmt = $conn->prepare(
        "INSERT INTO wishlist (user_id) VALUES (?)"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $wishlist_id = $conn->insert_id;
    $stmt->close();
}

// Check whether the dress is already added
$stmt = $conn->prepare(
    "SELECT wishlist_item_id
     FROM wishlist_items
     WHERE wishlist_id = ? AND dress_id = ?
     LIMIT 1"
);
$stmt->bind_param("ii", $wishlist_id, $dress_id);
$stmt->execute();

$alreadyAdded = $stmt->get_result()->num_rows > 0;
$stmt->close();

// Insert only if it is not already in the wishlist
if (!$alreadyAdded) {
    $stmt = $conn->prepare(
        "INSERT INTO wishlist_items (wishlist_id, dress_id)
         VALUES (?, ?)"
    );
    $stmt->bind_param("ii", $wishlist_id, $dress_id);
    $stmt->execute();
    $stmt->close();
}

// Return to the wishlist page
header("Location: wishlist.php");
exit;
?>