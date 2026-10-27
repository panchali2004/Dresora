<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../account.php');
    exit;
}

if (empty($_SESSION['category_csrf'])) {
    $_SESSION['category_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['category_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $message = 'Invalid request. Please refresh the page.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
        $categoryName = trim((string)($_POST['category_name'] ?? ''));

        try {
            if ($action === 'add' || $action === 'edit') {
                if ($categoryName === '' || mb_strlen($categoryName) > 100) {
                    throw new Exception('Enter a category name (maximum 100 characters).');
                }
                if ($action === 'edit' && (!$categoryId || $categoryId < 1)) {
                    throw new Exception('Invalid category ID.');
                }

                // Prevent duplicate category names (case-insensitive).
                if ($action === 'add') {
                    $check = $conn->prepare('SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(?) LIMIT 1');
                    $check->bind_param('s', $categoryName);
                } else {
                    $check = $conn->prepare('SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(?) AND category_id <> ? LIMIT 1');
                    $check->bind_param('si', $categoryName, $categoryId);
                }
                $check->execute();
                $duplicate = $check->get_result()->num_rows > 0;
                $check->close();
                if ($duplicate) {
                    throw new Exception('This category name already exists.');
                }

                if ($action === 'add') {
                    $stmt = $conn->prepare('INSERT INTO categories (category_name) VALUES (?)');
                    $stmt->bind_param('s', $categoryName);
                } else {
                    $stmt = $conn->prepare('UPDATE categories SET category_name = ? WHERE category_id = ?');
                    $stmt->bind_param('si', $categoryName, $categoryId);
                }
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                if ($action === 'edit' && $affected === 0) {
                    // Same name is also a valid save; verify the category exists.
                    $verify = $conn->prepare('SELECT category_id FROM categories WHERE category_id = ?');
                    $verify->bind_param('i', $categoryId);
                    $verify->execute();
                    $exists = $verify->get_result()->num_rows > 0;
                    $verify->close();
                    if (!$exists) throw new Exception('Category not found.');
                }
                $message = $action === 'add' ? 'Category added successfully!' : 'Category updated successfully!';
                $messageType = 'success';
            } elseif ($action === 'delete') {
                if (!$categoryId || $categoryId < 1) throw new Exception('Invalid category ID.');
                $check = $conn->prepare('SELECT COUNT(*) AS total FROM dresses WHERE category_id = ?');
                $check->bind_param('i', $categoryId);
                $check->execute();
                $count = (int)$check->get_result()->fetch_assoc()['total'];
                $check->close();
                if ($count > 0) {
                    throw new Exception('Cannot delete this category because it contains ' . $count . ' dress(es). Move or remove those dresses first.');
                }
                $stmt = $conn->prepare('DELETE FROM categories WHERE category_id = ?');
                $stmt->bind_param('i', $categoryId);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                if (!$affected) throw new Exception('Category not found.');
                $message = 'Category deleted successfully!';
                $messageType = 'success';
            } else {
                throw new Exception('Unknown action.');
            }
        } catch (Throwable $e) {
            $message = $e instanceof mysqli_sql_exception ? 'Database error. Please check the category data and table structure.' : $e->getMessage();
            $messageType = 'error';
        }
    }
}

