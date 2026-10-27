
<?php
session_start();
require_once "config/database.php";

/* =========================================
   HELPER: SAFE HTML OUTPUT
========================================= */
function e($value)
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* =========================================
   CUSTOMER / ADMIN STATUS
========================================= */
$isLoggedIn = isset($_SESSION["user_id"]);
$isAdmin = isset($_SESSION["role"]) &&
           strtolower((string)$_SESSION["role"]) === "admin";

$userId = $isLoggedIn ? (int)$_SESSION["user_id"] : 0;

// LOAD ALL CATEGORIES FROM DATABASE
$shopCategories = [];

$categorySql = "
    SELECT category_id, category_name
    FROM categories
    ORDER BY category_name ASC
";

$categoryResult = $conn->query($categorySql);

if ($categoryResult) {
    while ($categoryRow = $categoryResult->fetch_assoc()) {
        $shopCategories[] = $categoryRow;
    }
}

/* =========================================
   LOAD DRESSES
========================================= */
$dbProducts = [];

$sql = "
    SELECT
        d.dress_id,
        d.dress_name,
        d.description,
        d.size,
        d.colour,
        d.rental_price,
        d.image_url,
        c.category_name
    FROM dresses d
    LEFT JOIN categories c
        ON d.category_id = c.category_id
    ORDER BY d.created_at DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dbProducts[] = $row;
    }
}

/* =========================================
   LOAD CUSTOMER WISHLIST
========================================= */
$wishlistDressIds = [];

if ($isLoggedIn && !$isAdmin) {
    $stmt = $conn->prepare("
        SELECT wi.dress_id
        FROM wishlist w
        INNER JOIN wishlist_items wi
            ON w.wishlist_id = wi.wishlist_id
        WHERE w.user_id = ?
    ");

    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $wishlistResult = $stmt->get_result();

        while ($row = $wishlistResult->fetch_assoc()) {
            $wishlistDressIds[] = (int)$row["dress_id"];
        }

        $stmt->close();
    }
}

/* =========================================
   CART COUNT
========================================= */
$totalCartItems = 0;

