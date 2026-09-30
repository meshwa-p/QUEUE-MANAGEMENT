<?php
require_once "config.php";

// ============================================================
// Guard: only staff or admin can access this page.
// Same session-check pattern used in admin_users.php.
// ============================================================
if (!isset($_SESSION["user_id"]) ||
    !in_array($_SESSION["role"], ["staff", "admin"])) {
    header("Location: login.php");
    exit;
}

$msg = "";

// ============================================================
// Action: Call Next — set oldest 'waiting' token for a service
//         to 'called' and stamp called_at.
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["call_next"])) {
    $call_service_id = (int) $_POST["call_next"];

    $call_stmt = $conn->prepare(
        "UPDATE tokens
         SET status = 'called', called_at = NOW()
         WHERE service_id = ?
           AND status = 'waiting'
           AND DATE(created_at) = CURDATE()
         ORDER BY created_at ASC
         LIMIT 1"
    );
    $call_stmt->bind_param("i", $call_service_id);
    $call_stmt->execute();
    $affected = $call_stmt->affected_rows;
    $call_stmt->close();

    $msg = $affected > 0
        ? "Next token called successfully."
        : "No waiting tokens found for that service.";
}

// ============================================================
// Action: Update a specific token status (served / no_show /
//         cancelled). Staff clicks one of the action buttons.
// ============================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_token_id"])) {
    $update_id     = (int) $_POST["update_token_id"];
    $new_status    = $_POST["new_status"] ?? "";

    $allowed = ["served", "no_show", "cancelled"];
    if (in_array($new_status, $allowed)) {
        // served → stamp served_at; others → only update status
        if ($new_status === "served") {
            $upd_stmt = $conn->prepare(
                "UPDATE tokens SET status = 'served', served_at = NOW()
                 WHERE token_id = ? AND status = 'called'"
            );
        } else {
            $upd_stmt = $conn->prepare(
                "UPDATE tokens SET status = ?
                 WHERE token_id = ? AND status IN ('waiting','called')"
            );
            $upd_stmt->bind_param("si", $new_status, $update_id);
            $upd_stmt->execute();
            $affected = $upd_stmt->affected_rows;
            $upd_stmt->close();
            $msg = $affected > 0 ? "Token updated to: " . $new_status : "Token could not be updated.";
            goto after_update;
        }
        $upd_stmt->bind_param("i", $update_id);
        $upd_stmt->execute();
        $affected = $upd_stmt->affected_rows;
        $upd_stmt->close();
        $msg = $affected > 0 ? "Token marked as served." : "Token could not be updated.";
    } else {
        $msg = "Invalid status.";
    }
    after_update:
}

// ============================================================
// Load all active services
// ============================================================
$services_res = $conn->query(
    "SELECT service_id, name, icon FROM services WHERE is_active = 1 ORDER BY service_id"
);
$services = [];
while ($s = $services_res->fetch_assoc()) {
    $services[] = $s;
}

