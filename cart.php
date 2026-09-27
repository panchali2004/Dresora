
<?php

session_start();

/* ==========================================
   BLOCK ADMIN FROM CART
========================================== */

if (isset($_SESSION["role"]) && $_SESSION["role"] === "admin") {
    header("Location: admin/dashboard.php");
    exit;
}


/* ==========================================
   REQUIRE CUSTOMER LOGIN
========================================== */

if (!isset($_SESSION["user_id"])) {
    header("Location: account.php");
    exit;
}


require_once "config/database.php";

$user_id = $_SESSION["user_id"];


/* ==========================================
   HANDLE QUANTITY UPDATE
========================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $cart_item_id = intval($_POST["cart_item_id"] ?? 0);
    $action = $_POST["action"] ?? "";

    if ($cart_item_id > 0) {

        /* =========================
           INCREASE
        ========================= */

        if ($action === "increase") {

            $sql = "UPDATE cart_items
                    SET quantity = quantity + 1
                    WHERE cart_item_id = ?
                    AND user_id = ?";

            $stmt = $conn->prepare($sql);

            if ($stmt) {

                $stmt->bind_param(
                    "ii",
                    $cart_item_id,
                    $user_id
                );

                $stmt->execute();

                $stmt->close();
            }
        }


        /* =========================
           DECREASE
        ========================= */

        elseif ($action === "decrease") {

            /* First get current quantity */

            $sql = "SELECT quantity
                    FROM cart_items
                    WHERE cart_item_id = ?
                    AND user_id = ?";

            $stmt = $conn->prepare($sql);

            if ($stmt) {

                $stmt->bind_param(
                    "ii",
                    $cart_item_id,
                    $user_id
                );

                $stmt->execute();

                $result = $stmt->get_result();

                if ($result->num_rows === 1) {

                    $item = $result->fetch_assoc();

                    $quantity =
                        intval($item["quantity"]);

                    $stmt->close();


                    if ($quantity <= 1) {

                        /* Remove item */

                        $delete_sql =
                            "DELETE FROM cart_items
                             WHERE cart_item_id = ?
                             AND user_id = ?";

                        $delete_stmt =
                            $conn->prepare($delete_sql);

                        if ($delete_stmt) {

                            $delete_stmt->bind_param(
                                "ii",
                                $cart_item_id,
                                $user_id
                            );

                            $delete_stmt->execute();

                            $delete_stmt->close();
                        }

                    } else {

                        /* Reduce quantity */

                        $update_sql =
                            "UPDATE cart_items
                             SET quantity = quantity - 1
                             WHERE cart_item_id = ?
                             AND user_id = ?";

                        $update_stmt =
                            $conn->prepare($update_sql);

                        if ($update_stmt) {

                            $update_stmt->bind_param(
                                "ii",
                                $cart_item_id,
                                $user_id
                            );

                            $update_stmt->execute();

                            $update_stmt->close();
                        }
                    }
                } else {

                    $stmt->close();
                }
            }
        }


        /* =========================
           TOGGLE SELECTED
        ========================= */

        elseif ($action === "toggle") {

            $selected =
                intval($_POST["selected"] ?? 0);

            $sql =
                "UPDATE cart_items
                 SET selected = ?
                 WHERE cart_item_id = ?
                 AND user_id = ?";

            $stmt = $conn->prepare($sql);

            if ($stmt) {

                $stmt->bind_param(
                    "iii",
                    $selected,
                    $cart_item_id,
                    $user_id
                );

                $stmt->execute();

                $stmt->close();
            }
        }
    }

    /*
       Redirect after POST.
       This prevents duplicate form submission
       when refreshing the page.
    */

    header("Location: cart.php");
    exit;
}


/* ==========================================
   GET CURRENT USER CART
========================================== */

$sql = "SELECT
            cart_item_id,
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
        FROM cart_items
        WHERE user_id = ?
        ORDER BY created_at DESC";


$stmt = $conn->prepare($sql);

$cart_items = [];

if ($stmt) {

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    $result =
        $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $cart_items[] = $row;
    }

    $stmt->close();
}


/* ==========================================
   CALCULATE SUMMARY
========================================== */

$totalItems = 0;
$subtotal = 0;

