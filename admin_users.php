<?php
require_once "config.php";

// Guard: only logged-in admins can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

// Handle enable/disable toggle
if (isset($_GET["toggle_id"])) {
    $id = (int) $_GET["toggle_id"];
    $stmt = $conn->prepare(
        "UPDATE users SET status = IF(status='active','disabled','active') WHERE user_id = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_users.php");
    exit;
}

// Handle role change
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["role_id"])) {
    $id   = (int) $_POST["role_id"];
    $role = $_POST["new_role"];
    if (in_array($role, ["student", "staff", "admin"])) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
        $stmt->bind_param("si", $role, $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin_users.php");
    exit;
}

$users = $conn->query("SELECT user_id, full_name, student_id, parent_mobile, role, status, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Management - Queue System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="card wide-card">
    <div class="topbar">
        <div>
            <h2>User Management</h2>
            <p class="subtitle">Logged in as <?= htmlspecialchars($_SESSION["full_name"]) ?> (Admin)</p>
        </div>
        <a href="logout.php" class="btn" style="width:auto; text-decoration:none; padding:10px 20px;">Log Out</a>
    </div>

    <table>
        <tr>
            <th>Name</th>
            <th>Student ID</th>
            <th>Parent Mobile</th>
            <th>Role</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($u["full_name"]) ?></td>
            <td><?= htmlspecialchars($u["student_id"]) ?></td>
            <td><?= htmlspecialchars($u["parent_mobile"]) ?></td>
            <td><span class="badge badge-<?= $u["role"] ?>"><?= ucfirst($u["role"]) ?></span></td>
            <td><span class="badge badge-<?= $u["status"] ?>"><?= ucfirst($u["status"]) ?></span></td>
            <td>
                <form method="POST" style="display:inline-flex; gap:6px; align-items:center;">
                    <input type="hidden" name="role_id" value="<?= $u["user_id"] ?>">
                    <select name="new_role" onchange="this.form.submit()" style="font-size:12px; padding:4px;">
                        <option value="student" <?= $u["role"]=="student"?"selected":"" ?>>Student</option>
                        <option value="staff" <?= $u["role"]=="staff"?"selected":"" ?>>Staff</option>
                        <option value="admin" <?= $u["role"]=="admin"?"selected":"" ?>>Admin</option>
                    </select>
                </form>
                <a class="action-link <?= $u["status"]=="active"?"disable":"enable" ?>"
                   href="admin_users.php?toggle_id=<?= $u["user_id"] ?>">
                   <?= $u["status"]=="active" ? "Disable" : "Enable" ?>
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
