
<?php
session_start();

require_once "config/database.php";

/* =========================
   CUSTOMER LOGIN CHECK
========================= */
if (!isset($_SESSION["user_id"])) {
    header("Location: account.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

/* =========================
   GET OR CREATE WISHLIST
========================= */
$stmt = $conn->prepare("
    SELECT wishlist_id
    FROM wishlist
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$wishlist = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$wishlist) {
    $stmt = $conn->prepare("
        INSERT INTO wishlist (user_id, created_at)
        VALUES (?, NOW())
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $wishlist_id = (int) $conn->insert_id;
    $stmt->close();
} else {
    $wishlist_id = (int) $wishlist["wishlist_id"];
}

/* =========================
   REMOVE FROM WISHLIST
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["remove_item"])) {

    $item_id = filter_input(
        INPUT_POST,
        "remove_item",
        FILTER_VALIDATE_INT
    );

    if ($item_id) {
        $stmt = $conn->prepare("
            DELETE FROM wishlist_items
            WHERE wishlist_item_id = ?
              AND wishlist_id = ?
        ");

        $stmt->bind_param("ii", $item_id, $wishlist_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: wishlist.php?removed=1");
    exit;
}

/* =========================
   FETCH WISHLIST DRESSES
========================= */
$stmt = $conn->prepare("
    SELECT
        wi.wishlist_item_id,
        d.dress_id,
        d.dress_name,
        d.description,
        d.rental_price,
        d.image_url
    FROM wishlist_items wi
    INNER JOIN dresses d
        ON wi.dress_id = d.dress_id
    WHERE wi.wishlist_id = ?
    ORDER BY wi.wishlist_item_id DESC
");

$stmt->bind_param("i", $wishlist_id);
$stmt->execute();
$result = $stmt->get_result();

$dresses = [];

while ($row = $result->fetch_assoc()) {
    $dresses[] = $row;
}

$stmt->close();

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Wishlist | DRESORA</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #fffafc;
    color: #5d405c;
    font-family: Arial, sans-serif;
}

.container {
    width: 92%;
    max-width: 1200px;
    margin: 45px auto;
}

.header {
    text-align: center;
    margin-bottom: 35px;
}

.header h1 {
    margin-bottom: 10px;
    font-size: 32px;
}

.header p {
    color: #887487;
}

.notice {
    padding: 13px;
    background: #e5f6e9;
    color: #26733b;
    border-radius: 8px;
    margin-bottom: 22px;
}

.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 25px;
}

.dress-card {
    background: white;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid #f0e4ee;
    box-shadow: 0 5px 18px rgba(93, 64, 92, 0.08);
    display: flex;
    flex-direction: column;
}

.dress-image {
    width: 100%;
    height: 320px;
    object-fit: cover;
    background: #f8eef7;
}

.dress-info {
    padding: 18px;
    display: flex;
    flex-direction: column;
    flex: 1;
}

.dress-info h3 {
    margin: 0 0 10px;
    font-size: 19px;
}

.description {
    color: #817381;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 12px;
}

.price {
    color: #8b5a83;
    font-size: 20px;
    font-weight: bold;
    margin: 8px 0 18px;
}

.price small {
    font-size: 12px;
    font-weight: normal;
}

.card-actions {
    display: flex;
    gap: 10px;
    margin-top: auto;
}

.btn {
    display: inline-block;
    padding: 11px 12px;
    border-radius: 7px;
    text-align: center;
    text-decoration: none;
    font-size: 13px;
    cursor: pointer;
    border: none;
    font-weight: bold;
}

.view-btn {
    flex: 1;
    color: white;
    background: #8b5a83;
}

.remove-btn {
    color: #a33f55;
    background: #fff0f3;
}

.view-btn:hover {
    background: #5d405c;
}

.remove-btn:hover {
    background: #ffe0e7;
}

.empty {
    text-align: center;
    padding: 65px 20px;
    background: white;
    border: 1px solid #f0e4ee;
    border-radius: 14px;
}

.heart {
    font-size: 55px;
    margin-bottom: 12px;
}

.empty h2 {
    margin-bottom: 10px;
}

.empty p {
    color: #887487;
    margin-bottom: 25px;
}

.shop-btn {
    display: inline-block;
    padding: 13px 24px;
    border-radius: 8px;
    background: #8b5a83;
    color: white;
    text-decoration: none;
    font-weight: bold;
}

.back-link {
    display: inline-block;
    margin-top: 30px;
    color: #8b5a83;
    text-decoration: none;
}

@media (max-width: 850px) {
    .wishlist-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .dress-image {
        height: 280px;
    }
}

@media (max-width: 550px) {
    .wishlist-grid {
        grid-template-columns: 1fr;
    }

    .dress-image {
        height: 350px;
    }

    .header h1 {
        font-size: 27px;
    }
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>♡ My Wishlist</h1>
        <p>Your favourite styles, all in one place.</p>
    </div>

    <?php if (isset($_GET["removed"])): ?>
        <div class="notice">
            Dress removed from your wishlist successfully.
        </div>
    <?php endif; ?>

    <?php if (count($dresses) > 0): ?>

        <div class="wishlist-grid">

            <?php foreach ($dresses as $dress): ?>

                <div class="dress-card">

                    <?php
                    $image = trim((string)($dress["image_url"] ?? ""));
                    $imageSrc = "";

                    if ($image !== "") {
                        $imageSrc = preg_match(
                            '~^(https?://|/)~i',
                            $image
                        )
                            ? $image
                            : $image;
                    }
                    ?>

                    <?php if ($imageSrc !== ""): ?>
                        <img
                            class="dress-image"
                            src="<?= e($imageSrc) ?>"
                            alt="<?= e($dress["dress_name"]) ?>"
                            onerror="this.style.display='none';"
                        >
                    <?php else: ?>
                        <div
                            class="dress-image"
                            style="display:flex;align-items:center;justify-content:center;"
                        >
                            Image unavailable
                        </div>
                    <?php endif; ?>

                    <div class="dress-info">

                        <h3><?= e($dress["dress_name"]) ?></h3>

                        <div class="description">
                            <?= e($dress["description"]) ?>
                        </div>

                        <div class="price">
                            Rs. <?= number_format(
                                (float)$dress["rental_price"],
                                2
                            ) ?>
                            <small>/ 5 days</small>
                        </div>

                        <div class="card-actions">

                            <a
                                class="btn view-btn"
                                href="product-details.php?db_id=<?= (int)$dress["dress_id"] ?>"
                            >
                                View Details
                            </a>

                            <form
                                method="POST"
                                onsubmit="return confirm('Remove this dress from your wishlist?');"
                            >
                                <button
                                    class="btn remove-btn"
                                    type="submit"
                                    name="remove_item"
                                    value="<?= (int)$dress["wishlist_item_id"] ?>"
                                >
                                    Remove
                                </button>
                            </form>

                        </div>

                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty">
            <div class="heart">♡</div>

            <h2>Your Wishlist is Empty</h2>

            <p>
                You haven't saved any dresses yet.
                Explore our collection and add your favourites.
            </p>

            <a href="products.php" class="shop-btn">
                Explore Dresses
            </a>
        </div>

    <?php endif; ?>

    <a href="products.php" class="back-link">
        ← Continue Shopping
    </a>

</div>

</body>
</html>