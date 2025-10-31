<?php
// insert_reward_level.php
// Inserts or updates a reward status row for reward_level table.
// Expects POST: bin_id, transaction_id, reward_code (A/B), reward_status (Full/Empty or 1/0).

header('Content-Type: application/json; charset=utf-8');

// OPTIONAL: allow same-LAN requests; adjust or remove if you don't need CORS
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: POST');
// header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/db.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "DB connection not available"]);
    exit;
}

// Helper: unify JSON error responses
function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(["ok" => false, "error" => $msg]);
    exit;
}

// Read & sanitize inputs
$bin_id         = isset($_POST['bin_id']) ? trim($_POST['bin_id']) : '';
$transaction_id = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';
$reward_code    = isset($_POST['reward_code']) ? strtoupper(trim($_POST['reward_code'])) : '';
$reward_status  = isset($_POST['reward_status']) ? trim($_POST['reward_status']) : '';

// Basic validation
if ($bin_id === '')            fail("Missing bin_id");
if ($reward_code !== 'A' && $reward_code !== 'B') fail("Invalid reward_code (use 'A' or 'B')");

// Normalize reward_status to tinyint: Full=1, Empty=0
$status_val = null;
if ($reward_status === '' || strcasecmp($reward_status, 'Full') === 0 || $reward_status === '1') {
    $status_val = 1;
} elseif (strcasecmp($reward_status, 'Empty') === 0 || $reward_status === '0') {
    $status_val = 0;
} else {
    // Try to parse numeric
    if (is_numeric($reward_status)) {
        $status_val = ((int)$reward_status) ? 1 : 0;
    } else {
        fail("Invalid reward_status (use 'Full'/'Empty' or 1/0)");
    }
}

// Ensure transaction_id meets your UNIQUE constraint:
// If empty or "TX-NONE", generate a unique one to avoid duplicate-key errors.
if ($transaction_id === '' || strcasecmp($transaction_id, 'TX-NONE') === 0) {
    $transaction_id = 'RL-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
}

// Prepare UPSERT: on duplicate transaction_id, update reward_status, reward_code, bin_id
$sql = "
INSERT INTO reward_level (transaction_id, bin_id, reward_code, reward_status)
VALUES (?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    bin_id = VALUES(bin_id),
    reward_code = VALUES(reward_code),
    reward_status = VALUES(reward_status)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    fail("Prepare failed: " . $conn->error, 500);
}
$stmt->bind_param("sssi", $transaction_id, $bin_id, $reward_code, $status_val);

if (!$stmt->execute()) {
    $stmt->close();
    fail("Execute failed: " . $conn->error, 500);
}

$affected = $stmt->affected_rows; // 1 insert, 2 update (MySQL may return 2 on DUPLICATE UPDATE)
$stmt->close();

echo json_encode([
    "ok" => true,
    "transaction_id" => $transaction_id,
    "bin_id" => $bin_id,
    "reward_code" => $reward_code,
    "reward_status" => $status_val, // 1=Full, 0=Empty
    "message" => ($affected === 1 ? "Inserted" : "Upserted/Updated")
]);
