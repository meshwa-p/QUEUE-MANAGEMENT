<?php
require_once "config.php";
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name     = trim($_POST["full_name"]);
    $student_id    = trim($_POST["student_id"]);
    $parent_mobile = trim($_POST["parent_mobile"]);

    if ($full_name === "" || $student_id === "" || $parent_mobile === "") {
        $error = "All fields are required.";
    } elseif (!preg_match('/^[0-9]{10}$/', $parent_mobile)) {
        $error = "Parent mobile number must be exactly 10 digits.";
    } else {
        // Check for duplicate student ID
        $check = $conn->prepare("SELECT user_id FROM users WHERE student_id = ?");
        $check->bind_param("s", $student_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "This Student ID is already registered.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO users (full_name, student_id, parent_mobile, role, status)
                 VALUES (?, ?, ?, 'student', 'active')"
            );
            $stmt->bind_param("sss", $full_name, $student_id, $parent_mobile);

            if ($stmt->execute()) {
                $success = "Account created successfully. You can now log in.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register - Queue System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="card">
    <h2>Create Account</h2>
    <p class="subtitle">Register using your Student ID</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" required>
        </div>
        <div class="form-group">
            <label>Student ID</label>
            <input type="text" name="student_id" required>
        </div>
        <div class="form-group">
            <label>Parent's Mobile Number</label>
            <input type="text" name="parent_mobile" maxlength="10" placeholder="10-digit number" required>
        </div>
        <button type="submit" class="btn">Register</button>
    </form>

    <div class="link-row">
        Already have an account? <a href="login.php">Log in</a>
    </div>
</div>
</body>
</html>
