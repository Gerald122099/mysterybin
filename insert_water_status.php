<?php
// insert_water_status.php
// Expected POST: bin_id, status ('WET' or 'DRY')

header('Content-Type: application/json');
require_once 'db.php'; // provides $conn (mysqli)

function out($code, $payload){ http_response_code($code); echo json_encode($payload); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok'=>false,'error'=>'Method Not Allowed']);

$bin_id = isset($_POST['bin_id']) ? trim($_POST['bin_id']) : '';
$status = isset($_POST['status']) ? strtoupper(trim($_POST['status'])) : '';

if ($bin_id === '' || ($status !== 'WET' && $status !== 'DRY')) {
  out(400, ['ok'=>false,'error'=>'Invalid payload']);
}
if (strlen($bin_id) > 64) out(400, ['ok'=>false,'error'=>'bin_id too long']);

// Ensure table exists (optional safety)
$conn->query("
  CREATE TABLE IF NOT EXISTS water_status_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bin_id VARCHAR(64) NOT NULL,
    status ENUM('WET','DRY') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY (bin_id), KEY (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Get last status to avoid duplicates
$last = null;
if ($st = $conn->prepare("SELECT status FROM water_status_logs WHERE bin_id=? ORDER BY created_at DESC, id DESC LIMIT 1")) {
  $st->bind_param("s",$bin_id);
  if ($st->execute()) { $st->bind_result($last); $st->fetch(); }
  $st->close();
}
if ($last !== null && $last === $status) {
  out(200, ['ok'=>true,'changed'=>false,'message'=>'No change']);
}

// Insert water log
$ok=false; $insertId=null;
if ($st = $conn->prepare("INSERT INTO water_status_logs (bin_id, status) VALUES (?,?)")) {
  $st->bind_param("ss",$bin_id,$status);
  $ok = $st->execute();
  $insertId = $st->insert_id ?? null;
  $st->close();
}
if (!$ok) out(500, ['ok'=>false,'error'=>'DB insert failed']);

// Ensure notifications table exists
$conn->query("
  CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY (is_read), KEY (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Add notification row
$msg = "Water sensor: {$status} on {$bin_id}";
if ($st = $conn->prepare("INSERT INTO notifications (message, is_read) VALUES (?, 0)")) {
  $st->bind_param("s",$msg);
  $st->execute();
  $st->close();
}

out(200, ['ok'=>true,'changed'=>true,'id'=>$insertId,'bin_id'=>$bin_id,'status'=>$status]);
