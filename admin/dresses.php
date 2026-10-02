<?php

session_start();

require_once "../config/database.php";

// Admin only
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../account.php");
    exit;
}

$message = "";
$messageType = "";


/* =========================================================
   DELETE DRESS
========================================================= */

if (isset($_GET["delete"])) {

    $dress_id = intval($_GET["delete"]);

    // Delete size stock first
    $stockStmt = $conn->prepare(
        "DELETE FROM dress_size_stock WHERE dress_id = ?"
    );

    if ($stockStmt) {

        $stockStmt->bind_param(
            "i",
            $dress_id
        );

        $stockStmt->execute();

        $stockStmt->close();
    }


    // Delete dress
    $stmt = $conn->prepare(
        "DELETE FROM dresses WHERE dress_id = ?"
    );

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $dress_id
        );

        if ($stmt->execute()) {

            $message =
                "Dress deleted successfully.";

            $messageType =
                "success";

        } else {

            $message =
                "Unable to delete the dress.";

            $messageType =
                "error";
        }

        $stmt->close();

    } else {

        $message =
            "Database error: " . $conn->error;

        $messageType =
            "error";
    }
}


/* =========================================================
   ADD DRESS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* -----------------------------------------------------
       BASIC DETAILS
    ----------------------------------------------------- */

    $dress_name =
        trim(
            $_POST["dress_name"] ?? ""
        );


    $description =
        trim(
            $_POST["description"] ?? ""
        );


    $category_id =
        intval(
            $_POST["category_id"] ?? 0
        );


    $colour =
        trim(
            $_POST["colour"] ?? ""
        );


    $rental_price =
        floatval(
            $_POST["rental_price"] ?? 0
        );


    /* -----------------------------------------------------
       SIZE QUANTITIES
    ----------------------------------------------------- */

    $sizeQuantities = [

        "S" =>
            max(
                0,
                intval(
                    $_POST["size_S"] ?? 0
                )
            ),

        "M" =>
            max(
                0,
                intval(
                    $_POST["size_M"] ?? 0
                )
            ),

        "L" =>
            max(
                0,
                intval(
                    $_POST["size_L"] ?? 0
                )
            ),

        "XL" =>
            max(
                0,
                intval(
                    $_POST["size_XL"] ?? 0
                )
            )

    ];


    /* -----------------------------------------------------
       TOTAL STOCK
    ----------------------------------------------------- */

    $stock_quantity =
        array_sum(
            $sizeQuantities
        );


    /* -----------------------------------------------------
       IMAGE UPLOAD
    ----------------------------------------------------- */

    $imagePaths = [];


    if (
        isset($_FILES["dress_images"]) &&
        is_array(
            $_FILES["dress_images"]["name"]
        )
    ) {

        $uploadDir =
            "../images/";


        $allowedExtensions = [

            "jpg",
            "jpeg",
            "png",
            "webp"

        ];


        $fileCount =
            count(
                $_FILES["dress_images"]["name"]
            );


        /*
         * Maximum 2 images
         */

        if ($fileCount > 2) {

            $message =
                "Please upload a maximum of 2 images.";

            $messageType =
                "error";

        } else {


            for (
                $i = 0;
                $i < $fileCount;
                $i++
            ) {


                /*
                 * Skip empty file
                 */

                if (
                    $_FILES["dress_images"]["error"][$i]
                    === UPLOAD_ERR_NO_FILE
                ) {

                    continue;
                }


                /*
                 * Check upload error
                 */

                if (
                    $_FILES["dress_images"]["error"][$i]
                    !== UPLOAD_ERR_OK
                ) {

                    $message =
                        "There was an error uploading an image.";

                    $messageType =
                        "error";

                    break;
                }


                $originalFileName =
                    $_FILES["dress_images"]["name"][$i];


                $tmpName =
                    $_FILES["dress_images"]["tmp_name"][$i];


                $extension =
                    strtolower(
                        pathinfo(
                            $originalFileName,
                            PATHINFO_EXTENSION
                        )
                    );


                /*
                 * Check file type
                 */

                if (
                    !in_array(
                        $extension,
                        $allowedExtensions
                    )
                ) {

                    $message =
                        "Only JPG, JPEG, PNG and WEBP images are allowed.";

                    $messageType =
                        "error";

                    break;
                }


                /*
                 * Create unique filename
                 */

                $newFileName =
                    uniqid(
                        "dress_",
                        true
                    )
                    . "_"
                    . ($i + 1)
                    . "."
                    . $extension;


                $destination =
                    $uploadDir .
                    $newFileName;


                /*
                 * Move image to images folder
                 */

                if (
                    move_uploaded_file(
                        $tmpName,
                        $destination
                    )
                ) {

                    $imagePaths[] =
                        "images/" .
                        $newFileName;

                } else {

                    $message =
                        "Failed to save image.";

                    $messageType =
                        "error";

                    break;
                }
            }
        }

    } else {

        $message =
            "Please upload at least one image.";

        $messageType =
            "error";
    }


    /* -----------------------------------------------------
       IMAGE DATABASE VALUE
    ----------------------------------------------------- */

    if (
        count($imagePaths) > 0
    ) {

        /*
         * Example:
         *
         * images/dress_123_1.jpg,
         * images/dress_456_2.jpg
         */

        $image_url =
            implode(
                ",",
                $imagePaths
            );

    } else {

        $image_url = "";
    }


    /* =====================================================
       VALIDATION
    ===================================================== */

    if (
        $dress_name === "" ||
        $category_id <= 0 ||
        $colour === "" ||
        $rental_price <= 0
    ) {

        $message =
            "Please fill in all required fields.";

        $messageType =
            "error";

    }

    elseif (
        count($imagePaths) === 0
    ) {

        $message =
            "Please upload at least one dress image.";

        $messageType =
            "error";

    }

    elseif (
        $stock_quantity <= 0
    ) {

        $message =
            "Please enter quantity for at least one size.";

        $messageType =
            "error";

    }

    else {


        /* =================================================
           INSERT DRESS
        ================================================= */

        /*
         * The old dresses table has a size column.
         *
         * We keep it for compatibility.
         *
         * Actual stock is stored in
         * dress_size_stock.
         */

        $size =
            "S,M,L,XL";


        $sql =
            "INSERT INTO dresses
            (
                category_id,
                dress_name,
                description,
                size,
                colour,
                rental_price,
                stock_quantity,
                image_url
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )";


        $stmt =
            $conn->prepare(
                $sql
            );


        if ($stmt) {


            $stmt->bind_param(
                "issssdis",
                $category_id,
                $dress_name,
                $description,
                $size,
                $colour,
                $rental_price,
                $stock_quantity,
                $image_url
            );


            /* ---------------------------------------------
               EXECUTE DRESS INSERT
            --------------------------------------------- */

            if (
                $stmt->execute()
            ) {


                /*
                 * Get newly created dress ID
                 */

                $dress_id =
                    $conn->insert_id;


                /* =========================================
                   INSERT SIZE STOCK
                ========================================= */

                $stockSql =
                    "INSERT INTO dress_size_stock
                    (
                        dress_id,
                        size,
                        quantity
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?
                    )";


                $stockStmt =
                    $conn->prepare(
                        $stockSql
                    );


                if ($stockStmt) {


                    foreach (
                        $sizeQuantities
                        as $sizeName =>
                        $quantity
                    ) {


                        /*
                         * Save only sizes
                         * with stock > 0
                         */

                        if (
                            $quantity > 0
                        ) {


                            $stockStmt->bind_param(
                                "isi",
                                $dress_id,
                                $sizeName,
                                $quantity
                            );


                            $stockStmt->execute();
                        }
                    }


                    $stockStmt->close();


                    $message =
                        "Dress added successfully!";

                    $messageType =
                        "success";

                } else {

                    $message =
                        "Dress added, but size stock could not be saved.";

                    $messageType =
                        "error";
                }


            } else {

                $message =
                    "Failed to add dress: "
                    . $stmt->error;

                $messageType =
                    "error";
            }


            $stmt->close();


        } else {

            $message =
                "Database error: "
                . $conn->error;

            $messageType =
                "error";
        }
    }
}


