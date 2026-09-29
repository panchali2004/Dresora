<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dresora - Product Details</title>

    <link rel="stylesheet" href="style.css">

    <style>

        .details-page {
            max-width: 1100px;
            margin: auto;
            padding: 80px 30px;
        }

        .details-container {
            display: flex;
            gap: 50px;
            align-items: center;
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(120, 80, 110, 0.10);
        }

        .details-gallery {
            width: 50%;
        }

        .details-image {
            width: 100%;
            height: 550px;
            overflow: hidden;
            border-radius: 15px;
            background: #f3e8f1;
        }

        .details-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .thumbnail-container {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }

        .thumbnail {
            width: 78px;
            height: 90px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: 0.3s;
            background: #f3e8f1;
        }

        .thumbnail:hover {
            transform: translateY(-2px);
        }

        .thumbnail.active {
            border-color: #8b5a83;
        }

        .details-info {
            width: 50%;
        }

        .details-category {
            color: #a66b9b;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .details-info h1 {
            color: #5d405c;
            font-size: 38px;
            margin: 12px 0 15px;
        }

        .details-price {
            color: #8b5a83;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .details-price small {
            font-size: 14px;
            color: #777078;
            font-weight: normal;
        }

        .details-description {
            color: #777078;
            line-height: 1.8;
            margin-bottom: 25px;
        }

        .detail-item {
            margin-bottom: 15px;
            color: #665966;
        }

        .detail-item strong {
            color: #5d405c;
        }

        /* SIZE */

        .size-title {
            display: block;
            margin-bottom: 10px;
            color: #5d405c;
            font-weight: bold;
        }

        .size-options {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .size-options input {
            display: none;
        }

        .size-options label {
            width: 45px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;

            border: 1px solid #dcc6d8;
            border-radius: 8px;

            color: #5d405c;
            cursor: pointer;

            transition: 0.3s;
        }

        .size-options label:hover {
            border-color: #8b5a83;
        }

        .size-options input:checked + label {
            background: #8b5a83;
            color: white;
            border-color: #8b5a83;
        }

        /* RENTAL DATE */

        .rental-dates {
            margin-bottom: 18px;
        }

        .rental-dates label {
            display: block;
            margin-bottom: 8px;
            color: #5d405c;
            font-weight: bold;
        }

        .rental-dates input {
            width: 220px;
            padding: 11px 15px;

            border: 1px solid #dcc6d8;
            border-radius: 8px;

            color: #5d405c;
            background: white;

            outline: none;
        }

        .rental-dates input:focus {
            border-color: #8b5a83;
        }

        /* EXPECTED RETURN DATE */

        .expected-return {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f0f6;
            border-radius: 10px;
        }

        .expected-return strong {
            color: #5d405c;
        }

        .expected-return span {
            color: #8b5a83;
            font-weight: bold;
        }

        /* RENTAL POLICY */

        .rental-policy {
            margin-bottom: 20px;
            padding: 15px;
            background: #fff8fc;
            border-left: 4px solid #8b5a83;
            border-radius: 8px;
        }

        .rental-policy strong {
            color: #5d405c;
        }

        .rental-policy p {
            margin: 6px 0;
            color: #777078;
            font-size: 14px;
            line-height: 1.6;
        }

        /* TOTAL */

        .total-price {
            margin-top: 15px;
            padding: 15px;

            background: #f8f0f6;
            border-radius: 10px;

            color: #5d405c;
            font-weight: bold;
        }

        .total-price span {
            color: #8b5a83;
        }

        /* ADD TO CART */

        .cart-btn {
            display: inline-block;

            padding: 13px 30px;

            background: #8b5a83;
            color: white;

            border: none;
            border-radius: 25px;

            cursor: pointer;

            margin-top: 15px;

            font-size: 15px;
            font-weight: bold;

            transition: 0.3s;
        }

        .cart-btn:hover {
            background: #6f4568;
            transform: translateY(-2px);
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 25px;
            color: #8b5a83;
            font-weight: bold;
        }

        @media (max-width: 768px) {

            .details-container {
                flex-direction: column;
            }

            .details-gallery,
            .details-info {
                width: 100%;
            }

            .details-image {
                height: 450px;
            }

            .details-info h1 {
                font-size: 30px;
            }

            .rental-dates input {
                width: 100%;
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
            <small>DRESS RENTAL</small>

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
                    <span class="cart-count">0</span>
                </a>
            </li>

        </ul>

    </nav>

</header>


<!-- =========================
     MAIN
========================= -->

<main>

<section class="details-page">


    <a href="products.php"
       class="back-btn">

        ← Back to Shop

    </a>


    <div class="details-container">


        <!-- =========================
             IMAGE
        ========================= -->

        <div class="details-gallery">

            <div class="details-image">
                <img id="productImage"
                     src=""
                     alt="Dress">
            </div>

            <div id="thumbnailContainer" class="thumbnail-container"></div>

        </div>


        <!-- =========================
             DETAILS
        ========================= -->

        <div class="details-info">


            <span id="productCategory"
                  class="details-category">
            </span>


            <h1 id="productName">
            </h1>


            <p class="details-price">

                Rs. <span id="productPrice"></span>

                <small>
                    / 5 days
                </small>

            </p>


            <p id="productDescription"
               class="details-description">
            </p>


            <!-- SIZE -->

            <div class="detail-item">

                <span class="size-title">
                    Select Size
                </span>


                <div class="size-options">

                    <input type="radio"
                           id="sizeS"
                           name="size"
                           value="S">

                    <label for="sizeS">
                        S
                    </label>


                    <input type="radio"
                           id="sizeM"
                           name="size"
                           value="M">

                    <label for="sizeM">
                        M
                    </label>


                    <input type="radio"
                           id="sizeL"
                           name="size"
                           value="L">

                    <label for="sizeL">
                        L
                    </label>


                    <input type="radio"
                           id="sizeXL"
                           name="size"
                           value="XL">

                    <label for="sizeXL">
                        XL
                    </label>

                </div>

            </div>


            <!-- RENTAL START DATE -->

            <div class="rental-dates">

                <label for="startDate">
                    Rental Start Date
                </label>

                <input
                    type="date"
                    id="startDate"
                    onchange="calculateExpectedReturnDate()"
                >

            </div>


            <!-- EXPECTED RETURN DATE -->

            <div class="expected-return">

                <strong>
                    Expected Return Date:
                </strong>

                <span id="expectedReturnDate">
                    Select start date
                </span>

            </div>


            <!-- RENTAL POLICY -->

            <div class="rental-policy">

                <strong>
                    Rental Policy
                </strong>

                <p>
                    • The listed rental price includes the first 5 days.
                </p>

                <p>
                    • After 5 days, a late fee of Rs. 500 will be charged for each extra day.
                </p>

                <p>
                    • Late fees are calculated based on the actual return date.
                </p>

            </div>


            <!-- TOTAL -->

            <div class="total-price">

                Total Rental Price:

                Rs.
                <span id="totalPrice">
                    0
                </span>

            </div>


            <!-- ADD TO CART -->

            <button
                class="cart-btn"
                onclick="addToCart()">

                🛒 Add to Cart

            </button>


            <!-- AVAILABILITY -->

            <div class="detail-item">

                <strong>
                    Availability:
                </strong>

                Available

            </div>


        </div>

    </div>

</section>

</main>


<!-- =========================
     FOOTER
========================= -->

<footer>

    <div class="footer-bottom">

        <p style="text-align:center;">
            © 2026 Dresora. All Rights Reserved.
        </p>

    </div>

</footer>


<script>


/* =========================
   PRODUCTS
========================= */

const products = {

    1: {
        name: "Classic Party Dress",
        category: "Party Dress",
        price: 3000,
        image: "images/party1.jpg",
        images: ["images/party1.jpg", "images/party1-2.jpg"] ,
        description: "A beautiful and elegant party dress suitable for special occasions and celebrations."
    },

    2: {
        name: "Sparkle Party Dress",
        category: "Party Dress",
        price: 3200,
        image: "images/party2.jpg",
        images: ["images/party2.jpg", "images/party2-2.jpg"] ,
        description: "A stylish sparkle dress designed for parties and special events."
    },

    3: {
        name: "Rose Party Dress",
        category: "Party Dress",
        price: 2800,
        image: "images/party3.jpg",
        images: ["images/party3.jpg", "images/party3-2.jpg"] ,
        description: "A charming rose-inspired party dress perfect for celebrations."
    },

    4: {
        name: "Glamour Party Dress",
        category: "Party Dress",
        price: 3500,
        image: "images/party4.jpg",
        images: ["images/party4.jpg", "images/party4-2.jpg"] ,
        description: "A glamorous dress designed for parties and memorable occasions."
    },

    5: {
        name: "Velvet Party Dress",
        category: "Party Dress",
        price: 3300,
        image: "images/party5.jpg",
        images: ["images/party5.jpg", "images/party5-2.jpg"] ,
        description: "A sophisticated velvet dress with an elegant party style."
    },


    6: {
        name: "Luxury Wedding Dress",
        category: "Wedding Dress",
        price: 5000,
        image: "images/wedding1.jpg",
        images: ["images/wedding1.jpg", "images/wedding1-2.jpg"] ,
        description: "A beautiful wedding dress designed for an elegant bridal appearance."
    },

    7: {
        name: "Princess Wedding Dress",
        category: "Wedding Dress",
        price: 5500,
        image: "images/wedding2.jpg",
        images: ["images/wedding2.jpg", "images/wedding2-2.jpg"] ,
        description: "A princess-style wedding dress for a graceful and elegant look."
    },

    8: {
        name: "Elegant Bridal Dress",
        category: "Wedding Dress",
        price: 4800,
        image: "images/wedding3.jpg",
        images: ["images/wedding3.jpg", "images/wedding3-2.jpg"] ,
        description: "An elegant bridal dress suitable for special wedding celebrations."
    },

    9: {
        name: "Pearl Wedding Dress",
        category: "Wedding Dress",
        price: 5200,
        image: "images/wedding4.jpg",
        images: ["images/wedding4.jpg", "images/wedding4-2.jpg"] ,
        description: "A beautiful pearl-inspired wedding dress with a sophisticated design."
    },

    10: {
        name: "Classic Bridal Gown",
        category: "Wedding Dress",
        price: 4900,
        image: "images/wedding5.jpg",
        images: ["images/wedding5.jpg", "images/wedding5-2.jpg"] ,
        description: "A classic bridal gown designed for a timeless wedding look."
    },


    11: {
        name: "Elegant Evening Dress",
        category: "Evening Dress",
        price: 3500,
        image: "images/evening1.jpg",
        images: ["images/evening1.jpg", "images/evening1-2.jpg"] ,
        description: "An elegant evening dress suitable for dinners and special events."
    },

    12: {
        name: "Satin Evening Dress",
        category: "Evening Dress",
        price: 3700,
        image: "images/evening2.jpg",
        images: ["images/evening2.jpg", "images/evening2-2.jpg"] ,
        description: "A stylish satin dress perfect for evening occasions."
    },

    13: {
        name: "Black Evening Dress",
        category: "Evening Dress",
        price: 3400,
        image: "images/evening3.jpg",
        images: ["images/evening3.jpg", "images/evening3-2.jpg"] ,
        description: "A classic black evening dress with a sophisticated appearance."
    },

    14: {
        name: "Long Evening Gown",
        category: "Evening Dress",
        price: 4200,
        image: "images/evening4.jpg",
        images: ["images/evening4.jpg", "images/evening4-2.jpg"] ,
        description: "A graceful long gown designed for elegant evening occasions."
    },

    15: {
        name: "Shimmer Evening Dress",
        category: "Evening Dress",
        price: 3900,
        image: "images/evening5.jpg",
        images: ["images/evening5.jpg", "images/evening5-2.jpg"] ,
        description: "A beautiful shimmer dress that adds an elegant touch to special events."
    },


    16: {
        name: "Floral Summer Dress",
        category: "Casual Dress",
        price: 2500,
        image: "images/casual1.jpg",
        images: ["images/casual1.jpg", "images/casual1-2.jpg"] ,
        description: "A fresh floral dress suitable for casual daytime occasions."
    },

    17: {
        name: "Simple Cotton Dress",
        category: "Casual Dress",
        price: 2200,
        image: "images/casual2.jpg",
        images: ["images/casual2.jpg", "images/casual2-2.jpg"] ,
        description: "A comfortable cotton dress for a simple and relaxed look."
    },

    18: {
        name: "Casual Midi Dress",
        category: "Casual Dress",
        price: 2400,
        image: "images/casual3.jpg",
        images: ["images/casual3.jpg", "images/casual3-2.jpg"] ,
        description: "A stylish midi dress suitable for casual occasions."
    },

    19: {
        name: "Summer Breeze Dress",
        category: "Casual Dress",
        price: 2300,
        image: "images/casual4.jpg",
        images: ["images/casual4.jpg", "images/casual4-2.jpg"] ,
        description: "A light and comfortable dress perfect for summer days."
    },

    20: {
        name: "Everyday Comfort Dress",
        category: "Casual Dress",
        price: 2100,
        image: "images/casual5.jpg",
        images: ["images/casual5.jpg", "images/casual5-2.jpg"] ,
        description: "A comfortable casual dress designed for everyday occasions."
    },


    21: {
        name: "Modern Cocktail Dress",
        category: "Cocktail Dress",
        price: 3200,
        image: "images/cocktail1.jpg",
        images: ["images/cocktail1.jpg", "images/cocktail1-2.jpg"] ,
        description: "A modern cocktail dress suitable for stylish celebrations."
    },

    22: {
        name: "Satin Cocktail Dress",
        category: "Cocktail Dress",
        price: 3400,
        image: "images/cocktail2.jpg",
        images: ["images/cocktail2.jpg", "images/cocktail2-2.jpg"] ,
        description: "A sophisticated satin cocktail dress for special occasions."
    },

    23: {
        name: "Chic Cocktail Dress",
        category: "Cocktail Dress",
        price: 3100,
        image: "images/cocktail3.jpg",
        images: ["images/cocktail3.jpg", "images/cocktail3-2.jpg"] ,
        description: "A chic dress designed for elegant cocktail events."
    },

    24: {
        name: "Classic Cocktail Dress",
        category: "Cocktail Dress",
        price: 3000,
        image: "images/cocktail4.jpg",
        images: ["images/cocktail4.jpg", "images/cocktail4-2.jpg"] ,
        description: "A classic cocktail dress with a timeless style."
    },

    25: {
        name: "Short Party Cocktail Dress",
        category: "Cocktail Dress",
        price: 2900,
        image: "images/cocktail5.jpg",
        images: ["images/cocktail5.jpg", "images/cocktail5-2.jpg"] ,
        description: "A stylish short cocktail dress suitable for parties and events."
    },


    26: {
        name: "Elegant Formal Dress",
        category: "Formal Dress",
        price: 3600,
        image: "images/formal1.jpg",
        images: ["images/formal1.jpg", "images/formal1-2.jpg"] ,
        description: "An elegant formal dress suitable for professional and formal events."
    },

    27: {
        name: "Classic Formal Gown",
        category: "Formal Dress",
        price: 4000,
        image: "images/formal2.jpg",
        images: ["images/formal2.jpg", "images/formal2-2.jpg"] ,
        description: "A classic formal gown designed for sophisticated occasions."
    },

    28: {
        name: "Office Formal Dress",
        category: "Formal Dress",
        price: 2800,
        image: "images/formal3.jpg",
        images: ["images/formal3.jpg", "images/formal3-2.jpg"] ,
        description: "A professional formal dress suitable for office and business occasions."
    },

    29: {
        name: "Long Formal Dress",
        category: "Formal Dress",
        price: 3800,
        image: "images/formal4.jpg",
        images: ["images/formal4.jpg", "images/formal4-2.jpg"] ,
        description: "A graceful long formal dress for important occasions."
    },

    30: {
        name: "Premium Formal Dress",
        category: "Formal Dress",
        price: 4300,
        image: "images/formal5.jpg",
        images: ["images/formal5.jpg", "images/formal5-2.jpg"] ,
        description: "A premium formal dress designed for an elegant and professional appearance."
    }

};


/* =========================
   GET PRODUCT ID
========================= */

<?php
require_once "config/database.php";

$dbProduct = null;

if (isset($_GET["db_id"])) {

    $db_id = intval($_GET["db_id"]);

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
            WHERE d.dress_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $db_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $dbProduct = $result->fetch_assoc();
    }

    $stmt->close();
}
?>
const urlParams =
    new URLSearchParams(
        window.location.search
    );

const productId =
    urlParams.get("id");

const dbProduct =
    <?php echo json_encode($dbProduct); ?>;

let product = null;

if (dbProduct) {

    product = {
        id: dbProduct.dress_id,
        name: dbProduct.dress_name,
        category: dbProduct.category_name || "Dress",
        price: Number(dbProduct.rental_price),
        image: dbProduct.image_url || "images/no-image.jpg",
        images: [
            dbProduct.image_url || "images/no-image.jpg"
        ],
        description: dbProduct.description || "",
        size: dbProduct.size || ""
    };

} else if (productId) {

    product = products[productId];

}


/* =========================
   DISPLAY PRODUCT
========================= */

if (product) {

    document.getElementById(
        "productName"
    ).textContent =
        product.name;


    document.getElementById(
        "productCategory"
    ).textContent =
        product.category;


    document.getElementById(
        "productPrice"
    ).textContent =
        product.price.toLocaleString();


    document.getElementById(
        "productDescription"
    ).textContent =
        product.description;


    const productImage =
        document.getElementById("productImage");

    const thumbnailContainer =
        document.getElementById("thumbnailContainer");

    productImage.src = product.images[0];
    productImage.alt = product.name;

    thumbnailContainer.innerHTML = "";

    product.images.forEach(function(image, index) {

        const thumbnail = document.createElement("img");

        thumbnail.src = image;
        thumbnail.alt = product.name + " view " + (index + 1);
        thumbnail.className = "thumbnail" + (index === 0 ? " active" : "");

        thumbnail.addEventListener("click", function() {
            productImage.src = image;

            document.querySelectorAll(".thumbnail").forEach(function(item) {
                item.classList.remove("active");
            });

            thumbnail.classList.add("active");
        });

        thumbnailContainer.appendChild(thumbnail);
    });


    calculateTotal();

}


/* =========================
   SET MINIMUM DATE
========================= */

const startDateInput =
    document.getElementById("startDate");

const today =
    new Date();

const todayYear =
    today.getFullYear();

const todayMonth =
    String(today.getMonth() + 1)
        .padStart(2, "0");

const todayDay =
    String(today.getDate())
        .padStart(2, "0");

const todayString =
    `${todayYear}-${todayMonth}-${todayDay}`;

startDateInput.min = todayString;


/* =========================
   CALCULATE EXPECTED RETURN
========================= */

function calculateExpectedReturnDate() {

    const startDate =
        document.getElementById("startDate").value;

    const expectedReturn =
        document.getElementById("expectedReturnDate");


    if (!startDate) {

        expectedReturn.textContent =
            "Select start date";

        calculateTotal();

        return;
    }


    const date =
        new Date(startDate + "T00:00:00");


    /*
       Rental period = 5 days
       Expected return = Start Date + 5 days
    */

    date.setDate(
        date.getDate() + 5
    );


    const year =
        date.getFullYear();

    const month =
        String(date.getMonth() + 1)
            .padStart(2, "0");

    const day =
        String(date.getDate())
            .padStart(2, "0");


    const formattedDate =
        `${year}-${month}-${day}`;


    expectedReturn.textContent =
        formattedDate;


    calculateTotal();

}


/* =========================
   CALCULATE TOTAL
========================= */

function calculateTotal() {

    if (!product) {
        return;
    }


    const totalPrice =
        document.getElementById("totalPrice");


    /*
       Product price is the fixed
       rental price for the first 5 days.
    */

    if (!document.getElementById("startDate").value) {

        totalPrice.textContent =
            "0";

        return;
    }


    totalPrice.textContent =
        product.price.toLocaleString();

}


/* =========================
   ADD TO CART
========================= */

function addToCart() {

    if (!product) {

        alert("Product not found.");

        return;
    }


    /* =========================
       SIZE
    ========================= */

    const selectedSize =
        document.querySelector(
            'input[name="size"]:checked'
        );


    if (!selectedSize) {

        alert(
            "Please select a size first."
        );

        return;
    }


    const size =
        selectedSize.value;


    /* =========================
       START DATE
    ========================= */

    const startDate =
        document.getElementById(
            "startDate"
        ).value;


    if (!startDate) {

        alert(
            "Please select a rental start date."
        );

        return;
    }


    /* =========================
       EXPECTED RETURN DATE
    ========================= */

    const date =
        new Date(
            startDate + "T00:00:00"
        );


    date.setDate(
        date.getDate() + 5
    );


    const year =
        date.getFullYear();

    const month =
        String(date.getMonth() + 1)
            .padStart(2, "0");

    const day =
        String(date.getDate())
            .padStart(2, "0");


    const expectedReturnDate =
        `${year}-${month}-${day}`;


    /* =========================
       RENTAL DETAILS
    ========================= */

    const rentalDays = 5;

    const baseRentalPrice =
        product.price;

   
    const quantity = 1;


    /* =========================
       SEND TO DATABASE
    ========================= */

    const formData =
        new FormData();


   formData.append(
    "product_id",
    product.id
);

    formData.append(
        "product_name",
        product.name
    );

    formData.append(
        "category",
        product.category
    );

    formData.append(
        "price",
        product.price
    );

    formData.append(
        "image",
        product.image
    );

    formData.append(
        "size",
        size
    );

    formData.append(
        "start_date",
        startDate
    );

    formData.append(
        "expected_return_date",
        expectedReturnDate
    );

    formData.append(
        "rental_days",
        rentalDays
    );

    formData.append(
        "allowed_rental_days",
        5
    );

    formData.append(
        "base_rental_price",
        baseRentalPrice
    );

    formData.append(
        "late_fee_per_day",
        500
    );

    formData.append(
        "quantity",
        quantity
    );


    /* =========================
       SEND REQUEST
    ========================= */

    fetch("add_to_cart.php", {

        method: "POST",

        body: formData

    })

    .then(function(response) {

        return response.text();

    })

    .then(function(result) {

        if (result.trim() === "success") {

            updateCartCount();

            alert(
                product.name +
                " has been added to your cart."
            );

        }

        else {

            alert(result);

        }

    })

    .catch(function(error) {

        console.error(error);

        alert(
            "Something went wrong while adding the item to the cart."
        );

    });

}


    

    


    



   



 






    


/* =========================
   CART COUNT
========================= */

function updateCartCount() {

    const cart =
        JSON.parse(
            localStorage.getItem("cart")
        ) || [];


    const totalQuantity =
        cart.reduce(function(total, item) {

            return total +
                Number(item.quantity || 1);

        }, 0);


    document.querySelectorAll(
        ".cart-count"
    ).forEach(function(count) {

        count.textContent =
            totalQuantity;

    });

}


/* =========================
   INITIAL CART COUNT
========================= */

updateCartCount();


</script>


</body>

</html>