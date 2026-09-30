<?php
require_once "config.php";

// Guard: only logged-in users can see this page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$error   = "";
$token_issued = null;

// ============================================================
// Handle "Generate Token" POST
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["service_id"])) {
    $service_id = (int) $_POST["service_id"];

    // 1. Verify the service exists and is active
    $svc_stmt = $conn->prepare(
        "SELECT service_id, name, prefix FROM services WHERE service_id = ? AND is_active = 1"
    );
    $svc_stmt->bind_param("i", $service_id);
    $svc_stmt->execute();
    $svc_result = $svc_stmt->get_result();

    if ($svc_result->num_rows === 1) {
        $svc = $svc_result->fetch_assoc();
        $svc_stmt->close();

        // 2. Check if this user already has an active (waiting/called) token
        //    for ANY service today — one token per student per day.
        $active_stmt = $conn->prepare(
            "SELECT token_id FROM tokens
             WHERE user_id = ?
               AND status IN ('waiting','called')
               AND DATE(created_at) = CURDATE()
             LIMIT 1"
        );
        $active_stmt->bind_param("i", $user_id);
        $active_stmt->execute();
        $active_stmt->store_result();

        if ($active_stmt->num_rows > 0) {
            $error = "You already have an active token for today. Please wait for your turn or cancel it first.";
            $active_stmt->close();
        } else {
            $active_stmt->close();

            // 3. Calculate the next sequential number for this service today.
            //    COUNT existing tokens for this service today → next = count + 1.
            $count_stmt = $conn->prepare(
                "SELECT COUNT(*) AS cnt FROM tokens
                 WHERE service_id = ? AND DATE(created_at) = CURDATE()"
            );
            $count_stmt->bind_param("i", $service_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $count_row    = $count_result->fetch_assoc();
            $count_stmt->close();

            $next_num     = (int) $count_row["cnt"] + 1;
            $token_number = $svc["prefix"] . "-" . str_pad($next_num, 3, "0", STR_PAD_LEFT);

            // 4. INSERT the token row
            $ins_stmt = $conn->prepare(
                "INSERT INTO tokens (user_id, service_id, token_number, status)
                 VALUES (?, ?, ?, 'waiting')"
            );
            $ins_stmt->bind_param("iis", $user_id, $service_id, $token_number);

            if ($ins_stmt->execute()) {
                $token_issued = [
                    "token_id" => $ins_stmt->insert_id,
                    "number"   => $token_number,
                    "service"  => $svc["name"],
                ];
            } else {
                $error = "Could not generate token. Please try again.";
            }
            $ins_stmt->close();
        }
    } else {
        $svc_stmt->close();
        $error = "Invalid or inactive service selected.";
    }
}

// ============================================================
// Load active services from DB for the selection form
// ============================================================
$services_result = $conn->query(
    "SELECT service_id, name, description, icon FROM services WHERE is_active = 1 ORDER BY service_id"
);

// ============================================================
// Check if the logged-in user already has an active token today
// (so we can show it instead of the selection form)
// ============================================================
$existing_token = null;
if (!$token_issued) {
    $ex_stmt = $conn->prepare(
        "SELECT t.token_id, t.token_number, t.status, s.name AS service_name
         FROM tokens t
         JOIN services s ON t.service_id = s.service_id
         WHERE t.user_id = ?
           AND t.status IN ('waiting','called')
           AND DATE(t.created_at) = CURDATE()
         LIMIT 1"
    );
    $ex_stmt->bind_param("i", $user_id);
    $ex_stmt->execute();
    $ex_result = $ex_stmt->get_result();
    if ($ex_result->num_rows === 1) {
        $existing_token = $ex_result->fetch_assoc();
    }
    $ex_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Get Your Token - Queue System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="kiosk-wrap">

    <div class="kiosk-header">
        <div class="avatar"><?= strtoupper(substr($_SESSION["full_name"], 0, 2)) ?></div>
        <h1>Smart Queue</h1>
        <p>Student Portal</p>
    </div>

    <div class="kiosk-body">

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($token_issued || $existing_token):
            $display = $token_issued ?? $existing_token;
            $is_new  = ($token_issued !== null);
        ?>

            <!-- ===== Token issued / active token screen ===== -->
            <p style="font-size:12px; letter-spacing:1px; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">
                <?= $is_new ? "Token Issued" : "Your Active Token" ?>
            </p>
            <h2 style="color:#1e2f56; margin-bottom:20px;">
                <?= htmlspecialchars($display["service"] ?? $display["service_name"]) ?>
            </h2>

            <div class="token-box">
                <div class="label">Your Token Number</div>
                <div class="number"><?= htmlspecialchars($display["number"] ?? $display["token_number"]) ?></div>
                <span class="status-pill">● <?= ucfirst(htmlspecialchars($display["status"] ?? "waiting")) ?></span>
            </div>

            <div class="info-banner">
                Please stay nearby. Check your queue position anytime on the
                <a href="queue_status.php" style="color:#1e2f56; font-weight:600;">Queue Status</a> page.
            </div>

            <a href="queue_status.php" class="btn" style="text-decoration:none; display:block; text-align:center; margin-bottom:10px;">
                View My Queue Position
            </a>

            <form method="POST">
                <button type="submit" class="btn" style="background:#fff; color:#1e2f56; border:1px solid #d1d5db;">
                    Get a New Token
                </button>
            </form>

        <?php else: ?>

            <!-- ===== Service selection screen ===== -->
            <p style="font-size:12px; letter-spacing:1px; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">Step 1 of 1</p>
            <h2 style="color:#1e2f56; margin-bottom:20px;">Choose a Service</h2>

            <form method="POST">
                <?php while ($s = $services_result->fetch_assoc()): ?>
                    <label class="service-option">
                        <input type="radio" name="service_id" value="<?= (int) $s["service_id"] ?>" required>
                        <div style="display:flex; align-items:center;">
                            <div class="icon"><?= htmlspecialchars($s["icon"]) ?></div>
                            <div class="info">
                                <h4><?= htmlspecialchars($s["name"]) ?></h4>
                                <p><?= htmlspecialchars($s["description"]) ?></p>
                            </div>
                        </div>
                    </label>
                <?php endwhile; ?>

                <button type="submit" class="btn">Generate Token</button>
            </form>

        <?php endif; ?>

        <div class="link-row" style="margin-top:24px;">
            <a href="logout.php">Log Out</a>
        </div>
    </div>
</div>
</body>
</html>