$categories = [];
try {
    $result = $conn->query('SELECT c.category_id, c.category_name, COUNT(d.dress_id) AS dress_count FROM categories c LEFT JOIN dresses d ON d.category_id = c.category_id GROUP BY c.category_id, c.category_name ORDER BY c.category_name ASC');
    while ($row = $result->fetch_assoc()) $categories[] = $row;
} catch (Throwable $e) {
    $message = 'Could not load categories. Check your database tables.';
    $messageType = 'error';
}
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Categories - DRESORA</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#fffafc;color:#333}.header{background:#5d405c;color:white;padding:18px 35px;display:flex;justify-content:space-between;align-items:center;gap:15px}.header h2{margin:0}.back{color:white;background:#8b5a83;padding:10px 16px;border-radius:6px;text-decoration:none}.container{max-width:1050px;width:92%;margin:35px auto}h1,h2{color:#5d405c}.box{background:white;padding:25px;border-radius:12px;box-shadow:0 3px 12px #00000012;margin-bottom:25px}.form-row{display:flex;gap:12px;flex-wrap:wrap;align-items:end}.form-field{flex:1;min-width:220px}label{display:block;font-weight:bold;margin-bottom:8px}input[type=text]{padding:11px;border:1px solid #ddd;border-radius:6px;width:100%;font-size:14px}button{border:0;border-radius:6px;color:white;padding:11px 17px;cursor:pointer;font-size:14px}.add{background:#8b5a83}.edit{background:#8b5a83}.delete{background:#c62828}.actions{display:flex;gap:9px;flex-wrap:wrap}.actions form{margin:0}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:13px;border-bottom:1px solid #eee}th{background:#f5edf4;color:#5d405c}.table-wrap{overflow-x:auto}.message{padding:13px 16px;border-radius:7px;margin-bottom:20px;font-weight:bold}.success{background:#e8f7e8;color:#2e7d32}.error{background:#fdeaea;color:#c62828}.hint{font-size:13px;color:#777}.edit-form{display:none;margin-top:12px;padding:14px;background:#f8f0f6;border-radius:8px}.edit-form.open{display:block}@media(max-width:650px){.header{padding:15px}.container{width:95%}th,td{padding:9px}}
</style>
</head>
<body>
<header class="header"><h2>DRESORA Admin</h2><a class="back" href="dashboard.php">← Dashboard</a></header>
<main class="container">
<h1>Manage Categories</h1>
<?php if ($message !== ''): ?><div class="message <?= e($messageType) ?>"><?= e($message) ?></div><?php endif; ?>
<section class="box"><h2>Add New Category</h2>
<form method="post" class="form-row">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['category_csrf']) ?>">
<input type="hidden" name="action" value="add">
<div class="form-field"><label for="new_category">Category Name *</label><input type="text" id="new_category" name="category_name" maxlength="100" placeholder="e.g. Traditional" required></div>
<button class="add" type="submit">+ Add Category</button>
</form></section>
<section class="box"><h2>Existing Categories</h2><p class="hint">A category with dresses cannot be deleted. Edit the name to rename it.</p>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Category Name</th><th>Dresses</th><th>Actions</th></tr></thead><tbody>
<?php if (!$categories): ?><tr><td colspan="4">No categories found.</td></tr><?php endif; ?>
<?php foreach ($categories as $category): ?>
<tr><td><?= (int)$category['category_id'] ?></td><td><?= e($category['category_name']) ?></td><td><?= (int)$category['dress_count'] ?></td><td>
<div class="actions">
<button type="button" class="edit" onclick="toggleEdit(<?= (int)$category['category_id'] ?>)">Edit</button>
<form method="post" onsubmit="return confirm('Delete this category?');">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['category_csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?= (int)$category['category_id'] ?>">
<button type="submit" class="delete" <?= (int)$category['dress_count'] > 0 ? 'disabled title="Category contains dresses" style="opacity:.45;cursor:not-allowed"' : '' ?>>Delete</button>
</form></div>
<form method="post" id="edit-<?= (int)$category['category_id'] ?>" class="edit-form">
<input type="hidden" name="csrf_token" value="<?= e($_SESSION['category_csrf']) ?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="category_id" value="<?= (int)$category['category_id'] ?>">
<div class="form-row"><div class="form-field"><label>Category Name</label><input type="text" name="category_name" maxlength="100" value="<?= e($category['category_name']) ?>" required></div><button class="edit" type="submit">Save Changes</button></div>
</form>
</td></tr>
<?php endforeach; ?>
</tbody></table></div></section>
</main>
<script>function toggleEdit(id){const form=document.getElementById('edit-'+id);form.classList.toggle('open');if(form.classList.contains('open'))form.querySelector('input[name=category_name]').focus();}</script>
</body></html>
