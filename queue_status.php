<?php
require_once "config.php";

// Guard: only logged-in users can see this page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$AVG_SERVICE_MINUTES = 4; // assumed average minutes per token

// ============================================================
// Fetch the student's active token for today (waiting or called)
// ============================================================
$tok_stmt = $conn->prepare(
    "SELECT t.token_id, t.token_number, t.status, t.created_at,
            s.service_id, s.name AS service_name
     FROM tokens t
     JOIN services s ON t.service_id = s.service_id
     WHERE t.user_id = ?
       AND t.status IN ('waiting','called')
       AND DATE(t.created_at) = CURDATE()
     LIMIT 1"
);
$tok_stmt->bind_param("i", $user_id);
$tok_stmt->execute();
$tok_result = $tok_stmt->get_result();
$my_token   = $tok_result->num_rows === 1 ? $tok_result->fetch_assoc() : null;
$tok_stmt->close();

$position     = null;
$wait_minutes = null;

if ($my_token && $my_token["status"] === "waiting") {
    // Position = number of 'waiting' tokens for same service created BEFORE mine
    $pos_stmt = $conn->prepare(
        "SELECT COUNT(*) AS pos FROM tokens
         WHERE service_id = ?
           AND status = 'waiting'
           AND created_at < ?
           AND DATE(created_at) = CURDATE()"
    );
    $pos_stmt->bind_param("is", $my_token["service_id"], $my_token["created_at"]);
    $pos_stmt->execute();
    $pos_result   = $pos_stmt->get_result();
    $pos_row      = $pos_result->fetch_assoc();
    $pos_stmt->close();

    $position     = (int) $pos_row["pos"];          // 0 means "you're next"
    $wait_minutes = $position * $AVG_SERVICE_MINUTES;
}

// ============================================================
// Handle "Cancel Token" button
// ============================================================
$cancel_msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancel_token_id"])) {
    $cancel_id = (int) $_POST["cancel_token_id"];
    $c_stmt = $conn->prepare(
        "UPDATE tokens SET status = 'cancelled'
         WHERE token_id = ? AND user_id = ? AND status = 'waiting'"
    );
    $c_stmt->bind_param("ii", $cancel_id, $user_id);
    $c_stmt->execute();
    $c_stmt->close();
    // Reload page so everything re-fetches cleanly
    header("Location: queue_status.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Queue Status - Queue System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="kiosk-wrap">

    <div class="kiosk-header">
        <div class="avatar"><?= strtoupper(substr($_SESSION["full_name"], 0, 2)) ?></div>
        <h1>Queue Status</h1>
        <p>Live position tracker</p>
    </div>

    <div class="kiosk-body">

        <?php if (!$my_token): ?>
            <!-- No active token today -->
            <div class="info-banner">
                You don't have an active token for today.<br>
                <a href="dashboard.php" style="color:#1e2f56; font-weight:600;">Get a Token →</a>
            </div>

        <?php elseif ($my_token["status"] === "called"): ?>
            <!-- Token has been called — student should go to counter -->
            <p style="font-size:12px; letter-spacing:1px; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">Status Update</p>
            <h2 style="color:#1e2f56; margin-bottom:20px;"><?= htmlspecialchars($my_token["service_name"]) ?></h2>

            <div class="token-box" style="border: 2px solid #1e2f56;">
                <div class="label">Your Token</div>
                <div class="number"><?= htmlspecialchars($my_token["token_number"]) ?></div>
                <span class="status-pill" style="background:#dbeafe; color:#1e40af;">📢 Called — Please proceed to the counter!</span>
            </div>

            <div class="info-banner">
                Your number has been called. Please go to the service counter now.
            </div>

        <?php else: ?>
            <!-- Token is waiting -->
            <p style="font-size:12px; letter-spacing:1px; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">
                <?= htmlspecialchars($my_token["service_name"]) ?>
            </p>
            <h2 style="color:#1e2f56; margin-bottom:20px;">Your Queue Position</h2>

            <div class="token-box">
                <div class="label">Your Token Number</div>
                <div class="number"><?= htmlspecialchars($my_token["token_number"]) ?></div>
                <span class="status-pill">● Waiting</span>
            </div>

            <div class="stats-row">
                <div class="stat-box">
                    <div class="num"><?= $position === 0 ? "Next!" : $position ?></div>
                    <div class="lbl"><?= $position === 0 ? "You're next" : "Ahead of you" ?></div>
                </div>
                <div class="stat-box">
                    <div class="num"><?= $position === 0 ? "~Now" : "~" . $wait_minutes . " min" ?></div>
                    <div class="lbl">Est. wait time</div>
                </div>
            </div>

            <div class="info-banner">
                Estimated wait is based on <?= $AVG_SERVICE_MINUTES ?> minutes per token.
                Please stay nearby and check back regularly.
            </div>

            <!-- Cancel token form -->
            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel your token?');">
                <input type="hidden" name="cancel_token_id" value="<?= (int) $my_token["token_id"] ?>">
                <button type="submit" class="btn btn-danger">Cancel My Token</button>
            </form>

        <?php endif; ?>

        <!-- Manual refresh button -->
        <form method="GET" action="queue_status.php" style="margin-top:10px;">
            <button type="submit" class="btn" style="background:#fff; color:#1e2f56; border:1px solid #d1d5db;">
                🔄 Refresh Status
            </button>
        </form>

        <div class="link-row" style="margin-top:24px;">
            <a href="dashboard.php">← Back to Services</a>
            &nbsp;|&nbsp;
            <a href="logout.php">Log Out</a>
        </div>
    </div>
</div>
</body>
</html>
