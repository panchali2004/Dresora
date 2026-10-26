<?php
session_start();
require_once "../config/database.php";
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../account.php'); exit;
}
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { header('Location: dresses.php'); exit; }
$error = '';
$stmt = $conn->prepare('SELECT * FROM dresses WHERE dress_id = ?');
$stmt->bind_param('i', $id); $stmt->execute();
$dress = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$dress) { http_response_code(404); exit('Dress not found.'); }
$categories = $conn->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetch_all(MYSQLI_ASSOC);
$stocks = array_fill_keys(['S','M','L','XL'], 0);
$stmt = $conn->prepare('SELECT size, quantity FROM dress_size_stock WHERE dress_id = ?');
$stmt->bind_param('i', $id); $stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    if (array_key_exists($row['size'], $stocks)) $stocks[$row['size']] = (int)$row['quantity'];
}
$stmt->close();
$oldImages = array_values(array_filter(array_map('trim', preg_split('/[,|]/', (string)$dress['image_url']))));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['dress_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $colour = trim($_POST['colour'] ?? '');
    $category = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['rental_price'] ?? 0);
    $newStocks = [];
    foreach (array_keys($stocks) as $size) {
        $v = $_POST['size_'.$size] ?? '0';
        if (filter_var($v, FILTER_VALIDATE_INT) === false || (int)$v < 0) { $error = 'Stock must be a non-negative whole number.'; break; }
        $newStocks[$size] = (int)$v;
    }
    if (!$error && ($name === '' || $colour === '' || $category < 1 || $price <= 0)) $error = 'Please complete all required fields.';
    if (!$error && array_sum($newStocks) < 1) $error = 'At least one size needs stock.';
    if (!$error) {
        $check = $conn->prepare('SELECT category_id FROM categories WHERE category_id = ?');
        $check->bind_param('i', $category); $check->execute();
        if (!$check->get_result()->fetch_assoc()) $error = 'Invalid category.';
        $check->close();
    }
    $newImages = [];
    if (!$error && isset($_FILES['dress_images'])) {
        $files = $_FILES['dress_images'];
        $selected = [];
        foreach ($files['error'] as $i => $uploadStatus) {
            if ($uploadStatus === UPLOAD_ERR_NO_FILE) continue;
            if ($uploadStatus !== UPLOAD_ERR_OK) { $error = 'Image upload failed.'; break; }
            $selected[] = $i;
        }
        if (!$error && count($selected) > 2) $error = 'Maximum 2 images allowed.';
        if (!$error) {
            foreach ($selected as $i) {
                $tmp = $files['tmp_name'][$i];
                $info = @getimagesize($tmp);
                $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
                if (!$info || !isset($allowed[$info[2]]) || (int)$files['size'][$i] > 5 * 1024 * 1024) {
                    $error = 'Images must be JPG, PNG or WEBP, maximum 5MB each.'; break;
                }
                $filename = 'dress_' . bin2hex(random_bytes(12)) . '.' . $allowed[$info[2]];
                $destination = __DIR__ . '/../images/' . $filename;
                if (!move_uploaded_file($tmp, $destination)) { $error = 'Could not save uploaded image.'; break; }
                $newImages[] = 'images/' . $filename;
            }
        }
    }
    if (!$error) {
        $images = count($newImages) ? $newImages : $oldImages;
        $imageUrl = implode(',', $images);
        try {
            $conn->begin_transaction();
            $update = $conn->prepare('UPDATE dresses SET category_id=?, dress_name=?, description=?, colour=?, rental_price=?, image_url=? WHERE dress_id=?');
            $update->bind_param('isssdsi', $category, $name, $description, $colour, $price, $imageUrl, $id);
            if (!$update->execute()) throw new Exception($update->error);
            $update->close();
            foreach ($newStocks as $size => $qty) {
                $find = $conn->prepare('SELECT quantity FROM dress_size_stock WHERE dress_id=? AND size=?');
                $find->bind_param('is', $id, $size); $find->execute();
                $exists = $find->get_result()->fetch_assoc(); $find->close();
                if ($exists) {
                    $st = $conn->prepare('UPDATE dress_size_stock SET quantity=? WHERE dress_id=? AND size=?');
                    $st->bind_param('iis', $qty, $id, $size);
                } else {
                    $st = $conn->prepare('INSERT INTO dress_size_stock (dress_id,size,quantity) VALUES (?,?,?)');
                    $st->bind_param('isi', $id, $size, $qty);
                }
                if (!$st->execute()) throw new Exception($st->error);
                $st->close();
            }
            $conn->commit();
            header('Location: dresses.php?updated=1'); exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = 'Could not update dress: ' . $e->getMessage();
        }
    }
    // Remove newly uploaded files if validation/database operation fails.
    if ($error) foreach ($newImages as $path) { @unlink(__DIR__ . '/../' . $path); }
    $dress['dress_name'] = $name; $dress['description'] = $description;
    $dress['colour'] = $colour; $dress['category_id'] = $category;
    $dress['rental_price'] = $price;
    if (isset($newStocks) && count($newStocks) === 4) $stocks = $newStocks;
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Dress - DRESORA</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#fffafc;color:#333;font-family:Arial,sans-serif}header{background:#5d405c;color:white;padding:20px 6%;display:flex;justify-content:space-between;align-items:center}header a{color:white;text-decoration:none}main{max-width:850px;margin:35px auto;padding:25px;background:white;border-radius:12px;box-shadow:0 3px 12px #0001}h1{color:#5d405c}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.full{grid-column:1/-1}label{display:block;font-weight:bold;margin-bottom:7px}input,select,textarea{width:100%;padding:11px;border:1px solid #ddd;border-radius:6px}textarea{min-height:110px}.sizes{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.images{display:flex;gap:12px;flex-wrap:wrap}.images img{width:100px;height:125px;object-fit:cover;border-radius:6px}.btn{background:#8b5a83;color:white;border:0;padding:12px 24px;border-radius:6px;cursor:pointer}.error{padding:12px;background:#fdeaea;color:#a00;border-radius:6px;margin-bottom:18px}small{color:#777}@media(max-width:650px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.sizes{grid-template-columns:repeat(2,1fr)}main{margin:15px}}
</style></head><body>
<header><strong>DRESORA Admin</strong><a href="dresses.php">← Manage Dresses</a></header>
<main><h1>Edit Dress #<?= h($id) ?></h1>
<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><div class="grid">
<div><label>Dress Name *</label><input name="dress_name" value="<?= h($dress['dress_name']) ?>" required></div>
<div><label>Category *</label><select name="category_id" required>
<?php foreach ($categories as $cat): ?><option value="<?= h($cat['category_id']) ?>" <?= (int)$cat['category_id']===(int)$dress['category_id']?'selected':'' ?>><?= h($cat['category_name']) ?></option><?php endforeach; ?></select></div>
<div class="full"><label>Description</label><textarea name="description"><?= h($dress['description']) ?></textarea></div>
<div><label>Colour *</label><input name="colour" value="<?= h($dress['colour']) ?>" required></div>
<div><label>Rental Price (Rs.) *</label><input type="number" name="rental_price" step="0.01" min="0.01" value="<?= h($dress['rental_price']) ?>" required></div>
<div class="full"><label>Size Stock *</label><div class="sizes">
<?php foreach ($stocks as $size=>$qty): ?><div><label><?= h($size) ?></label><input type="number" name="size_<?= h($size) ?>" min="0" step="1" value="<?= h($qty) ?>" required></div><?php endforeach; ?>
</div><small>Enter current available stock, not total historical stock.</small></div>
<div class="full"><label>Current Images</label><div class="images">
<?php foreach ($oldImages as $img): ?><img src="../<?= h($img) ?>" alt="Dress image"><?php endforeach; ?>
<?php if (!$oldImages): ?><small>No images</small><?php endif; ?></div></div>
<div class="full"><label>Replace Images (optional, max 2)</label><input type="file" name="dress_images[]" accept="image/jpeg,image/png,image/webp" multiple><small>Leave empty to keep current images. Uploading replaces the image list.</small></div>
</div><p><button type="submit" class="btn">Save Changes</button> <a href="dresses.php">Cancel</a></p></form>
</main></body></html>
