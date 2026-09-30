<?php
require_once "config.php";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id    = trim($_POST["student_id"]);
    $parent_mobile = trim($_POST["parent_mobile"]);

    // Both values must match the same row - this is what makes it a valid "login"
    $stmt = $conn->prepare(
        "SELECT user_id, full_name, role, status FROM users
         WHERE student_id = ? AND parent_mobile = ?"
    );
    $stmt->bind_param("ss", $student_id, $parent_mobile);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($user["status"] === "disabled") {
            $error = "This account has been disabled. Contact an administrator.";
        } else {
            // Match found - start session
            $_SESSION["user_id"]   = $user["user_id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["role"]      = $user["role"];

            if ($user["role"] === "admin") {
                header("Location: admin_users.php");
            } elseif ($user["role"] === "staff") {
                header("Location: staff_queue.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        }
    } else {
        $error = "Student ID and Parent Mobile Number do not match our records.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login - Queue System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="card">
    <h2>Radiant English Academy</h2>
    <p class="subtitle">Log in with ID and Parent's Mobile Number</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label>ID</label>
            <input type="text" name="student_id" required>
        </div>
        <div class="form-group">
            <label>Parent's Mobile Number</label>
            <input type="text" name="parent_mobile" maxlength="10" placeholder="10-digit number" required>
        </div>
        <button type="submit" class="btn">Log In</button>
    </form>

    <div class="link-row">
        Don't have an account? <a href="register.php">Register</a>
    </div>
</div>
</body>
</html>
