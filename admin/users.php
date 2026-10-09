
<?php
session_start();
require_once "../config/database.php";

/* =========================
   ADMIN ACCESS
========================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    strtolower($_SESSION["role"]) !== "admin"
) {
    header("Location: ../account.php");
    exit;
}

$admin_id = (int)$_SESSION["user_id"];

/* =========================
   CSRF TOKEN
========================= */

if (empty($_SESSION["users_csrf"])) {
    $_SESSION["users_csrf"] = bin2hex(random_bytes(32));
}

function h($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =========================
   FLASH MESSAGE
========================= */

if (!isset($_SESSION["users_flash"])) {
    $_SESSION["users_flash"] = null;
}

/* =========================
   REMOVE CUSTOMER
   Customers with orders cannot
   be deleted, preserving history.
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $target_id = filter_input(
        INPUT_POST,
        "user_id",
        FILTER_VALIDATE_INT
    );

    $csrf = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION["users_csrf"], $csrf)
    ) {
        $_SESSION["users_flash"] = [
            "type" => "error",
            "text" => "Security check failed. Please try again."
        ];

    } elseif (!$target_id || $target_id <= 0) {

        $_SESSION["users_flash"] = [
            "type" => "error",
            "text" => "Invalid user selected."
        ];

    } elseif ($target_id === $admin_id) {

        $_SESSION["users_flash"] = [
            "type" => "error",
            "text" => "You cannot remove your own admin account."
        ];

    } else {

        try {

            $conn->begin_transaction();

            $check = $conn->prepare("
                SELECT role
                FROM users
                WHERE user_id = ?
                FOR UPDATE
            ");

            $check->bind_param("i", $target_id);
            $check->execute();

            $target = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$target) {
                throw new Exception("User not found.");
            }

            if (strtolower($target["role"]) !== "customer") {
                throw new Exception(
                    "Only customer accounts can be removed."
                );
            }

            // Keep order history intact.
            $order_check = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM orders
                WHERE user_id = ?
            ");

            $order_check->bind_param("i", $target_id);
            $order_check->execute();

            $order_data = $order_check
                ->get_result()
                ->fetch_assoc();

            $order_check->close();

            if ((int)$order_data["total"] > 0) {
                throw new Exception(
                    "This customer has order history. " .
                    "The account cannot be deleted."
                );
            }

            // Remove notifications belonging to this customer.
            $notification_delete = $conn->prepare("
                DELETE FROM notifications
                WHERE user_id = ?
            ");

            $notification_delete->bind_param("i", $target_id);
            $notification_delete->execute();
            $notification_delete->close();

            // Delete only the selected customer.
            $delete = $conn->prepare("
                DELETE FROM users
                WHERE user_id = ?
                  AND LOWER(role) = 'customer'
            ");

            $delete->bind_param("i", $target_id);
            $delete->execute();

            if ($delete->affected_rows !== 1) {
                $delete->close();
                throw new Exception(
                    "The customer account could not be removed."
                );
            }

            $delete->close();
            $conn->commit();

            $_SESSION["users_csrf"] = bin2hex(random_bytes(32));

            $_SESSION["users_flash"] = [
                "type" => "success",
                "text" => "Customer account removed successfully."
            ];

        } catch (Throwable $e) {

            $conn->rollback();

            $_SESSION["users_flash"] = [
                "type" => "error",
                "text" => $e instanceof mysqli_sql_exception
                    ? "Unable to remove this account. Check related records."
                    : $e->getMessage()
            ];
        }
    }

    header("Location: users.php");
    exit;
}

/* =========================
   FLASH MESSAGE
========================= */

$flash = $_SESSION["users_flash"];
$_SESSION["users_flash"] = null;

/* =========================
   SUMMARY COUNTS
========================= */

$total_users = 0;
$total_customers = 0;
$total_admins = 0;

$count_result = $conn->query("
    SELECT
        COUNT(*) AS total_users,
        COALESCE(SUM(LOWER(role) = 'customer'), 0)
            AS total_customers,
        COALESCE(SUM(LOWER(role) = 'admin'), 0)
            AS total_admins
    FROM users
");

if ($count_result) {
    $counts = $count_result->fetch_assoc();

    $total_users = (int)$counts["total_users"];
    $total_customers = (int)$counts["total_customers"];
    $total_admins = (int)$counts["total_admins"];
}

/* =========================
   SEARCH USERS
========================= */

$search = trim($_GET["search"] ?? "");

$sql = "
    SELECT user_id, name, email, phone, role, created_at
    FROM users
    WHERE name LIKE ? OR email LIKE ?
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);

$search_term = "%" . $search . "%";
$stmt->bind_param("ss", $search_term, $search_term);
$stmt->execute();

$users = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>DRESORA | Users Management</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #fffafc;
    color: #453447;
    font-family: Arial, sans-serif;
}

