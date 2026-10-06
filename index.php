<?php

session_start();

/* ==========================================
   CHECK LOGIN STATUS
========================================== */

$loggedIn = isset($_SESSION["user_id"]);


/* ==========================================
   SELECT DASHBOARD ACCORDING TO ROLE
========================================== */

$dashboard = "customer/profile.php";

if ($loggedIn && isset($_SESSION["role"])) {

    if ($_SESSION["role"] === "admin") {
        $dashboard = "admin/dashboard.php";
    } else {
        $dashboard = "customer/profile.php";
    }
}


/* ==========================================
   LOGIN SUCCESS MESSAGE
========================================== */

$showLoginSuccess =
    isset($_SESSION["login_success"])
    && $_SESSION["login_success"] === true;


/*
 * Remove login success message after reading it.
 * Therefore it will show only once.
 */

unset($_SESSION["login_success"]);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Dresora | Online Dress Rental</title>

    <link rel="stylesheet" href="style.css">


    <!-- ==========================================
         LOGIN SUCCESS MODAL CSS
    =========================================== -->

    <style>

        .login-success-modal {

            display: none;

            position: fixed;

            top: 0;
            left: 0;

            width: 100%;
            height: 100%;

            background: rgba(0, 0, 0, 0.5);

            justify-content: center;
            align-items: center;

            z-index: 9999;
        }


        .login-success-box {

            background: white;

            width: 380px;

            padding: 35px;

            border-radius: 15px;

            text-align: center;

            box-shadow:
                0 8px 30px rgba(0, 0, 0, 0.2);
        }


        .login-success-box h2 {

            color: #5d405c;

            margin-bottom: 12px;
        }


        .login-success-box p {

            color: #666;

            margin-bottom: 25px;
        }


        .login-success-btn {

            background: #8b5a83;

            color: white;

            border: none;

            padding: 11px 30px;

            border-radius: 8px;

            cursor: pointer;

            font-size: 14px;
        }


        .login-success-btn:hover {

            background: #6f4569;
        }

    </style>

</head>


<body>


<!-- ==========================================
     NAVIGATION BAR
=========================================== -->

<header>

    <nav>

        <!-- LOGO -->

        <div class="logo">

            <span>DRESORA</span>

            <small>DRESS RENTAL</small>

        </div>


        <!-- SEARCH BOX -->

        <div class="search-box">

            <input
                type="text"
                id="homeSearchInput"
                placeholder="Search dresses..."
            >

            <button
                type="button"
                onclick="searchDresses()">

                Search

            </button>

        </div>


        <!-- NAVIGATION LINKS -->

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


            <!-- ==========================================
                 LOGIN / DASHBOARD
            =========================================== -->

            <li>

                <?php if ($loggedIn): ?>

                    <a href="<?php echo htmlspecialchars($dashboard); ?>">
                        Account
                    </a>

                <?php else: ?>

                    <a href="account.php">
                        Login
                    </a>

                <?php endif; ?>

            </li>


            <!-- CART -->

            <li>

                <a href="cart.php">

                    Cart

                    <span class="cart-count">
                        0
                    </span>

                </a>

            </li>

        </ul>

    </nav>

</header>



<!-- ==========================================
     HERO SECTION
=========================================== -->

<section class="hero">

    <div class="hero-overlay"></div>


    <div class="hero-content">

        <p class="hero-small-text">
            DRESORA DRESS RENTAL
        </p>


        <h1>

            Look Beautiful.<br>
            Rent Your Style.

        </h1>


        <p>

            Discover elegant dresses for weddings, parties,
            evenings and every special occasion.
            Find your perfect look without buying it.

        </p>


        <div class="hero-buttons">

            <a
                href="products.php"
                class="btn">

                Browse Dresses

            </a>


            <a
                href="#featured"
                class="btn secondary-btn">

                View Featured

            </a>

        </div>

    </div>


    <!-- HERO IMAGE -->

    <div class="hero-image">

        <img
            src="images/dress2.jpg"
            alt="Elegant dress from Dresora"
        >


        <div class="hero-image-card">

            <span>✨</span>

            <div>

                <strong>
                    Elegant Styles
                </strong>

                <small>
                    For Every Occasion
                </small>

            </div>

        </div>

    </div>

</section>



<!-- ==========================================
     CATEGORIES
=========================================== -->