/* =========================================================
   GET CATEGORIES
========================================================= */

$categories =
    $conn->query(
        "SELECT
            category_id,
            category_name
         FROM categories
         ORDER BY category_name ASC"
    );


/* =========================================================
   GET DRESSES
========================================================= */

$dresses =
    $conn->query(
        "SELECT
            d.*,
            c.category_name
         FROM dresses d
         LEFT JOIN categories c
            ON d.category_id = c.category_id
         ORDER BY d.dress_id DESC"
    );

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Manage Dresses - DRESORA
    </title>


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


        /* =================================================
           HEADER
        ================================================= */

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

            background: #6f4568;
        }


        /* =================================================
           CONTAINER
        ================================================= */

        .container {

            width: 92%;

            max-width: 1200px;

            margin: 35px auto;
        }


        .page-title {

            color: #5d405c;

            margin-bottom: 25px;
        }


        /* =================================================
           MESSAGE
        ================================================= */

        .message {

            padding: 13px 16px;

            margin-bottom: 20px;

            border-radius: 7px;

            font-weight: bold;
        }


        .success {

            background: #e8f7e8;

            color: #2e7d32;

            border: 1px solid #b7dfb9;
        }


        .error {

            background: #fdeaea;

            color: #c62828;

            border: 1px solid #efb6b6;
        }


        /* =================================================
           FORM
        ================================================= */

        .form-box {

            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px rgba(0,0,0,0.08);

            margin-bottom: 35px;
        }


        .form-box h2 {

            color: #5d405c;

            margin-top: 0;
        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;
        }


        .form-group {

            display: flex;

            flex-direction: column;
        }


        .form-group.full {

            grid-column: 1 / -1;
        }


        label {

            margin-bottom: 7px;

            font-weight: bold;
        }


        input,
        select,
        textarea {

            padding: 11px;

            border: 1px solid #ddd;

            border-radius: 6px;

            font-size: 14px;
        }


        textarea {

            resize: vertical;

            min-height: 90px;
        }


        /* =================================================
           SIZE QUANTITY
        ================================================= */

        .size-quantity-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 15px;

            margin-top: 5px;
        }


        .size-box {

            background: #f8f0f6;

            padding: 15px;

            border-radius: 8px;

            text-align: center;

            border: 1px solid #ead9e6;
        }


        .size-box label {

            display: block;

            color: #5d405c;

            font-weight: bold;

            margin-bottom: 8px;
        }


        .size-box input {

            width: 100%;

            text-align: center;
        }


        /* =================================================
           IMAGE INPUT
        ================================================= */

        .image-help {

            margin-top: 7px;

            color: #777;

            font-size: 13px;
        }


        /* =================================================
           BUTTON
        ================================================= */

        .add-btn {

            margin-top: 20px;

            background: #8b5a83;

            color: white;

            border: none;

            padding: 12px 25px;

            border-radius: 6px;

            cursor: pointer;

            font-size: 15px;

            font-weight: bold;
        }


        .add-btn:hover {

            background: #5d405c;
        }


        /* =================================================
           TABLE
        ================================================= */

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


        /* =================================================
           IMAGE PREVIEW
        ================================================= */

        .dress-images {

            display: flex;

            gap: 6px;

            align-items: center;
        }


        .dress-images img {

            width: 50px;

            height: 60px;

            object-fit: cover;

            border-radius: 5px;

            border: 1px solid #ddd;
        }


        /* =================================================
           DELETE
        ================================================= */

        .delete-btn {

            background: #c62828;

            color: white;

            padding: 7px 12px;

            border-radius: 5px;

            text-decoration: none;

            font-size: 13px;
        }


        .delete-btn:hover {

            background: #a51f1f;
        }


        /* =================================================
           MOBILE
        ================================================= */

        @media (max-width: 700px) {


            .form-grid {

                grid-template-columns: 1fr;
            }


            .form-group.full {

                grid-column: auto;
            }


            .size-quantity-grid {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .header {

                padding: 15px 20px;
            }


            .container {

                width: 95%;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     HEADER
===================================================== -->

<header class="header">

    <h2>
        DRESORA Admin
    </h2>


    <a
        href="dashboard.php"
        class="back-btn"
    >
        ← Dashboard
    </a>

</header>



<!-- =====================================================
     MAIN CONTAINER
===================================================== -->

<div class="container">


    <h1 class="page-title">
        Manage Dresses
    </h1>


    <!-- =================================================
         MESSAGE
    ================================================== -->

    <?php if ($message !== ""): ?>

        <div
            class="message
            <?php echo htmlspecialchars($messageType); ?>"
        >

            <?php
            echo htmlspecialchars(
                $message
            );
            ?>

        </div>

    <?php endif; ?>



    <!-- =================================================
         ADD DRESS
    ================================================== -->

    <div class="form-box">

        <h2>
            Add New Dress
        </h2>


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <div class="form-grid">


                <!-- DRESS NAME -->

                <div class="form-group">

                    <label>
                        Dress Name *
                    </label>

                    <input
                        type="text"
                        name="dress_name"
                        placeholder="Enter dress name"
                        required
                    >

                </div>



                <!-- CATEGORY -->

                <div class="form-group">

                    <label>
                        Category *
                    </label>

                    <select
                        name="category_id"
                        required
                    >

                        <option value="">
                            Select Category
                        </option>


                        <?php
                        if (
                            $categories &&
                            $categories->num_rows > 0
                        ):
                        ?>

                            <?php
                            while (
                                $category =
                                $categories->fetch_assoc()
                            ):
                            ?>

                                <option
                                    value="<?php
                                    echo $category["category_id"];
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $category["category_name"]
                                    );
                                    ?>

                                </option>

                            <?php endwhile; ?>

                        <?php endif; ?>

                    </select>

                </div>



                <!-- DESCRIPTION -->

                <div class="form-group full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        placeholder="Enter dress description"
                    ></textarea>

                </div>



                <!-- COLOUR -->

                <div class="form-group">

                    <label>
                        Colour *
                    </label>

                    <input
                        type="text"
                        name="colour"
                        placeholder="e.g. Pink"
                        required
                    >

                </div>



                <!-- RENTAL PRICE -->

                <div class="form-group">

                    <label>
                        Rental Price (Rs.) *
                    </label>

                    <input
                        type="number"
                        name="rental_price"
                        step="0.01"
                        min="0"
                        placeholder="3500.00"
                        required
                    >

                </div>



                <!-- =================================================
                     SIZE QUANTITY
                ================================================== -->

                <div class="form-group full">

                    <label>
                        Size Quantities *
                    </label>


                    <div class="size-quantity-grid">


                        <!-- S -->

                        <div class="size-box">

                            <label
                                for="size_S"
                            >
                                S
                            </label>

                            <input
                                type="number"
                                id="size_S"
                                name="size_S"
                                min="0"
                                value="0"
                            >

                        </div>



                        <!-- M -->

                        <div class="size-box">

                            <label
                                for="size_M"
                            >
                                M
                            </label>

                            <input
                                type="number"
                                id="size_M"
                                name="size_M"
                                min="0"
                                value="0"
                            >

                        </div>



                        <!-- L -->

                        <div class="size-box">

                            <label
                                for="size_L"
                            >
                                L
                            </label>

                            <input
                                type="number"
                                id="size_L"
                                name="size_L"
                                min="0"
                                value="0"
                            >

                        </div>



                        <!-- XL -->

                        <div class="size-box">

                            <label
                                for="size_XL"
                            >
                                XL
                            </label>

                            <input
                                type="number"
                                id="size_XL"
                                name="size_XL"
                                min="0"
                                value="0"
                            >

                        </div>

                    </div>


                    <small class="image-help">
                        Enter the available quantity for each size.
                        Total stock will be calculated automatically.
                    </small>

                </div>



                <!-- =================================================
                     IMAGES
                ================================================== -->

                <div class="form-group full">

                    <label>
                        Dress Images * (Maximum 2)
                    </label>


                    <input
                        type="file"
                        name="dress_images[]"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        required
                    >


                    <small class="image-help">

                        Select up to 2 images
                        for this dress.

                    </small>

                </div>


            </div>



            <!-- ADD BUTTON -->

            <button
                type="submit"
                class="add-btn"
            >

                + Add Dress

            </button>


        </form>

    </div>



    <!-- =====================================================
         EXISTING DRESSES
    ===================================================== -->

    <div class="table-box">

        <h2>
            Existing Dresses
        </h2>


        <table>


            <thead>

                <tr>

                    <th>
                        ID
                    </th>

                    <th>
                        Images
                    </th>

                    <th>
                        Name
                    </th>

                    <th>
                        Category
                    </th>

                    <th>
                        Colour
                    </th>

                    <th>
                        Price
                    </th>

                    <th>
                        Stock
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>



            <tbody>


                <?php
                if (
                    $dresses &&
                    $dresses->num_rows > 0
                ):
                ?>


                    <?php
                    while (
                        $dress =
                        $dresses->fetch_assoc()
                    ):
                    ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                <?php
                                echo $dress["dress_id"];
                                ?>

                            </td>



                            <!-- IMAGES -->

                            <td>

                                <div class="dress-images">

                                    <?php

                                    $dressImages = [];

                                    if (
                                        !empty(
                                            $dress["image_url"]
                                        )
                                    ) {

                                        $dressImages =
                                            preg_split(
                                                "/[,|]/",
                                                $dress["image_url"]
                                            );
                                    }

                                    ?>


                                    <?php
                                    if (
                                        count(
                                            $dressImages
                                        ) > 0
                                    ):
                                    ?>


                                        <?php
                                        foreach (
                                            $dressImages
                                            as $dressImage
                                        ):
                                        ?>


                                            <?php
                                            $dressImage =
                                                trim(
                                                    $dressImage
                                                );
                                            ?>


                                            <?php
                                            if (
                                                $dressImage !== ""
                                            ):
                                            ?>

                                                <img
                                                    src="../<?php
                                                    echo htmlspecialchars(
                                                        $dressImage
                                                    );
                                                    ?>"
                                                    alt="Dress"
                                                >

                                            <?php endif; ?>


                                        <?php endforeach; ?>


                                    <?php else: ?>

                                        <span>
                                            No image
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>



                            <!-- NAME -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $dress["dress_name"]
                                );
                                ?>

                            </td>



                            <!-- CATEGORY -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $dress["category_name"]
                                );
                                ?>

                            </td>



                            <!-- COLOUR -->

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $dress["colour"]
                                );
                                ?>

                            </td>



                            <!-- PRICE -->

                            <td>

                                Rs.

                                <?php
                                echo number_format(
                                    (float)
                                    $dress["rental_price"],
                                    2
                                );
                                ?>

                            </td>



                            <!-- TOTAL STOCK -->

                            <td>

                                <?php
                                echo $dress["stock_quantity"];
                                ?>

                            </td>



                            <!-- DELETE -->

                            <td>

                                <a
                                    href="dresses.php?delete=<?php
                                    echo $dress["dress_id"];
                                    ?>"
                                    class="delete-btn"

                                    onclick="
                                        return confirm(
                                            'Are you sure you want to delete this dress?'
                                        );
                                    "
                                >

                                    Delete

                                </a>

                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="8"
                            style="text-align:center;"
                        >

                            No dresses found.

                        </td>

                    </tr>


                <?php endif; ?>


            </tbody>

        </table>

    </div>

</div>


</body>

</html>