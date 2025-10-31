<?php
// status.php — returns live status used by JS polling
header('Content-Type: application/json');
require_once 'db.php';

// Today’s collection
$row = $conn->query("SELECT SUM(total_grams) total FROM transactions WHERE DATE(created_at)=CURDATE()")->fetch_assoc();
$total_today = (int)($row['total'] ?? 0);

// Tank latest (also use its bin_id when available)
$tank_row = $conn->query("
  SELECT bin_id, level_code, inches, measured_at
  FROM tank_levels
  ORDER BY measured_at DESC
  LIMIT 1
")->fetch_assoc();

$labels = [0=>'FULL', 1=>'MEDIUM', 2=>'EMPTY'];
$tank = null;
if ($tank_row) {
  $lc = (int)$tank_row['level_code'];
  $tank = [
    'bin_id' => $tank_row['bin_id'],
    'level_code' => $lc,
    'label' => $labels[$lc] ?? 'N/A',
    'inches' => $tank_row['inches'] !== null ? (float)$tank_row['inches'] : null,
    'measured_at' => $tank_row['measured_at']
  ];
}

// Decide which bin_id to use for rewards
$BIN_ID = $tank_row['bin_id'] ?? 'BIN-001';

// --------- Rewards (live) from reward_level ONLY ---------
$rewards = ['A'=>['level'=>null],'B'=>['level'=>null]]; // level is 0 or 100 for UI

$rl = $conn->prepare("SELECT reward_code, reward_status FROM reward_level WHERE bin_id=? AND reward_code IN ('A','B')");
$rl->bind_param('s', $BIN_ID);
if ($rl->execute()) {
  $rs = $rl->get_result();
  while ($row = $rs->fetch_assoc()) {
    $code = strtoupper($row['reward_code']);     // 'A' or 'B'
    $status = (int)$row['reward_status'];        // 1=Full, 0=Empty
    $pct = $status ? 100 : 0;                    // map to percent for UI
    if ($code === 'A') $rewards['A']['level'] = $pct;
    if ($code === 'B') $rewards['B']['level'] = $pct;
  }
  $rs->free();
}
$rl->close();

// Recent transactions (10)
$recent_tx = [];
$tx = $conn->query("SELECT transaction_id, bin_id, total_grams, accepted_count, reward_type, reward_label, created_at FROM transactions ORDER BY created_at DESC LIMIT 10");
while ($t = $tx->fetch_assoc()) $recent_tx[] = $t;

// Notifications (5) + unread count
$notifications = [];
$nq = $conn->query("SELECT id, message, is_read, created_at FROM notifications ORDER BY created_at DESC LIMIT 5");
while ($n = $nq->fetch_assoc()) $notifications[] = $n;
$unread = $conn->query("SELECT COUNT(*) c FROM notifications WHERE is_read=0")->fetch_assoc();
$unread_count = (int)($unread['c'] ?? 0);
$latest_notif_id = count($notifications) ? (int)$notifications[0]['id'] : 0;

// Water logs (latest 5)
$water_logs = [];
$wq = $conn->query("SELECT id, bin_id, status, created_at FROM water_status_logs ORDER BY created_at DESC LIMIT 5");
while ($w = $wq->fetch_assoc()) $water_logs[] = $w;
$latest_water_id = count($water_logs) ? (int)$water_logs[0]['id'] : 0;

echo json_encode([
  'total_today' => $total_today,
  'tank' => $tank,
  'rewards' => $rewards,  // A.level / B.level => 0% or 100%
  'recent_tx' => $recent_tx,
  'notifications' => [
    'items' => $notifications,
    'unread_count' => $unread_count,
    'latest_id' => $latest_notif_id
  ],
  'water_logs' => [
    'items' => $water_logs,
    'latest_id' => $latest_water_id
  ]
], JSON_UNESCAPED_SLASHES);