.topbar {
    background: #5d405c;
    color: white;
    padding: 20px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    box-shadow: 0 4px 16px #5d405c20;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: #8b5a83;
    font-size: 23px;
}

.brand h2 {
    margin: 0 0 5px;
    font-size: 20px;
}

.brand small {
    letter-spacing: 1.5px;
    opacity: .75;
    font-size: 10px;
}

.back-link {
    color: white;
    text-decoration: none;
    background: #8b5a83;
    padding: 11px 16px;
    border-radius: 8px;
    font-size: 13px;
}

.back-link:hover {
    background: #a66b9b;
}

.container {
    width: 92%;
    max-width: 1250px;
    margin: 35px auto 60px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.page-heading h1 {
    margin: 0 0 8px;
    color: #5d405c;
    font-size: 28px;
}

.page-heading p {
    margin: 0;
    color: #887b89;
    font-size: 14px;
    line-height: 1.6;
}

.summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}

.summary-card {
    background: white;
    border: 1px solid #f0e5ef;
    border-radius: 15px;
    padding: 22px;
    box-shadow: 0 5px 20px #5d405c09;
}

.summary-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 18px;
}

.summary-label {
    color: #817282;
    font-size: 13px;
}

.summary-icon {
    width: 43px;
    height: 43px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: #f5eaf4;
    font-size: 21px;
}

.summary-number {
    color: #5d405c;
    font-size: 30px;
    font-weight: bold;
}

.summary-note {
    margin-top: 7px;
    font-size: 12px;
    color: #998d9a;
}

.panel {
    background: white;
    border: 1px solid #f0e5ef;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px #5d405c09;
}

.panel-heading {
    padding: 22px 24px;
    border-bottom: 1px solid #f1eaf1;
}

.panel-heading h2 {
    margin: 0 0 7px;
    font-size: 18px;
    color: #5d405c;
}

.panel-heading p {
    margin: 0;
    color: #938493;
    font-size: 12px;
}

.toolbar {
    padding: 20px 24px;
    display: flex;
    gap: 12px;
}

.search-input {
    flex: 1;
    min-width: 0;
    border: 1px solid #e5d7e4;
    border-radius: 9px;
    padding: 12px 14px;
    font-size: 13px;
    outline: none;
}

.search-input:focus {
    border-color: #a66b9b;
    box-shadow: 0 0 0 3px #a66b9b18;
}

.btn {
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    padding: 11px 15px;
    font-size: 12px;
    font-weight: bold;
}

.btn-search {
    background: #8b5a83;
    color: white;
}

.btn-search:hover {
    background: #5d405c;
}

.btn-reset {
    color: #6b5368;
    background: #f3edf3;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 920px;
}

thead {
    background: #faf5fa;
}

th {
    text-align: left;
    padding: 15px 18px;
    color: #79667a;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .5px;
}

td {
    padding: 17px 18px;
    border-top: 1px solid #f3edf3;
    font-size: 12px;
    color: #665b68;
}

tbody tr:hover {
    background: #fffbfe;
}

.user-name {
    font-weight: bold;
    color: #5d405c;
    margin-bottom: 4px;
}

.user-id {
    font-size: 11px;
    color: #9a8c9b;
}

.role {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: bold;
    text-transform: capitalize;
}

.role-customer {
    background: #e9f7ef;
    color: #26764b;
}

.role-admin {
    background: #f1e6f5;
    color: #764687;
}

.remove-btn {
    background: #fff0f1;
    color: #bb3e53;
    border: 1px solid #f4d6dc;
    padding: 8px 11px;
    border-radius: 7px;
    font-size: 11px;
    font-weight: bold;
    cursor: pointer;
}

.remove-btn:hover {
    background: #ffe0e5;
}

.protected {
    color: #9b8d9d;
    font-size: 11px;
}

.empty {
    text-align: center;
    padding: 45px 20px;
    color: #958596;
}

.empty-icon {
    font-size: 32px;
    margin-bottom: 12px;
}

.alert {
    padding: 14px 17px;
    border-radius: 9px;
    margin-bottom: 22px;
    font-size: 13px;
    line-height: 1.5;
}

.alert-success {
    background: #eaf8ef;
    color: #256c44;
    border: 1px solid #ccebd7;
}

.alert-error {
    background: #fff0f1;
    color: #a33347;
    border: 1px solid #f2d3d9;
}

