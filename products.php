<?php

session_start();

require_once "config/database.php";

/* =========================
   LOAD ADMIN ADDED DRESSES
========================= */

$dbProducts = [];

$sql = "SELECT 
            d.dress_id,
            d.dress_name,
            d.description,
            d.size,
            d.colour,
            d.rental_price,
            d.stock_quantity,
            d.image_url,
            c.category_name
        FROM dresses d
        LEFT JOIN categories c
            ON d.category_id = c.category_id
        ORDER BY d.created_at DESC";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $dbProducts[] = $row;
    }

}


/* =========================
   CART COUNT
========================= */

$totalCartItems = 0;

if (isset($_SESSION["user_id"]) && $_SESSION["role"] !== "admin") {

    $user_id = $_SESSION["user_id"];

    $cartSql = "SELECT COALESCE(SUM(quantity), 0) AS total
                FROM cart_items
                WHERE user_id = ?";

    $cartStmt = $conn->prepare($cartSql);

    if ($cartStmt) {

        $cartStmt->bind_param("i", $user_id);
        $cartStmt->execute();

        $cartResult = $cartStmt->get_result();

        if ($cartRow = $cartResult->fetch_assoc()) {
            $totalCartItems = (int)$cartRow["total"];
        }

        $cartStmt->close();
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dresora - Shop</title>

    <link rel="stylesheet" href="style.css">

    <style>

        /* =========================
           PRODUCTS PAGE
        ========================= */

        .products-page {
            max-width: 1200px;
            margin: auto;
            padding: 80px 30px;
        }

        .products-heading {
            text-align: center;
            margin-bottom: 40px;
        }

        .products-heading h1 {
            color: #5d405c;
            font-size: 40px;
            margin-bottom: 12px;
        }

        .products-heading p:last-child {
            color: #777078;
        }


        /* =========================
           SEARCH
        ========================= */

        .shop-search {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .shop-search input {
            width: 350px;
            padding: 12px 18px;
            border: 1px solid #ead9e6;
            border-radius: 25px;
            outline: none;
            background: #fffafc;
        }

        .shop-search input:focus {
            border-color: #a66b9b;
        }


        /* =========================
           FILTER + SORT
        ========================= */

        .filter-sort {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;

            margin-bottom: 40px;
            padding: 20px;

            background: #f8f0f6;
            border-radius: 15px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            color: #5d405c;
            font-weight: bold;
        }

        .filter-group select {
            padding: 10px 15px;

            border: 1px solid #ead9e6;
            border-radius: 20px;

            background: #ffffff;
            color: #5d405c;

            outline: none;
            cursor: pointer;
        }

        .filter-group select:focus {
            border-color: #a66b9b;
        }


        /* =========================
           PRODUCTS GRID
        ========================= */

        .products-container {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 25px;
        }


        /* =========================
           PRODUCT CARD
        ========================= */

        .product-card {
            background: #ffffff;

            border: 1px solid #ead9e6;
            border-radius: 15px;

            overflow: hidden;

            box-shadow:
                0 6px 20px rgba(120, 80, 110, 0.07);

            transition: 0.3s;
        }

        .product-card:hover {
            transform: translateY(-7px);

            box-shadow:
                0 12px 28px rgba(120, 80, 110, 0.14);
        }


        /* =========================
           PRODUCT IMAGE
        ========================= */

        .product-image {
            width: 100%;
            height: 350px;

            background: #f3e8f1;

            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;

            object-fit: cover;

            display: block;

            transition: 0.4s;
        }

        .product-card:hover
        .product-image img {
            transform: scale(1.04);
        }


        /* =========================
           PRODUCT INFORMATION
        ========================= */

        .product-info {
            padding: 20px;
        }

        .product-category {
            color: #a66b9b;

            font-size: 11px;

            font-weight: bold;

            letter-spacing: 1.5px;

            text-transform: uppercase;
        }

        .product-info h3 {
            color: #5d405c;

            font-size: 19px;

            margin: 8px 0;
        }

        .product-price {
            color: #8b5a83;

            font-size: 17px;

            font-weight: bold;

            margin-bottom: 15px;
        }

        .product-price span {
            color: #8f858d;

            font-size: 13px;

            font-weight: normal;
        }


        /* =========================
           VIEW DETAILS BUTTON
        ========================= */

        .product-btn {
            display: block;

            text-align: center;

            padding: 11px 20px;

            background: #8b5a83;

            color: #ffffff;

            border-radius: 22px;

            transition: 0.3s;
        }

        .product-btn:hover {
            background: #6f4568;

            transform: translateY(-2px);
        }


        /* =========================
           NO PRODUCTS
        ========================= */

        .no-products {
            display: none;

            text-align: center;

            color: #777078;

            padding: 50px;

            grid-column: 1 / -1;
        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {

            .products-container {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }


        @media (max-width: 600px) {

            .products-page {
                padding: 50px 20px;
            }

            .products-heading h1 {
                font-size: 30px;
            }

            .filter-sort {
                flex-direction: column;

                align-items: stretch;
            }

            .filter-group {
                justify-content: space-between;
            }

            .shop-search input {
                width: 100%;
            }

            .products-container {
                grid-template-columns: 1fr;
            }

            .product-image {
                height: 380px;
            }

        }

    </style>

</head>


<body>


<!-- =========================
     NAVIGATION BAR
========================= -->

<header>

    
<nav>

    <div class="logo">
        <span>DRESORA</span>
        <small>DRESS RENTAL</small>
    </div>

    <ul>

        <li>
            <a href="/Dresora/index.php">
                Home
            </a>
        </li>

        <li>
            <a href="/Dresora/products.php">
                Shop
            </a>
        </li>

        <li>
            <a href="/Dresora/about.html">
                About
            </a>
        </li>

        <li>
            <a href="/Dresora/contact.html">
                Contact
            </a>
        </li>

        <li>
            <a href="/Dresora/cart.php">
                Cart
                <span class="cart-count">
    <?php echo $totalCartItems; ?>
</span>
            </a>
        </li>

    </ul>

</nav>



</header>



<!-- =========================
     MAIN CONTENT
========================= -->

<main>

<section class="products-page">


    <!-- PAGE HEADING -->

    <div class="products-heading">

        <p class="section-subtitle">
            DRESORA COLLECTION
        </p>

        <h1>
            Find Your Perfect Dress
        </h1>

        <p>
            Explore our collection of beautiful dresses
            available for rental.
        </p>

    </div>



    <!-- =========================
         SEARCH
    ========================= -->

    <div class="shop-search">

        <input
            type="text"
            id="searchInput"
            placeholder="Search dresses..."
            onkeyup="applyFilters()"
        >

    </div>



    <!-- =========================
         FILTER + SORT
    ========================= -->

    <div class="filter-sort">


        <!-- CATEGORY -->

        <div class="filter-group">

            <label for="categoryFilter">
                Category
            </label>

            <select
                id="categoryFilter"
                onchange="applyFilters()"
            >

                <option value="all">
                    All Dresses
                </option>

                <option value="party">
                    Party Dresses
                </option>

                <option value="wedding">
                    Wedding Dresses
                </option>

                <option value="evening">
                    Evening Dresses
                </option>

                <option value="casual">
                    Casual Dresses
                </option>

                <option value="cocktail">
                    Cocktail Dresses
                </option>

                <option value="formal">
                    Formal Dresses
                </option>

            </select>

        </div>



        <!-- SORT -->

        <div class="filter-group">

            <label for="sortFilter">
                Sort By
            </label>

            <select
                id="sortFilter"
                onchange="applyFilters()"
            >

                <option value="default">
                    Default
                </option>

                <option value="low-high">
                    Price: Low to High
                </option>

                <option value="high-low">
                    Price: High to Low
                </option>

                

            </select>

        </div>

    </div>



    <!-- =========================
         PRODUCTS
    ========================= -->

    <div
        class="products-container"
        id="productsContainer"
    >

<?php if (!empty($dbProducts)): ?>

    <?php foreach ($dbProducts as $dress): ?>

        <?php
            $categoryName = trim($dress["category_name"] ?? "");

            $categoryValue = strtolower($categoryName);

            if (str_ends_with($categoryValue, " dress")) {
                $categoryValue = substr($categoryValue, 0, -6);
            }

            $image = trim($dress["image_url"] ?? "");

            if ($image === "") {
                $image = "images/no-image.jpg";
            }
        ?>

        <div class="product-card"
             data-category="<?php echo htmlspecialchars($categoryValue); ?>"
             data-name="<?php echo htmlspecialchars($dress["dress_name"]); ?>"
             data-price="<?php echo (float)$dress["rental_price"]; ?>">

            <div class="product-image">

                <img
                    src="<?php echo htmlspecialchars($image); ?>"
                    alt="<?php echo htmlspecialchars($dress["dress_name"]); ?>"
                    onerror="this.onerror=null; this.src='images/no-image.jpg';"
                >

            </div>

            <div class="product-info">

                <span class="product-category">
                    <?php echo htmlspecialchars($categoryName); ?>
                </span>

                <h3>
                    <?php echo htmlspecialchars($dress["dress_name"]); ?>
                </h3>

                <p class="product-price">
                    Rs. <?php echo number_format((float)$dress["rental_price"], 2); ?>
                    <span>/ day</span>
                </p>

                <a
                    href="product-details.php?db_id=<?php echo (int)$dress["dress_id"]; ?>"
                    class="product-btn"
                >
                    View Details
                </a>

            </div>

        </div>

    <?php endforeach; ?>

<?php endif; ?>


<p
    class="no-products"
    id="noProducts"
>
    No dresses found.
</p>
       

<?php if (!empty($dbProducts)): ?>

<?php foreach ($dbProducts as $dress): ?>

<?php
    $categoryName = trim($dress["category_name"] ?? "");
    $categoryValue = strtolower($categoryName);

    if (str_ends_with($categoryValue, " dress")) {
        $categoryValue = substr($categoryValue, 0, -6);
    }

    $image = trim($dress["image_url"] ?? "");

    if ($image === "") {
        $image = "images/no-image.jpg";
    
    }
    
?>


<div class="product-card"
     data-category="<?php echo htmlspecialchars($categoryValue); ?>"
     data-name="<?php echo htmlspecialchars($dress["dress_name"] ?? ""); ?>"
     data-price="<?php echo (float)($dress["rental_price"] ?? 0); ?>">

    <div class="product-image">

        <img
            src="<?php echo htmlspecialchars($image); ?>"
            alt="<?php echo htmlspecialchars($dress["dress_name"] ?? "Dress"); ?>"
            onerror="this.onerror=null; this.src='images/no-image.jpg';"
        >

    </div>

    <div class="product-info">

        <span class="product-category">
            <?php echo htmlspecialchars($categoryName); ?>
        </span>

        <h3>
            <?php echo htmlspecialchars($dress["dress_name"] ?? ""); ?>
        </h3>

        <p class="product-price">
            Rs.
            <?php echo number_format((float)($dress["rental_price"] ?? 0), 2); ?>
            <span>/ day</span>
        </p>

       <a
    href="product-details.php?db_id=<?php echo (int)$dress['dress_id']; ?>"
    class="product-btn"
>
    View Details
</a>
    </div>

</div>

<?php endforeach; ?>

<?php endif; ?>

<!-- NO PRODUCTS MESSAGE -->




        <!-- NO PRODUCTS MESSAGE -->

        <p
            class="no-products"
            id="noProducts"
        >
            No dresses found.
        </p>


    </div>

</section>

</main>





            
           


   <!-- =========================
     PROFESSIONAL FOOTER
========================== -->

<footer class="professional-footer">

    <div class="footer-container">

        <!-- Brand -->
        <div class="footer-column footer-brand">

            <h2>DRESORA</h2>

            <p class="footer-tagline">
                DRESS RENTAL
            </p>

            <p>
                Find your perfect dress for every special
                occasion. Stylish, affordable and easy to rent.
            </p>

            <div class="footer-social">
                <a href="#" aria-label="Instagram">Instagram</a>
                <a href="#" aria-label="Facebook">Facebook</a>
                <a href="#" aria-label="TikTok">TikTok</a>
            </div>

        </div>


        <!-- Quick Links -->
        <div class="footer-column">

            <h3>Quick Links</h3>

            <ul>
                <li>
                    <a href="index.php">Home</a>
                </li>

                <li>
                    <a href="products.php">Shop</a>
                </li>

                <li>
                    <a href="about.html">About Us</a>
                </li>

                <li>
                    <a href="contact.html">Contact Us</a>
                </li>

                <li>
                    <a href="cart.php">My Cart</a>
                </li>
            </ul>

        </div>


        <!-- Customer Service -->
        <div class="footer-column">

            <h3>Customer Service</h3>

            <ul>
                <li>
                    <a href="contact.html">Help & Support</a>
                </li>

                <li>
                    <a href="contact.html">Rental Information</a>
                </li>

                <li>
                    <a href="#">Rental Policy</a>
                </li>

                <li>
                    <a href="#">Privacy Policy</a>
                </li>

                <li>
                    <a href="#">Terms & Conditions</a>
                </li>
            </ul>

        </div>


        <!-- Contact -->
        <div class="footer-column footer-contact">

            <h3>Contact Us</h3>

            <p>
                <strong>Email</strong><br>
                info@dresora.com
            </p>

            <p>
                <strong>Phone</strong><br>
                +94 77 123 4567
            </p>

            <p>
                <strong>Location</strong><br>
                Sri Lanka
            </p>

        </div>

    </div>


    <!-- Newsletter / CTA -->
    <div class="footer-newsletter">

        <div>
            <h3>Find Your Perfect Dress</h3>

            <p>
                Explore our collection and rent your favourite style today.
            </p>
        </div>

        <a href="products.php" class="footer-shop-btn">
            Explore Dresses →
        </a>

    </div>


    <!-- Bottom -->
    <div class="footer-bottom">

        <p>
            © 2026 DRESORA. All Rights Reserved.
        </p>

        <p>
            Online Dress Rental
        </p>

    </div>

</footer>


<!-- =========================
     JAVASCRIPT
========================= -->

<script>

/* =========================
   FILTER PRODUCTS
========================= */

function applyFilters() {

    const category =
        document.getElementById("categoryFilter").value;

    const sort =
        document.getElementById("sortFilter").value;

    const search =
        document.getElementById("searchInput")
            .value
            .toLowerCase()
            .trim();

    const container =
        document.getElementById("productsContainer");

    const noProducts =
        document.getElementById("noProducts");

    const products =
        Array.from(
            container.querySelectorAll(".product-card")
        );

    let visibleProducts = [];


    /* =========================
       FILTER PRODUCTS
    ========================= */

    products.forEach(function(product) {

        const productCategory =
            product.dataset.category.toLowerCase();

        const productName =
            product.dataset.name.toLowerCase();


        const categoryMatch =
            category === "all" ||
            productCategory === category.toLowerCase();


        const searchMatch =
            productName.includes(search) ||
            productCategory.includes(search);


        if (categoryMatch && searchMatch) {

            product.style.display = "block";

            visibleProducts.push(product);

        } else {

            product.style.display = "none";

        }

    });


    /* =========================
       SORT PRODUCTS
    ========================= */

    if (sort === "low-high") {

        visibleProducts.sort(function(a, b) {

            return Number(a.dataset.price) -
                   Number(b.dataset.price);

        });

    }

    else if (sort === "high-low") {

        visibleProducts.sort(function(a, b) {

            return Number(b.dataset.price) -
                   Number(a.dataset.price);

        });

    }

    else if (sort === "a-z") {

        visibleProducts.sort(function(a, b) {

            return a.dataset.name.localeCompare(
                b.dataset.name
            );

        });

    }

    else if (sort === "z-a") {

        visibleProducts.sort(function(a, b) {

            return b.dataset.name.localeCompare(
                a.dataset.name
            );

        });

    }


    /* =========================
       REORDER PRODUCTS
    ========================= */

    visibleProducts.forEach(function(product) {

        container.appendChild(product);

    });


    /* =========================
       NO PRODUCTS MESSAGE
    ========================= */

    if (visibleProducts.length === 0) {

        noProducts.style.display = "block";

    } else {

        noProducts.style.display = "none";

    }

}


/* =========================
   READ URL PARAMETERS
========================= */

const urlParams =
    new URLSearchParams(
        window.location.search
    );


/* =========================
   CATEGORY FROM HOME PAGE
========================= */

const selectedCategory =
    urlParams.get("category");


if (selectedCategory) {

    const categoryFilter =
        document.getElementById("categoryFilter");

    categoryFilter.value =
        selectedCategory;

}


/* =========================
   SEARCH FROM HOME PAGE
========================= */

const selectedSearch =
    urlParams.get("search");


if (selectedSearch) {

    const searchInput =
        document.getElementById("searchInput");


    /* Put search text inside search box */

    searchInput.value =
        selectedSearch;


    /* Apply search */

    applyFilters();

}


/* =========================
   CATEGORY ONLY
========================= */

else if (selectedCategory) {

    applyFilters();

}








</script>

</body>

</html>
