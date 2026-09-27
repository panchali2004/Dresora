
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


/* =========================
   DELETE DRESS
========================= */

if (isset($_GET["delete"])) {

    $dress_id = intval($_GET["delete"]);

    $stmt = $conn->prepare(
        "DELETE FROM dresses WHERE dress_id = ?"
    );

    $stmt->bind_param("i", $dress_id);

    if ($stmt->execute()) {
        $message = "Dress deleted successfully.";
        $messageType = "success";
    } else {
        $message = "Unable to delete the dress.";
        $messageType = "error";
    }

    $stmt->close();
}


/* =========================
   ADD DRESS
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $dress_name = trim($_POST["dress_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category_id = intval($_POST["category_id"] ?? 0);
    $size = trim($_POST["size"] ?? "");
    $colour = trim($_POST["colour"] ?? "");
    $rental_price = floatval($_POST["rental_price"] ?? 0);
    $stock_quantity = intval($_POST["stock_quantity"] ?? 0);
   $image_url = "";

if (isset($_FILES["dress_image"]) && $_FILES["dress_image"]["error"] === UPLOAD_ERR_OK) {

    $uploadDir = "../images/";

    $fileName = $_FILES["dress_image"]["name"];
    $tmpName = $_FILES["dress_image"]["tmp_name"];

    $extension = strtolower(
        pathinfo($fileName, PATHINFO_EXTENSION)
    );

    $allowedExtensions = ["jpg", "jpeg", "png", "webp"];

    if (in_array($extension, $allowedExtensions)) {

        $newFileName = uniqid("dress_", true) . "." . $extension;

        $destination = $uploadDir . $newFileName;

        if (move_uploaded_file($tmpName, $destination)) {

            $image_url = "images/" . $newFileName;

        } else {

            $message = "Failed to upload image.";
            $messageType = "error";
        }

    } else {

        $message = "Only JPG, JPEG, PNG and WEBP images are allowed.";
        $messageType = "error";
    }
}


    // Validation
    if (
        $dress_name === "" ||
        $category_id <= 0 ||
        $size === "" ||
        $colour === "" ||
        $rental_price <= 0 ||
        $stock_quantity < 0
    ) {

        $message = "Please fill in all required fields.";
        $messageType = "error";

    } else {

        $sql = "INSERT INTO dresses
                (category_id, dress_name, description, size, colour,
                 rental_price, stock_quantity, image_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

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

            // IMPORTANT: Execute INSERT here
            if ($stmt->execute()) {

                $message = "Dress added successfully!";
                $messageType = "success";

            } else {

                $message = "Failed to add dress: " . $stmt->error;
                $messageType = "error";
            }

            $stmt->close();

        } else {

            $message = "Database error: " . $conn->error;
            $messageType = "error";
        }
    }
}


/* =========================
   GET CATEGORIES
========================= */

$categories = $conn->query(
    "SELECT category_id, category_name
     FROM categories
     ORDER BY category_name ASC"
);


/* =========================
   GET DRESSES
========================= */

$dresses = $conn->query(
    "SELECT d.*, c.category_name
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

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Manage Dresses - DRESORA</title>

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

        .container {
            width: 92%;
            max-width: 1200px;
            margin: 35px auto;
        }

        .page-title {
            color: #5d405c;
            margin-bottom: 25px;
        }

        /* SUCCESS / ERROR MESSAGE */

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

        /* FORM */

        .form-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            margin-bottom: 35px;
        }

        .form-box h2 {
            color: #5d405c;
            margin-top: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
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

        .add-btn {
            margin-top: 20px;
            background: #8b5a83;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }

        .add-btn:hover {
            background: #5d405c;
        }

        /* TABLE */

        .table-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
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

        @media (max-width: 700px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

        }

    </style>

</head>


<body>


<header class="header">

    <h2>DRESORA Admin</h2>

    <a href="dashboard.php" class="back-btn">
        ← Dashboard
    </a>

</header>


<div class="container">

    <h1 class="page-title">
        Manage Dresses
    </h1>


    <!-- SUCCESS / ERROR MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="message <?php echo $messageType; ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>


    <!-- =========================
         ADD DRESS
    ========================== -->

    <div class="form-box">

        <h2>Add New Dress</h2>
<form method="POST" enctype="multipart/form-data">

            <div class="form-grid">


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


                <div class="form-group">

                    <label>
                        Category *
                    </label>

                    <select name="category_id" required>

                        <option value="">
                            Select Category
                        </option>

                        <?php while ($category = $categories->fetch_assoc()): ?>

                            <option value="<?php echo $category["category_id"]; ?>">

                                <?php
                                echo htmlspecialchars(
                                    $category["category_name"]
                                );
                                ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <div class="form-group full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        placeholder="Enter dress description"
                    ></textarea>

                </div>


                <div class="form-group">

                    <label>
                        Size *
                    </label>

                    <input
                        type="text"
                        name="size"
                        placeholder="S, M, L, XL"
                        required
                    >

                </div>


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


                <div class="form-group">

                    <label>
                        Stock Quantity *
                    </label>

                    <input
                        type="number"
                        name="stock_quantity"
                        min="0"
                        placeholder="5"
                        required
                    >

                </div>


                <div class="form-group full">

                    <label>
                       Dress Image
                    </label>

                    
                     <input
    type="file"
    name="dress_image"
    accept="image/*"
>
                </div>

            </div>


            <button
                type="submit"
                class="add-btn">

                + Add Dress

            </button>

        </form>

    </div>


    <!-- =========================
         DRESS LIST
    ========================== -->

    <div class="table-box">

        <h2>Existing Dresses</h2>

        <table>

            <thead>

                <tr>

                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Size</th>
                    <th>Colour</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

                <?php if ($dresses && $dresses->num_rows > 0): ?>

                    <?php while ($dress = $dresses->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php echo $dress["dress_id"]; ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $dress["dress_name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $dress["category_name"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $dress["size"]
                                );
                                ?>
                            </td>

                            <td>
                                <?php
                                echo htmlspecialchars(
                                    $dress["colour"]
                                );
                                ?>
                            </td>

                            <td>
                                Rs.
                                <?php
                                echo number_format(
                                    (float)$dress["rental_price"],
                                    2
                                );
                                ?>
                            </td>

                            <td>
                                <?php echo $dress["stock_quantity"]; ?>
                            </td>

                            <td>

                                <a
                                    href="dresses.php?delete=<?php echo $dress["dress_id"]; ?>"
                                    class="delete-btn"
                                    onclick="return confirm('Are you sure you want to delete this dress?');"
                                >
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="8">
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
