<?php
// insert_tank_level.php
// Expects POST: bin_id, level_code (0=Full, 1=Medium, 2=Low), optional inches
// Writes into: tank_levels (id, bin_id, level_code, inches, measured_at)

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

function fail($msg, $code=400){
  http_response_code($code);
  echo json_encode(["ok"=>false, "error"=>$msg]);
  exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
  fail("DB connection not available", 500);
}

$bin_id     = isset($_POST['bin_id']) ? trim($_POST['bin_id']) : '';
$level_code = isset($_POST['level_code']) ? (int)$_POST['level_code'] : null;
$inches     = isset($_POST['inches']) ? $_POST['inches'] : null;

if ($bin_id === '') fail("Missing bin_id");
if (!in_array($level_code, [0,1,2], true)) fail("Invalid level_code (0=Full, 1=Medium, 2=Low)");

// OPTIONAL: ignore duplicate consecutive states (safe even if ESP32 sends only on change)
$last = null;
$qs = $conn->prepare("SELECT level_code FROM tank_levels WHERE bin_id=? ORDER BY measured_at DESC, id DESC LIMIT 1");
if ($qs) {
  $qs->bind_param('s', $bin_id);
  if ($qs->execute()) {
    $res = $qs->get_result();
    $last = $res->fetch_assoc();
  }
  $qs->close();
}
if ($last && (int)$last['level_code'] === $level_code) {
  echo json_encode(["ok"=>true, "skipped"=>true, "message"=>"No change"]);
  exit;
}

if ($inches === null || $inches === '') {
  $stmt = $conn->prepare("INSERT INTO tank_levels (bin_id, level_code, inches) VALUES (?, ?, NULL)");
  if (!$stmt) fail("Prepare failed: ".$conn->error, 500);
  $stmt->bind_param("si", $bin_id, $level_code);
} else {
  $inchf = (float)$inches;
  $stmt = $conn->prepare("INSERT INTO tank_levels (bin_id, level_code, inches) VALUES (?, ?, ?)");
  if (!$stmt) fail("Prepare failed: ".$conn->error, 500);
  $stmt->bind_param("sid", $bin_id, $level_code, $inchf);
}

if (!$stmt->execute()) {
  $stmt->close();
  fail("Execute failed: ".$conn->error, 500);
}
$stmt->close();

echo json_encode([
  "ok"=>true,
  "bin_id"=>$bin_id,
  "level_code"=>$level_code,   // 0=Full, 1=Medium, 2=Low
  "inches" => ($inches === null || $inches === '' ? null : round((float)$inches, 2)),
  "measured_at"=>date('c')
]);
