<?php
// notifications_mark_all.php
// POST only: marks all notifications as read
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false, 'error'=>'Method Not Allowed']);
  exit;
}

$ok = $conn->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
if (!$ok) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false, 'error'=>'DB error']);
  exit;
}

http_response_code(204); // No Content