if ($isLoggedIn && !$isAdmin) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(quantity), 0) AS total
        FROM cart_items
        WHERE user_id = ?
    ");

    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $cartResult = $stmt->get_result();

        if ($cartRow = $cartResult->fetch_assoc()) {
            $totalCartItems = (int)$cartRow["total"];
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Shop Dresses | DRESORA</title>
    <meta name="description" content="Discover beautiful dresses for your special occasions with DRESORA dress rental.">

    <link rel="stylesheet" href="style.css">

    <style>
        :root {
            --cream: #fffafc;
            --white: #ffffff;
            --plum: #5d405c;
            --mauve: #8b5a83;
            --rose: #a66b9b;
            --text: #4d414b;
            --muted: #827681;
            --border: #eee3eb;
            --soft: #f9f2f7;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--cream);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        a {
            text-decoration: none;
        }

        /* ===============================
           HEADER
        =============================== */

        .shop-announcement {
            padding: 9px 15px;
            background: var(--plum);
            color: #fff;
            text-align: center;
            font-size: 11px;
            letter-spacing: 1.5px;
        }

        .shop-header {
            position: relative;
            z-index: 10;
            background: rgba(255, 250, 252, .97);
            border-bottom: 1px solid var(--border);
        }

        .shop-nav {
            max-width: 1380px;
            min-height: 88px;
            margin: auto;
            padding: 15px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
        }

        .shop-logo {
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            color: var(--plum);
        }

        .shop-logo strong {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 28px;
            font-weight: 500;
            letter-spacing: 3px;
        }

        .shop-logo span {
            margin-top: 4px;
            font-size: 9px;
            letter-spacing: 3px;
            text-align: center;
        }

        .shop-nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .shop-nav-links a {
            color: var(--text);
            font-size: 13px;
            transition: color .2s;
        }

        .shop-nav-links a:hover,
        .shop-nav-links a.active {
            color: var(--mauve);
        }

        .shop-nav-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-shrink: 0;
        }

        .shop-nav-actions a {
            color: var(--plum);
            font-size: 13px;
        }

        .shop-cart-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 19px;
            height: 19px;
            padding: 0 5px;
            border-radius: 50%;
            background: var(--plum);
            color: white;
            font-size: 10px;
            margin-left: 3px;
        }

        /* ===============================
           PAGE LAYOUT
        =============================== */

        .products-page {
            max-width: 1380px;
            margin: auto;
            padding: 58px 32px 85px;
        }

        .products-heading {
            text-align: center;
            margin-bottom: 38px;
        }

        .section-subtitle {
            margin: 0 0 13px;
            color: var(--rose);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 3px;
        }

        .products-heading h1 {
            margin: 0 0 14px;
            color: var(--plum);
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(32px, 4vw, 46px);
            font-weight: 500;
            line-height: 1.2;
        }

        .products-heading > p:last-child {
            max-width: 550px;
            margin: 0 auto;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .heading-decoration {
            width: 44px;
            height: 2px;
            margin: 22px auto 0;
            background: var(--rose);
        }

        /* ===============================
           SEARCH
        =============================== */

        .shop-search {
            display: flex;
            justify-content: center;
            margin-bottom: 26px;
        }

        .search-wrap {
            position: relative;
            width: min(100%, 460px);
        }

        .search-wrap input {
            width: 100%;
            padding: 14px 20px 14px 45px;
            border: 1px solid var(--border);
            border-radius: 30px;
            background: white;
            color: var(--plum);
            font-size: 13px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .search-wrap input:focus {
            border-color: var(--rose);
            box-shadow: 0 0 0 4px rgba(166, 107, 155, .09);
        }

        .search-icon {
            position: absolute;
            top: 50%;
            left: 18px;
            color: var(--muted);
            font-size: 18px;
            transform: translateY(-50%);
            pointer-events: none;
        }

        /* ===============================
           FILTER / SORT
        =============================== */

        .filter-sort {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 30px;
            padding: 16px 19px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .filter-group label {
            color: var(--plum);
            font-size: 12px;
            font-weight: 600;
        }

        .filter-group select {
            max-width: 100%;
            padding: 10px 13px;
            border: 1px solid var(--border);
            border-radius: 7px;
            background: var(--cream);
            color: var(--plum);
            font-size: 12px;
            outline: none;
            cursor: pointer;
        }

        .results-count {
            color: var(--muted);
            font-size: 12px;
        }

        /* ===============================
           PRODUCT GRID
        =============================== */

        .products-container {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            align-items: start;
            gap: 28px 20px;
        }

        .product-card {
            min-width: 0;
            overflow: hidden;
            border: 1px solid #f0e7ee;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 4px 16px rgba(70, 40, 65, .035);
            transition: transform .3s ease, box-shadow .3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 13px 30px rgba(70, 40, 65, .10);
        }

        /* ===============================
           PRODUCT IMAGE + HEART
        =============================== */

        .product-image {
            position: relative;
            width: 100%;
            height: 340px;
            overflow: hidden;
            background: var(--soft);
        }

        .product-image > img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .5s ease;
        }

        .product-card:hover .product-image > img {
            transform: scale(1.045);
        }

        .wishlist-form {
            position: absolute;
            top: 13px;
            right: 13px;
            z-index: 2;
            margin: 0;
        }

        .wishlist-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            padding: 0;
            border: 1px solid rgba(230, 216, 228, .9);
            border-radius: 50%;
            background: rgba(255, 255, 255, .96);
            color: var(--plum);
            font-family: Arial, sans-serif;
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 3px 12px rgba(50, 30, 45, .08);
            transition: transform .2s, background .2s, color .2s;
        }

        .wishlist-btn:hover:not(:disabled) {
            transform: scale(1.08);
            background: var(--plum);
            color: #fff;
        }

        .wishlist-btn.is-added,
        .wishlist-btn:disabled {
            background: #fff;
            color: #b34f79;
            cursor: default;
            opacity: 1;
        }

        /* ===============================
           PRODUCT INFORMATION
        =============================== */

        .product-info {
            padding: 18px 17px 20px;
        }

        .product-category {
            display: block;
            margin-bottom: 8px;
            color: var(--rose);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .product-info h3 {
            min-height: 43px;
            margin: 0 0 10px;
            color: var(--plum);
            font-family: Georgia, "Times New Roman", serif;
            font-size: 17px;
            font-weight: 500;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .product-price {
            margin: 0 0 17px;
            color: var(--mauve);
            font-size: 15px;
            font-weight: 700;
        }

        .product-price span {
            color: var(--muted);
            font-size: 11px;
            font-weight: 400;
        }

        .product-btn {
            display: block;
            padding: 12px 14px;
            border: 1px solid var(--mauve);
            border-radius: 5px;
            background: var(--mauve);
            color: white;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .3px;
            transition: background .2s, border-color .2s;
        }

        .product-btn:hover {
            border-color: var(--plum);
            background: var(--plum);
        }

        /* ===============================
           EMPTY RESULTS
        =============================== */

        .no-products {
            display: none;
            grid-column: 1 / -1;
            padding: 65px 20px;
            color: var(--muted);
            text-align: center;
            line-height: 1.8;
        }

        .no-products strong {
            display: block;
            margin-bottom: 8px;
            color: var(--plum);
            font-family: Georgia, "Times New Roman", serif;
            font-size: 23px;
            font-weight: 500;
        }

    



       

       

      
/* ===============================
   PROFESSIONAL FOOTER
=============================== */

.shop-footer {
    margin-top: 30px;
    background: #f5edf3;
    color: #5d405c;
    border-top: 1px solid #ead9e6;
}

.shop-footer .footer-container {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr 1fr;
    gap: 40px;
    max-width: 1380px;
    margin: 0 auto;
    padding: 60px 35px 45px;
}

.shop-footer .footer-column {
    min-width: 0;
}

.shop-footer .footer-brand h2 {
    margin: 0;
    color: #5d405c;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 30px;
    font-weight: 500;
    letter-spacing: 3px;
}

.shop-footer .footer-tagline {
    margin: 7px 0 20px;
    color: #8b5a83;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 3px;
}

.shop-footer .footer-description {
    max-width: 290px;
    margin: 0;
    color: #625663;
    font-size: 14px;
    line-height: 1.9;
}

.shop-footer .footer-column h3 {
    margin: 5px 0 22px;
    color: #5d405c;
    font-size: 15px;
    font-weight: 700;
}

.shop-footer .footer-column ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.shop-footer .footer-column li {
    margin: 0 0 14px;
}

.shop-footer .footer-column a {
    display: inline-block;
    color: #625663;
    font-size: 13px;
    line-height: 1.7;
    text-decoration: none;
    transition: color .2s, padding-left .2s;
}

.shop-footer .footer-column a:hover {
    color: #8b5a83;
    padding-left: 4px;
}

.shop-footer .footer-column p {
    margin: 0 0 13px;
    color: #625663;
    font-size: 13px;
    line-height: 1.8;
    overflow-wrap: anywhere;
}

.shop-footer .footer-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 20px 35px;
    border-top: 1px solid #e4d5e1;
    color: #625663;
    font-size: 12px;
}

.shop-footer .footer-bottom p {
    margin: 0;
}

/* TABLET */

@media (max-width: 900px) {
    .shop-footer .footer-container {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 35px;
        padding: 45px 25px 35px;
    }
}

/* MOBILE */

@media (max-width: 480px) {
    .shop-footer .footer-container {
        grid-template-columns: 1fr 1fr;
        gap: 30px 18px;
        padding: 35px 18px;
    }

    .shop-footer .footer-brand {
        grid-column: 1 / -1;
    }

    .shop-footer .footer-brand h2 {
        font-size: 27px;
    }

    .shop-footer .footer-description {
        max-width: 100%;
    }

    .shop-footer .footer-column h3 {
        font-size: 14px;
        margin-bottom: 16px;
    }

    .shop-footer .footer-column a,
    .shop-footer .footer-column p {
        font-size: 12px;
    }

    .shop-footer .footer-bottom {
        flex-direction: column;
        text-align: center;
        padding: 18px;
        line-height: 1.7;
    }
}


       

        /* ===============================
           RESPONSIVE
        =============================== */

        @media (max-width: 1100px) {
            .products-container {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .shop-nav-links {
                gap: 17px;
            }

            .footer-container {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .shop-nav {
                min-height: auto;
                padding: 17px 20px;
                flex-wrap: wrap;
                gap: 16px;
            }

            .shop-logo strong {
                font-size: 23px;
            }

            .shop-nav-links {
                order: 3;
                width: 100%;
                justify-content: center;
                gap: 22px;
                flex-wrap: wrap;
            }

            .shop-nav-links a {
                font-size: 12px;
            }

            .shop-nav-actions {
                margin-left: auto;
            }

            .products-page {
                padding: 40px 18px 60px;
            }

            .products-container {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 18px 12px;
            }

            .product-image {
                height: 285px;
            }

            .product-info {
                padding: 14px 12px 15px;
            }

            .product-info h3 {
                font-size: 15px;
            }

            .filter-sort {
                flex-wrap: wrap;
                align-items: flex-start;
            }

            .filter-group {
                flex-wrap: wrap;
            }

            .results-count {
                width: 100%;
            }

            .footer-container {
                padding: 40px 22px 30px;
                gap: 28px 20px;
            }

            .footer-bottom {
                padding: 17px 20px;
            }
        }

        @media (max-width: 480px) {
            .shop-announcement {
                font-size: 9px;
                letter-spacing: 1px;
            }

            .shop-nav-links {
                gap: 15px;
            }

            .products-heading h1 {
                font-size: 31px;
            }

            .products-heading > p:last-child {
                font-size: 13px;
            }

            .product-image {
                height: 225px;
            }

            .wishlist-form {
                top: 8px;
                right: 8px;
            }

            .wishlist-btn {
                width: 35px;
                height: 35px;
                font-size: 22px;
            }

            .product-info h3 {
                min-height: 42px;
                font-size: 14px;
            }

            .product-category {
                font-size: 9px;
                letter-spacing: 1px;
            }

            .product-price {
                font-size: 13px;
            }

            .product-price span {
                display: block;
                margin-top: 4px;
            }

            .product-btn {
                padding: 10px 5px;
                font-size: 11px;
            }

            .filter-sort {
                padding: 13px;
            }

            .filter-group {
                width: 100%;
                justify-content: space-between;
            }

            .filter-group select {
                max-width: 68%;
            }

            .footer-container {
                grid-template-columns: 1fr 1fr;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<div class="shop-announcement">
    A LITTLE LUXURY FOR EVERY OCCASION
</div>

<header class="shop-header">
    <nav class="shop-nav" aria-label="Main navigation">

        <a class="shop-logo" href="index.php" aria-label="DRESORA Home">
            <strong>DRESORA</strong>
            <span>DRESS RENTAL</span>
        </a>

        <ul class="shop-nav-links">
            <li><a href="index.php">Home</a></li>
            <li><a class="active" href="products.php">Shop</a></li>
            <li><a href="about.html">About</a></li>
            <li><a href="contact.html">Contact</a></li>
             
        </ul>

        <div class="shop-nav-actions">
            <?php if ($isLoggedIn && !$isAdmin): ?>
                <a href="wishlist.php" aria-label="My wishlist" title="My wishlist">
                    <span aria-hidden="true">♥</span>
                </a>
            <?php endif; ?>

            <a href="cart.php" aria-label="Shopping cart">
                Cart
                <span class="shop-cart-count"><?php echo $totalCartItems; ?></span>
            </a>
        </div>

    </nav>
</header>

<main>
    <section class="products-page">

        <div class="products-heading">
            <p class="section-subtitle">THE DRESORA COLLECTION</p>
            <h1>Find Your Perfect Dress</h1>
            <p>
                Discover beautiful styles for your special moments.
                Find the dress you love and make every occasion memorable.
            </p>
            <div class="heading-decoration"></div>
        </div>

        <!-- SEARCH -->
        <div class="shop-search">
            <div class="search-wrap">
                <span class="search-icon" aria-hidden="true">&#8981;</span>
                <input
                    type="search"
                    id="searchInput"
                    placeholder="Search your favourite dress..."
                    aria-label="Search dresses"
                >
            </div>
        </div>

        <!-- FILTERS -->
        <div class="filter-sort">

            <div class="filter-group">
                <label for="categoryFilter">Category</label>

              <select id="categoryFilter">

    <option value="all">All Dresses</option>

    <?php foreach ($shopCategories as $category): ?>

        <?php
            $categoryName = trim($category["category_name"]);

            $categoryValue = strtolower($categoryName);

            $categoryValue = preg_replace(
                '/\s+dresses?$/i',
                '',
                $categoryValue
            );

            $categoryValue = trim($categoryValue);
        ?>

        <option value="<?php echo e($categoryValue); ?>">
            <?php echo e($categoryName); ?>
        </option>

    <?php endforeach; ?>

</select>
            </div>

            <div class="filter-group">
                <label for="sortFilter">Sort by</label>

                <select id="sortFilter">
                    <option value="default">Featured</option>
                    <option value="low-high">Price: Low to High</option>
                    <option value="high-low">Price: High to Low</option>
                 
                </select>
            </div>

            <p class="results-count" id="resultsCount" aria-live="polite"></p>
        </div>

        <!-- PRODUCTS -->
        <div class="products-container" id="productsContainer">

            <?php foreach ($dbProducts as $dress): ?>

                <?php
                $categoryName = trim((string)($dress["category_name"] ?? ""));
                $categoryValue = strtolower($categoryName);

                // Normalize category names such as "Party Dresses" to "party".
                $categoryValue = preg_replace('/\s+dresses?$/i', '', $categoryValue);
                $categoryValue = trim($categoryValue);

                $imageUrl = trim((string)($dress["image_url"] ?? ""));
                $images = $imageUrl !== ""
                    ? preg_split("/[,|]/", $imageUrl)
                    : [];

                $image = !empty($images[0])
                    ? trim($images[0])
                    : "images/no-image.jpg";

                $dressId = (int)$dress["dress_id"];

                $isInWishlist = in_array(
                    $dressId,
                    $wishlistDressIds,
                    true
                );
                ?>

                <article
                    class="product-card"
                    data-category="<?php echo e($categoryValue); ?>"
                    data-name="<?php echo e($dress["dress_name"] ?? ""); ?>"
                    data-price="<?php echo (float)($dress["rental_price"] ?? 0); ?>"
                >

                    <div class="product-image">

                        <img
                            src="<?php echo e($image); ?>"
                            alt="<?php echo e($dress["dress_name"] ?? "Dress"); ?>"
                            loading="lazy"
                            onerror="this.onerror=null;this.src='images/no-image.jpg';"
                        >

                        <?php if ($isLoggedIn && !$isAdmin): ?>
                            <form
                                action="add-to-wishlist.php"
                                method="POST"
                                class="wishlist-form"
                            >
                                <input
                                    type="hidden"
                                    name="dress_id"
                                    value="<?php echo $dressId; ?>"
                                >

                                <button
                                    type="submit"
                                    class="wishlist-btn <?php echo $isInWishlist ? 'is-added' : ''; ?>"
                                    aria-label="<?php echo $isInWishlist ? 'Already in wishlist' : 'Add to wishlist'; ?>"
                                    title="<?php echo $isInWishlist ? 'Already in wishlist' : 'Add to wishlist'; ?>"
                                    <?php echo $isInWishlist ? 'disabled' : ''; ?>
                                ><?php echo $isInWishlist ? '&#9829;' : '&#9825;'; ?></button>
                            </form>
                        <?php elseif (!$isLoggedIn): ?>
                            <a
                                class="wishlist-form wishlist-btn"
                                href="account.php"
                                aria-label="Log in to use wishlist"
                                title="Log in to use wishlist"
                            >&#9825;</a>
                        <?php endif; ?>

                    </div>

                    <div class="product-info">

                        <span class="product-category">
                            <?php echo e($categoryName !== "" ? $categoryName : "DRESORA COLLECTION"); ?>
                        </span>

                        <h3><?php echo e($dress["dress_name"] ?? ""); ?></h3>

                        <p class="product-price">
                            Rs. <?php echo number_format((float)($dress["rental_price"] ?? 0), 2); ?>
                            <span>/ day</span>
                        </p>

                        <a
                            class="product-btn"
                            href="product-details.php?db_id=<?php echo $dressId; ?>"
                        >
                            VIEW DETAILS &rarr;
                        </a>

                    </div>
                </article>

            <?php endforeach; ?>

            <div class="no-products" id="noProducts">
                <strong>No dresses found</strong>
                Try another search or select a different category.
            </div>

        </div>
    </section>
</main>

<footer class="shop-footer">

    <div class="footer-container">

        <div class="footer-column footer-brand">
            <h2>DRESORA</h2>
            <p class="footer-tagline">DRESS RENTAL</p>

            <p class="footer-description">
                Beautiful styles for the moments that matter.
                Discover your favourite dress for your next special occasion.
            </p>
        </div>

        <div class="footer-column">
            <h3>Explore</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="products.php">Shop Dresses</a></li>
                <li><a href="about.html">About Us</a></li>
                <li><a href="contact.html">Contact</a></li>
              
            </ul>
        </div>

        <div class="footer-column">
            <h3>Your Account</h3>
            <ul>
                <li><a href="account.php">My Account</a></li>
                <li><a href="wishlist.php">My Wishlist</a></li>
                <li><a href="cart.php">My Cart</a></li>
            </ul>
        </div>

        <div class="footer-column">
            <h3>Contact</h3>
            <p>Email: info@dresora.com</p>
            <p>Location: Sri Lanka</p>
            <p>Online Dress Rental</p>
        </div>

    </div>

    <div class="footer-bottom">
        <p>&copy; 2026 DRESORA. All Rights Reserved.</p>
        <p>Find your moment. Wear your style.</p>
    </div>

</footer>

<script>
/* =========================================
   SEARCH, FILTER AND SORT
========================================= */

const searchInput = document.getElementById("searchInput");
const categoryFilter = document.getElementById("categoryFilter");
const sortFilter = document.getElementById("sortFilter");
const productsContainer = document.getElementById("productsContainer");
const noProducts = document.getElementById("noProducts");
const resultsCount = document.getElementById("resultsCount");

function applyFilters() {
    const search = searchInput.value.toLowerCase().trim();
    const category = categoryFilter.value.toLowerCase();
    const sort = sortFilter.value;

    const allProducts = Array.from(
        productsContainer.querySelectorAll(".product-card")
    );

    let visibleProducts = [];

    allProducts.forEach(function(product) {
        const name = (product.dataset.name || "").toLowerCase();
        const productCategory = (product.dataset.category || "").toLowerCase();

        const matchesSearch =
            name.includes(search) ||
            productCategory.includes(search);

        const matchesCategory =
            category === "all" ||
            productCategory === category ||
            productCategory.includes(category);

        if (matchesSearch && matchesCategory) {
            product.style.display = "";
            visibleProducts.push(product);
        } else {
            product.style.display = "none";
        }
    });

    if (sort === "low-high") {
        visibleProducts.sort(function(a, b) {
            return Number(a.dataset.price) - Number(b.dataset.price);
        });
    } else if (sort === "high-low") {
        visibleProducts.sort(function(a, b) {
            return Number(b.dataset.price) - Number(a.dataset.price);
        });
    
    }

    // Reorder only the visible cards. Hidden cards remain available for filtering.
    visibleProducts.forEach(function(product) {
        productsContainer.insertBefore(product, noProducts);
    });

    noProducts.style.display =
        visibleProducts.length === 0 ? "block" : "none";

    resultsCount.textContent =
        visibleProducts.length + 
        (visibleProducts.length === 1 ? " dress" : " dresses");
}

searchInput.addEventListener("input", applyFilters);
categoryFilter.addEventListener("change", applyFilters);
sortFilter.addEventListener("change", applyFilters);

/* =========================================
   HOME PAGE SEARCH / CATEGORY PARAMETERS
========================================= */

const urlParams = new URLSearchParams(window.location.search);
const selectedCategory = urlParams.get("category");
const selectedSearch = urlParams.get("search");

if (selectedCategory) {
    const availableOptions = Array.from(categoryFilter.options);

    const matchingOption = availableOptions.find(function(option) {
        return option.value.toLowerCase() === selectedCategory.toLowerCase();
    });

    if (matchingOption) {
        categoryFilter.value = matchingOption.value;
    }
}

if (selectedSearch) {
    searchInput.value = selectedSearch;
}

applyFilters();
</script>

</body>
</html>