// ============================================================
// For each service: fetch waiting queue + currently called tokens
// ============================================================
$queues = [];
foreach ($services as $svc) {
    $sid = (int) $svc["service_id"];

    // Waiting tokens (oldest first)
    $w_stmt = $conn->prepare(
        "SELECT t.token_id, t.token_number, t.created_at, u.full_name
         FROM tokens t
         JOIN users u ON t.user_id = u.user_id
         WHERE t.service_id = ?
           AND t.status = 'waiting'
           AND DATE(t.created_at) = CURDATE()
         ORDER BY t.created_at ASC"
    );
    $w_stmt->bind_param("i", $sid);
    $w_stmt->execute();
    $w_result = $w_stmt->get_result();
    $waiting  = [];
    while ($row = $w_result->fetch_assoc()) {
        $waiting[] = $row;
    }
    $w_stmt->close();

    // Currently called tokens
    $c_stmt = $conn->prepare(
        "SELECT t.token_id, t.token_number, t.called_at, u.full_name
         FROM tokens t
         JOIN users u ON t.user_id = u.user_id
         WHERE t.service_id = ?
           AND t.status = 'called'
           AND DATE(t.created_at) = CURDATE()
         ORDER BY t.called_at ASC"
    );
    $c_stmt->bind_param("i", $sid);
    $c_stmt->execute();
    $c_result = $c_stmt->get_result();
    $called   = [];
    while ($row = $c_result->fetch_assoc()) {
        $called[] = $row;
    }
    $c_stmt->close();

    $queues[] = [
        "service" => $svc,
        "waiting" => $waiting,
        "called"  => $called,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Dashboard - Queue System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="card wide-card">

    <div class="topbar">
        <div>
            <h2>Staff Queue Dashboard</h2>
            <p class="subtitle">
                Logged in as <?= htmlspecialchars($_SESSION["full_name"]) ?>
                (<span class="badge badge-<?= htmlspecialchars($_SESSION["role"]) ?>"><?= ucfirst($_SESSION["role"]) ?></span>)
                &nbsp;—&nbsp; <?= date("D, d M Y") ?>
            </p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <?php if ($_SESSION["role"] === "admin"): ?>
                <a href="admin_users.php" class="btn" style="width:auto; text-decoration:none; padding:10px 18px; background:#5b21b6;">
                    User Mgmt
                </a>
            <?php endif; ?>
            <a href="logout.php" class="btn" style="width:auto; text-decoration:none; padding:10px 18px;">Log Out</a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success" style="margin-bottom:20px;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php foreach ($queues as $q): ?>
        <?php
            $svc    = $q["service"];
            $sid    = (int) $svc["service_id"];
            $w_cnt  = count($q["waiting"]);
            $c_cnt  = count($q["called"]);
        ?>

        <div class="queue-panel" style="margin-bottom:32px;">

            <!-- Service header row -->
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div class="icon" style="width:38px; height:38px; background:#e8ecf3; border-radius:10px;
                                             display:flex; align-items:center; justify-content:center; font-size:18px;">
                        <?= htmlspecialchars($svc["icon"]) ?>
                    </div>
                    <div>
                        <strong style="color:#1e2f56; font-size:16px;"><?= htmlspecialchars($svc["name"]) ?></strong>
                        <div style="font-size:12px; color:#6b7280;">
                            <span class="badge badge-staff"><?= $w_cnt ?> waiting</span>
                            &nbsp;
                            <span class="badge badge-admin"><?= $c_cnt ?> called</span>
                        </div>
                    </div>
                </div>

                <!-- Call Next button -->
                <form method="POST">
                    <input type="hidden" name="call_next" value="<?= $sid ?>">
                    <button type="submit" class="btn"
                            style="width:auto; padding:9px 20px; font-size:14px;"
                            <?= $w_cnt === 0 ? "disabled" : "" ?>>
                        📢 Call Next
                    </button>
                </form>
            </div>

            <!-- Currently Called tokens -->
            <?php if ($c_cnt > 0): ?>
                <p style="font-size:12px; letter-spacing:1px; text-transform:uppercase; color:#9ca3af; margin-bottom:8px;">
                    Currently Called
                </p>
                <table style="margin-bottom:16px;">
                    <tr>
                        <th>Token</th>
                        <th>Student</th>
                        <th>Called At</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($q["called"] as $tok): ?>
                    <tr>
                        <td><strong style="color:#1e2f56;"><?= htmlspecialchars($tok["token_number"]) ?></strong></td>
                        <td><?= htmlspecialchars($tok["full_name"]) ?></td>
                        <td><?= htmlspecialchars(date("H:i", strtotime($tok["called_at"]))) ?></td>
                        <td>
                            <!-- Mark Served -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="update_token_id" value="<?= (int) $tok["token_id"] ?>">
                                <input type="hidden" name="new_status" value="served">
                                <button type="submit" class="action-link edit"
                                        style="background:none; border:none; cursor:pointer; font-size:13px;">
                                    ✅ Served
                                </button>
                            </form>
                            &nbsp;
                            <!-- Mark No Show -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="update_token_id" value="<?= (int) $tok["token_id"] ?>">
                                <input type="hidden" name="new_status" value="no_show">
                                <button type="submit" class="action-link disable"
                                        style="background:none; border:none; cursor:pointer; font-size:13px;">
                                    ⚠️ No Show
                                </button>
                            </form>
                            &nbsp;
                            <!-- Cancel -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="update_token_id" value="<?= (int) $tok["token_id"] ?>">
                                <input type="hidden" name="new_status" value="cancelled">
                                <button type="submit" class="action-link disable"
                                        style="background:none; border:none; cursor:pointer; font-size:13px;">
                                    ❌ Cancel
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <!-- Waiting queue -->
            <p style="font-size:12px; letter-spacing:1px; text-transform:uppercase; color:#9ca3af; margin-bottom:8px;">
                Waiting Queue (<?= $w_cnt ?>)
            </p>

            <?php if ($w_cnt === 0): ?>
                <p style="color:#6b7280; font-size:14px; margin-bottom:8px;">No one waiting for this service.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>#</th>
                        <th>Token</th>
                        <th>Student</th>
                        <th>Queued At</th>
                    </tr>
                    <?php foreach ($q["waiting"] as $i => $tok): ?>
                    <tr>
                        <td style="color:#6b7280; font-size:13px;"><?= $i + 1 ?></td>
                        <td><strong style="color:#1e2f56;"><?= htmlspecialchars($tok["token_number"]) ?></strong></td>
                        <td><?= htmlspecialchars($tok["full_name"]) ?></td>
                        <td><?= htmlspecialchars(date("H:i", strtotime($tok["created_at"]))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <hr style="border:none; border-top:1px solid #e5e7eb; margin-top:20px;">
        </div>

    <?php endforeach; ?>

</div>
</body>
</html>