.result-count {
    padding: 16px 24px;
    border-top: 1px solid #f1eaf1;
    color: #938493;
    font-size: 12px;
}

@media (max-width: 760px) {
    .summary {
        grid-template-columns: 1fr;
    }

    .container {
        width: 92%;
        margin-top: 25px;
    }

    .page-heading h1 {
        font-size: 24px;
    }

    .toolbar {
        padding: 16px;
        flex-wrap: wrap;
    }

    .search-input {
        flex-basis: 100%;
    }

    .panel-heading {
        padding: 18px;
    }
}
</style>
</head>

<body>

<header class="topbar">
    <div class="brand">
        <div class="brand-icon">👗</div>
        <div>
            <h2>DRESORA Admin</h2>
            <small>USER MANAGEMENT</small>
        </div>
    </div>

    <a href="dashboard.php" class="back-link">
        ← Back to Dashboard
    </a>
</header>

<main class="container">

    <div class="page-heading">
        <div>
            <h1>Users Management</h1>
            <p>View and manage registered DRESORA accounts.</p>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert <?= $flash["type"] === "success"
            ? "alert-success" : "alert-error" ?>">
            <?= h($flash["text"]) ?>
        </div>
    <?php endif; ?>

    <section class="summary">

        <div class="summary-card">
            <div class="summary-top">
                <span class="summary-label">Total Users</span>
                <div class="summary-icon">👥</div>
            </div>
            <div class="summary-number">
                <?= $total_users ?>
            </div>
            <div class="summary-note">All registered accounts</div>
        </div>

        <div class="summary-card">
            <div class="summary-top">
                <span class="summary-label">Customers</span>
                <div class="summary-icon">🛍️</div>
            </div>
            <div class="summary-number">
                <?= $total_customers ?>
            </div>
            <div class="summary-note">Registered customer accounts</div>
        </div>

        <div class="summary-card">
            <div class="summary-top">
                <span class="summary-label">Admins</span>
                <div class="summary-icon">🛡️</div>
            </div>
            <div class="summary-number">
                <?= $total_admins ?>
            </div>
            <div class="summary-note">Administrator accounts</div>
        </div>

    </section>

    <section class="panel">

        <div class="panel-heading">
            <h2>Registered Accounts</h2>
            <p>Search users and review their account information.</p>
        </div>

        <form method="GET" class="toolbar">
            <input
                type="search"
                class="search-input"
                name="search"
                placeholder="Search by name or email..."
                value="<?= h($search) ?>"
            >

            <button class="btn btn-search" type="submit">
                🔍 Search
            </button>

            <?php if ($search !== ""): ?>
                <a class="btn btn-reset" href="users.php">
                    Reset
                </a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Registered</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($users->num_rows > 0): ?>

                    <?php while ($user = $users->fetch_assoc()): ?>
                        <?php
                            $role = strtolower($user["role"] ?? "");
                            $is_customer = $role === "customer";
                            $is_self = (int)$user["user_id"] === $admin_id;
                        ?>

                        <tr>
                            <td>
                                <div class="user-name">
                                    <?= h($user["name"]) ?>
                                </div>
                                <div class="user-id">
                                    ID #<?= (int)$user["user_id"] ?>
                                </div>
                            </td>

                            <td><?= h($user["email"]) ?></td>

                            <td>
                                <?= h(
                                    $user["phone"] ?: "Not provided"
                                ) ?>
                            </td>

                            <td>
                                <span class="role <?= $role === "admin"
                                    ? "role-admin" : "role-customer" ?>">
                                    <?= h($role) ?>
                                </span>
                            </td>

                            <td>
                                <?= $user["created_at"]
                                    ? h(date(
                                        "d M Y",
                                        strtotime($user["created_at"])
                                    ))
                                    : "—" ?>
                            </td>

                            <td>
                                <?php if ($is_customer && !$is_self): ?>

                                    <form
                                        method="POST"
                                        action="users.php"
                                        onsubmit="return confirm(
                                            'Remove this customer account? ' +
                                            'This action cannot be undone.'
                                        );"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= h($_SESSION["users_csrf"]) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int)$user["user_id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="remove-btn"
                                        >
                                            🗑 Remove
                                        </button>
                                    </form>

                                <?php else: ?>

                                    <span class="protected">
                                        Protected
                                    </span>

                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty">
                            <div class="empty-icon">🔎</div>
                            <strong>No users found</strong>
                            <p>Try another name or email address.</p>
                        </td>
                    </tr>

                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="result-count">
            Showing <?= $users->num_rows ?> matching account(s)
        </div>

    </section>

</main>

<?php
$stmt->close();
$conn->close();
?>

</body>
</html>