foreach ($cart_items as $item) {

    if ((int)$item["selected"] === 1) {

        $quantity =
            (int)$item["quantity"];

        $price =
            (float)$item["price"];

        $totalItems +=
            $quantity;

        $subtotal +=
            $price * $quantity;
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Dresora - Shopping Cart</title>

<link rel="stylesheet"
      href="style.css">

<style>

.cart-page {
    max-width: 1100px;
    margin: auto;
    padding: 70px 30px;
}

.cart-page h1 {
    color: #5d405c;
    margin-bottom: 30px;
}

.cart-layout {
    display: flex;
    gap: 30px;
    align-items: flex-start;
}

.cart-items {
    flex: 1;
}

.cart-item {
    display: flex;
    gap: 20px;
    background: white;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(120, 80, 110, 0.10);
    transition: 0.3s;
}

.cart-item.unselected {
    opacity: 0.55;
}

.cart-item img {
    width: 130px;
    height: 160px;
    object-fit: cover;
    border-radius: 10px;
}

.cart-item-info {
    flex: 1;
}

.cart-item-info h3 {
    color: #5d405c;
    margin: 0 0 10px;
}

.cart-item-info p {
    color: #777078;
    margin: 6px 0;
}

.cart-item-price {
    color: #8b5a83;
    font-weight: bold;
    font-size: 18px;
    margin-top: 10px;
}


/* =========================
   CHECKBOX
========================= */

.select-item {
    width: 22px;
    height: 22px;
    margin-top: 5px;
    accent-color: #8b5a83;
    cursor: pointer;
    flex-shrink: 0;
}


/* =========================
   QUANTITY
========================= */

.quantity-control {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 15px;
}

.quantity-control button {
    width: 32px;
    height: 32px;
    border: 1px solid #dcc6d8;
    background: white;
    color: #8b5a83;
    border-radius: 6px;
    cursor: pointer;
    font-size: 18px;
    font-weight: bold;
    transition: 0.2s;
}

.quantity-control button:hover {
    background: #8b5a83;
    color: white;
}

.quantity-number {
    min-width: 25px;
    text-align: center;
    font-weight: bold;
    color: #5d405c;
}


/* =========================
   SUMMARY
========================= */

.cart-summary {
    width: 320px;
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(120, 80, 110, 0.10);
}

.cart-summary h2 {
    color: #5d405c;
    margin-top: 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin: 15px 0;
    color: #665966;
}

.summary-total {
    border-top: 1px solid #dcc6d8;
    padding-top: 15px;
    font-size: 20px;
    font-weight: bold;
    color: #5d405c;
}

.summary-total span {
    color: #8b5a83;
}


/* =========================
   CHECKOUT
========================= */

.checkout-btn {
    display: block;
    text-align: center;
    text-decoration: none;
    margin-top: 20px;
    padding: 13px;
    background: #8b5a83;
    color: white;
    border-radius: 25px;
    font-weight: bold;
    transition: 0.3s;
}

.checkout-btn:hover {
    background: #6f4568;
}


/* =========================
   CONTINUE SHOPPING
========================= */

.continue-shopping {
    display: block;
    text-align: center;
    margin-top: 15px;
    color: #8b5a83;
    font-weight: bold;
    text-decoration: none;
}


/* =========================
   EMPTY CART
========================= */

.empty-cart {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(120, 80, 110, 0.10);
}

.empty-cart h2 {
    color: #5d405c;
}

.empty-cart p {
    color: #777078;
}

.shop-btn {
    display: inline-block;
    margin-top: 15px;
    padding: 12px 25px;
    background: #8b5a83;
    color: white;
    text-decoration: none;
    border-radius: 25px;
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 768px) {

    .cart-layout {
        flex-direction: column;
    }

    .cart-summary {
        width: auto;
    }

    .cart-item {
        flex-direction: column;
    }

    .cart-item img {
        width: 100%;
        height: 300px;
    }

}

</style>

</head>


<body>


<!-- =========================
     HEADER
========================= -->

<header>

<nav>

    <div class="logo">

        <span>DRESORA</span>

        <small>
            DRESS RENTAL
        </small>

    </div>


    <ul>

        <li>
            <a href="index.php">
                Home
            </a>
        </li>

        <li>
            <a href="products.php">
                Shop
            </a>
        </li>

        <li>
            <a href="about.html">
                About
            </a>
        </li>

        <li>
            <a href="contact.html">
                Contact
            </a>
        </li>

        <li>

            <a href="cart.php">

                Cart

                <span class="cart-count">
                    <?php echo $totalItems; ?>
                </span>

            </a>

        </li>

    </ul>

</nav>

</header>


<!-- =========================
     MAIN
========================= -->

<main>

<section class="cart-page">

<h1>
    Shopping Cart
</h1>


<?php if (count($cart_items) === 0): ?>

    <!-- =========================
         EMPTY CART
    ========================= -->

    <div class="empty-cart">

        <h2>
            Your cart is empty
        </h2>

        <p>
            You haven't added any dresses
            to your cart yet.
        </p>

        <a href="products.php"
           class="shop-btn">

            Continue Shopping

        </a>

    </div>


<?php else: ?>


    <!-- =========================
         CART LAYOUT
    ========================= -->

    <div class="cart-layout">


        <!-- =========================
             CART ITEMS
        ========================= -->

        <div class="cart-items">


        <?php foreach ($cart_items as $item): ?>

            <?php

            $quantity =
                (int)$item["quantity"];

            $price =
                (float)$item["price"];

            $itemTotal =
                $price * $quantity;

            $selected =
                (int)$item["selected"] === 1;

            ?>


            <div class="cart-item
                <?php echo !$selected ? "unselected" : ""; ?>">


                <!-- =========================
                     SELECT CHECKBOX
                ========================= -->

                <form method="POST">

                    <input
                        type="hidden"
                        name="cart_item_id"
                        value="<?php echo $item["cart_item_id"]; ?>">

                    <input
                        type="hidden"
                        name="action"
                        value="toggle">

                    <input
                        type="hidden"
                        name="selected"
                        value="<?php echo $selected ? 0 : 1; ?>">

                    <input
                        type="checkbox"
                        class="select-item"
                        <?php echo $selected ? "checked" : ""; ?>
                        onchange="this.form.submit()"
                        title="Select this item">

                </form>


                <!-- =========================
                     IMAGE
                ========================= -->

                <img
                    src="<?php echo htmlspecialchars($item["image"]); ?>"
                    alt="<?php echo htmlspecialchars($item["product_name"]); ?>">


                <!-- =========================
                     INFORMATION
                ========================= -->

                <div class="cart-item-info">


                    <h3>
                        <?php
                        echo htmlspecialchars(
                            $item["product_name"]
                        );
                        ?>
                    </h3>


                    <p>

                        <strong>
                            Category:
                        </strong>

                        <?php
                        echo htmlspecialchars(
                            $item["category"]
                        );
                        ?>

                    </p>


                    <p>

                        <strong>
                            Size:
                        </strong>

                        <?php
                        echo htmlspecialchars(
                            $item["size"]
                        );
                        ?>

                    </p>


                    <p>

                        <strong>
                            Rental Start:
                        </strong>

                        <?php
                        echo htmlspecialchars(
                            $item["start_date"]
                        );
                        ?>

                    </p>


                    <p>

                        <strong>
                            Expected Return:
                        </strong>

                        <?php
                        echo htmlspecialchars(
                            $item["expected_return_date"]
                        );
                        ?>

                    </p>


                    <p>

                        <strong>
                            Rental Period:
                        </strong>

                        <?php
                        echo htmlspecialchars(
                            $item["rental_days"]
                        );
                        ?>

                        days

                    </p>


                    <div class="cart-item-price">

                        Rs.

                        <?php
                        echo number_format(
                            $itemTotal,
                            2
                        );
                        ?>

                    </div>


                    <!-- =========================
                         QUANTITY
                    ========================= -->

                    <div class="quantity-control">


                        <!-- DECREASE -->

                        <form method="POST">

                            <input
                                type="hidden"
                                name="cart_item_id"
                                value="<?php echo $item["cart_item_id"]; ?>">

                            <input
                                type="hidden"
                                name="action"
                                value="decrease">

                            <button
                                type="submit">

                                −

                            </button>

                        </form>


                        <span class="quantity-number">

                            <?php
                            echo $quantity;
                            ?>

                        </span>


                        <!-- INCREASE -->

                        <form method="POST">

                            <input
                                type="hidden"
                                name="cart_item_id"
                                value="<?php echo $item["cart_item_id"]; ?>">

                            <input
                                type="hidden"
                                name="action"
                                value="increase">

                            <button
                                type="submit">

                                +

                            </button>

                        </form>


                    </div>


                </div>


            </div>


        <?php endforeach; ?>


        </div>


        <!-- =========================
             ORDER SUMMARY
        ========================= -->

        <div class="cart-summary">


            <h2>
                Order Summary
            </h2>


            <div class="summary-row">

                <span>
                    Selected Items
                </span>

                <span>

                    <?php
                    echo $totalItems;
                    ?>

                </span>

            </div>


            <div class="summary-row">

                <span>
                    Subtotal
                </span>

                <span>

                    Rs.

                    <?php
                    echo number_format(
                        $subtotal,
                        2
                    );
                    ?>

                </span>

            </div>


            <div class="summary-row summary-total">

                <span>
                    Total
                </span>

                <span>

                    Rs.

                    <?php
                    echo number_format(
                        $subtotal,
                        2
                    );
                    ?>

                </span>

            </div>


            <a href="checkout.html"
               class="checkout-btn">

                Proceed to Checkout

            </a>


            <a href="products.php"
               class="continue-shopping">

                ← Continue Shopping

            </a>


        </div>


    </div>


<?php endif; ?>


</section>

</main>


<!-- =========================
     FOOTER
========================= -->

<footer>

<div class="footer-bottom">

    <p style="text-align:center;">

        © 2026 Dresora.
        All Rights Reserved.

    </p>

</div>

</footer>


</body>

</html>

<?php

$conn->close();

?>

