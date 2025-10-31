<?php
// insert_transaction.php
header('Content-Type: application/json');

// OPTIONAL: if you call this from a browser page, you may enable CORS
// header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["ok"=>false, "error"=>"Method not allowed; use POST"]);
  exit;
}

// --- DB CONFIG ---
$host = "localhost";
$user = "root";       // change if needed
$pass = "";           // change if needed
$db   = "mysterybin"; // change if needed

// --- INPUTS (trim + basic sanitize) ---
$bin_id         = isset($_POST['bin_id'])         ? trim($_POST['bin_id'])         : '';
$transaction_id = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';
$total_grams_s  = isset($_POST['total_grams'])    ? trim($_POST['total_grams'])    : '';
$accepted_s     = isset($_POST['accepted_count']) ? trim($_POST['accepted_count']) : '';
$reward_type    = isset($_POST['reward_type'])    ? strtoupper(trim($_POST['reward_type'])) : '';
$reward_label   = isset($_POST['reward_label'])   ? trim($_POST['reward_label'])   : '';

// --- VALIDATION ---
$missing = [];
foreach ([
  'bin_id'         => $bin_id,
  'transaction_id' => $transaction_id,
  'total_grams'    => $total_grams_s,
  'accepted_count' => $accepted_s,
  'reward_type'    => $reward_type,
  'reward_label'   => $reward_label
] as $k => $v) {
  if ($v === '' && $v !== '0') $missing[] = $k;
}

if (!empty($missing)) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Missing fields", "fields"=>$missing]);
  exit;
}

if (!preg_match('/^TX-\d{6}$/', $transaction_id)) {
  http_response_code(422);
  echo json_encode(["ok"=>false, "error"=>"Invalid transaction_id format; expected TX-000000"]);
  exit;
}

$total_grams = filter_var($total_grams_s, FILTER_VALIDATE_FLOAT);
if ($total_grams === false || $total_grams < 0) {
  http_response_code(422);
  echo json_encode(["ok"=>false, "error"=>"total_grams must be a non-negative number"]);
  exit;
}

$accepted_count = filter_var($accepted_s, FILTER_VALIDATE_INT);
if ($accepted_count === false || $accepted_count < 0) {
  http_response_code(422);
  echo json_encode(["ok"=>false, "error"=>"accepted_count must be a non-negative integer"]);
  exit;
}

$allowed_rewards = ['A','B','C','D'];
if (!in_array($reward_type, $allowed_rewards, true)) {
  http_response_code(422);
  echo json_encode(["ok"=>false, "error"=>"reward_type must be one of A,B,C,D"]);
  exit;
}

// --- DB INSERT ---
$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) {
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"DB connect failed: ".$mysqli->connect_error]);
  exit;
}
$mysqli->set_charset('utf8mb4');

$sql = "INSERT INTO transactions
          (bin_id, transaction_id, total_grams, accepted_count, reward_type, reward_label)
        VALUES (?,?,?,?,?,?)";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"Prepare failed: ".$mysqli->error]);
  $mysqli->close();
  exit;
}

// ssdi**ss**  => s(bin) s(tx) d(total) i(count) s(type) s(label)
if (!$stmt->bind_param("ssdiss", $bin_id, $transaction_id, $total_grams, $accepted_count, $reward_type, $reward_label)) {
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"Bind failed: ".$stmt->error]);
  $stmt->close();
  $mysqli->close();
  exit;
}

if ($stmt->execute()) {
  echo json_encode([
    "ok" => true,
    "id" => $mysqli->insert_id,
    "transaction_id" => $transaction_id
  ]);
} else {
  // Duplicate key (transaction_id UNIQUE) => 1062
  if ($mysqli->errno === 1062) {
    http_response_code(409);
    echo json_encode(["ok"=>false, "error"=>"Duplicate transaction_id"]);
  } else {
    http_response_code(500);
    echo json_encode(["ok"=>false, "error"=>"Execute failed: ".$mysqli->error]);
  }
}

$stmt->close();
$mysqli->close();