<section class="categories">

    <p class="section-subtitle">
        FIND YOUR STYLE
    </p>


    <h2>
        Explore Our Collections
    </h2>


    <div class="category-container">


        <!-- Wedding -->

        <div class="category-card">

            <div class="category-icon">
                💍
            </div>

            <h3>
                Wedding Dresses
            </h3>

            <p>
                Elegant dresses for weddings and
                special occasions.
            </p>

            <a href="products.php?category=wedding">
                View Wedding Dresses
            </a>

        </div>



        <!-- Party -->

        <div class="category-card">

            <div class="category-icon">
                👗
            </div>

            <h3>
                Party Dresses
            </h3>

            <p>
                Stylish dresses for parties
                and celebrations.
            </p>

            <a href="products.php?category=party">
                View Party Dresses
            </a>

        </div>



        <!-- Evening -->

        <div class="category-card">

            <div class="category-icon">
                ✨
            </div>

            <h3>
                Evening Dresses
            </h3>

            <p>
                Beautiful dresses for evening events.
            </p>

            <a href="products.php?category=evening">
                View Evening Dresses
            </a>

        </div>



        <!-- Casual -->

        <div class="category-card">

            <div class="category-icon">
                🌸
            </div>

            <h3>
                Casual Dresses
            </h3>

            <p>
                Comfortable dresses for everyday occasions.
            </p>

            <a href="products.php?category=casual">
                View Casual Dresses
            </a>

        </div>



        <!-- Cocktail -->

        <div class="category-card">

            <div class="category-icon">
                🎉
            </div>

            <h3>
                Cocktail Dresses
            </h3>

            <p>
                Modern dresses for parties and dinner events.
            </p>

            <a href="products.php?category=cocktail">
                View Cocktail Dresses
            </a>

        </div>



        <!-- Formal -->

        <div class="category-card">

            <div class="category-icon">
                👑
            </div>

            <h3>
                Formal Dresses
            </h3>

            <p>
                Elegant outfits for formal occasions.
            </p>

            <a href="products.php?category=formal">
                View Formal Dresses
            </a>

        </div>

    </div>

</section>



<!-- ==========================================
     FEATURED DRESSES
=========================================== -->

<section
    class="featured"
    id="featured">


    <p class="section-subtitle">
        OUR COLLECTION
    </p>


    <h2>
        Featured Dresses
    </h2>


    <p class="section-description">

        Discover some of our most popular
        dresses available for rental.

    </p>


    <div class="dress-container">


        <!-- DRESS 01 -->

        <div class="dress-card">

            <div class="dress-image">

                <img
                    src="images/party1.jpg"
                    alt="Classic Party Dress"
                >

            </div>


            <div class="dress-info">

                <span class="dress-category">
                    Party Wear
                </span>


                <h3>
                    Classic Party Dress
                </h3>


                <p class="price">

                    Rs. 3,000

                    <span>
                        / rental
                    </span>

                </p>


                <a
                    href="product-details.php?id=1"
                    class="btn">

                    View Details

                </a>

            </div>

        </div>



        <!-- DRESS 02 -->

        <div class="dress-card">

            <div class="dress-image">

                <img
                    src="images/party2.jpg"
                    alt="Sparkle Party Dress"
                >

            </div>


            <div class="dress-info">

                <span class="dress-category">
                    Party Wear
                </span>


                <h3>
                    Sparkle Party Dress
                </h3>


                <p class="price">

                    Rs. 3,200

                    <span>
                        / rental
                    </span>

                </p>


                <a
                    href="product-details.php?id=2"
                    class="btn">

                    View Details

                </a>

            </div>

        </div>



        <!-- DRESS 03 -->

        <div class="dress-card">

            <div class="dress-image">

                <img
                    src="images/party3.jpg"
                    alt="Rose Party Dress"
                >

            </div>


            <div class="dress-info">

                <span class="dress-category">
                    Party Wear
                </span>


                <h3>
                    Rose Party Dress
                </h3>


                <p class="price">

                    Rs. 2,800

                    <span>
                        / rental
                    </span>

                </p>


                <a
                    href="product-details.php?id=3"
                    class="btn">

                    View Details

                </a>

            </div>

        </div>

    </div>

</section>



<!-- ==========================================
     WHY CHOOSE DRESORA
=========================================== -->

<section class="why-dresora">

    <div class="why-content">

        <p class="section-subtitle">
            WHY DRESORA?
        </p>


        <h2>

            Your Perfect Look,
            Made Simple

        </h2>


        <p>

            Dresora makes finding and renting
            your favourite dress simple,
            convenient, and affordable.

        </p>

    </div>


    <div class="benefits">


        <!-- Benefit 1 -->

        <div class="benefit-card">

            <div class="benefit-icon">
                ♡
            </div>


            <h3>
                Affordable Rental
            </h3>


            <p>
                Enjoy beautiful dresses
                without buying them.
            </p>

        </div>



        <!-- Benefit 2 -->

        <div class="benefit-card">

            <div class="benefit-icon">
                ✦
            </div>


            <h3>
                Quality Dresses
            </h3>


            <p>
                Choose from stylish dresses
                for different occasions.
            </p>

        </div>



        <!-- Benefit 3 -->

        <div class="benefit-card">

            <div class="benefit-icon">
                ✓
            </div>


            <h3>
                Easy Booking
            </h3>


            <p>
                Select your dress and
                reserve it easily.
            </p>

        </div>

    </div>

</section>



<!-- ==========================================
     PROMOTIONAL SECTION
=========================================== -->

<section class="promotion">

    <div class="promotion-content">

        <p class="section-subtitle">
            SPECIAL RENTAL
        </p>


        <h2>
            Dress for Every Occasion
        </h2>


        <p>

            Whether it is a wedding, party,
            or special event, find a dress
            that matches your style.

        </p>


        <a
            href="products.php"
            class="btn">

            Explore Collection

        </a>

    </div>

</section>



<!-- ==========================================
     PROFESSIONAL FOOTER
=========================================== -->

<footer class="professional-footer">

    <div class="footer-container">


        <!-- BRAND -->

        <div class="footer-column footer-brand">

            <h2>
                DRESORA
            </h2>


            <p class="footer-tagline">
                DRESS RENTAL
            </p>


            <p>

                Find your perfect dress for every special
                occasion. Stylish, affordable and easy to rent.

            </p>


            <div class="footer-social">

                <a href="#" aria-label="Instagram">
                    Instagram
                </a>


                <a href="#" aria-label="Facebook">
                    Facebook
                </a>


                <a href="#" aria-label="TikTok">
                    TikTok
                </a>

            </div>

        </div>



        <!-- QUICK LINKS -->

        <div class="footer-column">

            <h3>
                Quick Links
            </h3>


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
                        About Us
                    </a>
                </li>


                <li>
                    <a href="contact.html">
                        Contact Us
                    </a>
                </li>


                <li>
                    <a href="cart.php">
                        My Cart
                    </a>
                </li>

            </ul>

        </div>



        <!-- CUSTOMER SERVICE -->

        <div class="footer-column">

            <h3>
                Customer Service
            </h3>


            <ul>

                <li>
                    <a href="contact.html">
                        Help & Support
                    </a>
                </li>


                <li>
                    <a href="contact.html">
                        Rental Information
                    </a>
                </li>


                <li>
                    <a href="#">
                        Rental Policy
                    </a>
                </li>


                <li>
                    <a href="#">
                        Privacy Policy
                    </a>
                </li>


                <li>
                    <a href="#">
                        Terms & Conditions
                    </a>
                </li>

            </ul>

        </div>



        <!-- CONTACT -->

        <div class="footer-column footer-contact">

            <h3>
                Contact Us
            </h3>


            <p>

                <strong>
                    Email
                </strong>

                <br>

                info@dresora.com

            </p>


            <p>

                <strong>
                    Phone
                </strong>

                <br>

                +94 77 123 4567

            </p>


            <p>

                <strong>
                    Location
                </strong>

                <br>

                Sri Lanka

            </p>

        </div>

    </div>



    <!-- NEWSLETTER / CTA -->

    <div class="footer-newsletter">

        <div>

            <h3>
                Find Your Perfect Dress
            </h3>


            <p>

                Explore our collection and rent
                your favourite style today.

            </p>

        </div>


        <a
            href="products.php"
            class="footer-shop-btn">

            Explore Dresses →

        </a>

    </div>



    <!-- BOTTOM -->

    <div class="footer-bottom">

        <p>
            © 2026 DRESORA. All Rights Reserved.
        </p>


        <p>
            Online Dress Rental
        </p>

    </div>

</footer>



<!-- ==========================================
     LOGIN SUCCESS MODAL
=========================================== -->

<?php if ($showLoginSuccess): ?>

<div
    id="loginSuccessModal"
    class="login-success-modal">

    <div class="login-success-box">

        <h2>
            Login Successful!
        </h2>


        <p>
            Welcome back to DRESORA.
        </p>


        <button
            class="login-success-btn"
            onclick="closeLoginSuccess()">

            OK

        </button>

    </div>

</div>


<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        document
            .getElementById("loginSuccessModal")
            .style.display = "flex";

    }
);


function closeLoginSuccess() {

    document
        .getElementById("loginSuccessModal")
        .style.display = "none";

}

</script>

<?php endif; ?>



<!-- ==========================================
     JAVASCRIPT
=========================================== -->

<script>


/* ==========================================
   HOME PAGE SEARCH
========================================== */

function searchDresses() {

    const searchInput =
        document.getElementById("homeSearchInput");


    const searchValue =
        searchInput.value.trim();


    if (searchValue === "") {

        alert(
            "Please enter a dress name to search."
        );

        return;
    }


    window.location.href =
        "products.php?search=" +
        encodeURIComponent(searchValue);

}



/* ==========================================
   SEARCH USING ENTER KEY
========================================== */

document
    .getElementById("homeSearchInput")
    .addEventListener(
        "keypress",
        function(event) {

            if (event.key === "Enter") {

                searchDresses();

            }

        }
    );



/* ==========================================
   UPDATE CART COUNT
========================================== */

function updateCartCount() {

    const cart =
        JSON.parse(
            localStorage.getItem("cart")
        ) || [];


    const totalQuantity =
        cart.reduce(
            function(total, item) {

                return total +
                    Number(
                        item.quantity || 1
                    );

            },
            0
        );


    const cartCounts =
        document.querySelectorAll(
            ".cart-count"
        );


    cartCounts.forEach(
        function(count) {

            count.textContent =
                totalQuantity;

        }
    );

}



/* ==========================================
   INITIAL LOAD
========================================== */

updateCartCount();

</script>


</body>

</html>