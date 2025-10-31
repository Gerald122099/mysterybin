<?php
// dashboard.php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: index.php"); exit; }
include 'db.php';

/* ------------------ CONFIG LOAD / SAVE (for Settings page) ------------------ */
$configPath = __DIR__ . '/config.json';
$configDefaults = [
  "weight_thresh_g" => 50,
  "water_threshold" => 1800,
  "reward_on_ms"    => 2000,
  // Reward windows (defaults; can be edited in Settings)
  "a_min" => 100, "a_max" => 120,
  "b_min" => 140, "b_max" => 150,
  // Versioning fields — ESP32 only applies when ver changes
  "config_version" => 1,
  "updated_at" => date('c')
];
$configData = $configDefaults;
if (file_exists($configPath)) {
  $raw = @file_get_contents($configPath);
  $json = json_decode($raw, true);
  if (is_array($json)) $configData = array_merge($configDefaults, $json);
}
$noticeMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
  // Basic sanitation and constraints
  $configData['weight_thresh_g'] = max(1, min(1000, (float)($_POST['weight_thresh_g'] ?? $configData['weight_thresh_g'])));
  $configData['water_threshold'] = max(0, min(4095, (int)($_POST['water_threshold'] ?? $configData['water_threshold'])));
  $configData['reward_on_ms']    = max(200, min(30000, (int)($_POST['reward_on_ms'] ?? $configData['reward_on_ms'])));

  $configData['a_min'] = max(0, (float)($_POST['a_min'] ?? $configData['a_min']));
  $configData['a_max'] = max($configData['a_min'], (float)($_POST['a_max'] ?? $configData['a_max']));
  $configData['b_min'] = max($configData['a_max'], (float)($_POST['b_min'] ?? $configData['b_min']));
  $configData['b_max'] = max($configData['b_min'], (float)($_POST['b_max'] ?? $configData['b_max']));

  // Bump version and timestamp ONLY when saving
  $configData['config_version'] = (int)($configData['config_version'] ?? 1) + 1;
  $configData['updated_at'] = date('c');

  // Save atomically
  $tmp = $configPath . '.tmp';
  file_put_contents($tmp, json_encode($configData, JSON_PRETTY_PRINT));
  rename($tmp, $configPath);
  $noticeMsg = 'Configuration saved!';
}

/* ------------------ DASHBOARD DATA (initial paint) ------------------ */
// Today's total
$total_today = $conn->query("SELECT SUM(total_grams) AS total FROM transactions WHERE DATE(created_at)=CURDATE()")
                ->fetch_assoc()['total'] ?? 0;

// Recent transactions (top 10)
$logs = $conn->query("SELECT * FROM transactions ORDER BY created_at DESC LIMIT 10");

// Tank (latest)
$tank = $conn->query("
    SELECT bin_id, level_code, inches, measured_at
    FROM tank_levels
    ORDER BY measured_at DESC
    LIMIT 1
")->fetch_assoc();

$level_labels = [0 => 'FULL', 1 => 'MEDIUM', 2 => 'EMPTY'];
$tank_label  = ($tank && isset($level_labels[(int)$tank['level_code']])) ? $level_labels[(int)$tank['level_code']] : 'N/A';
$tank_inches = $tank['inches'] ?? null;
$tank_time   = $tank['measured_at'] ?? null;

// Rewards — specifically A & B from reward_level (0% or 100% based on reward_status)
$BIN_ID = $tank['bin_id'] ?? 'BIN-001';
$rewardA = ['name'=>'Reward A','level'=>null];
$rewardB = ['name'=>'Reward B','level'=>null];

if ($stmt = $conn->prepare("SELECT reward_code, reward_status FROM reward_level WHERE bin_id=? AND reward_code IN ('A','B')")) {
    $stmt->bind_param('s', $BIN_ID);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $code = strtoupper($r['reward_code']);       // 'A' or 'B'
            $pct  = ((int)$r['reward_status']) ? 0 : 100; // 1=Full => 100%, 0=Empty => 0%
            if ($code === 'A') $rewardA['level'] = $pct;
            if ($code === 'B') $rewardB['level'] = $pct;
        }
        $res->free();
    }
    $stmt->close();
}
// Notifications (initial only for dropdown)
$notifications = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5")
                  ->fetch_all(MYSQLI_ASSOC);
$unread_count = $conn->query("SELECT COUNT(*) AS count FROM notifications WHERE is_read=0")
                ->fetch_assoc()['count'] ?? 0;

// 7-day analytics (static at first)
$analytics_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $row = $conn->query("SELECT SUM(total_grams) AS total FROM transactions WHERE DATE(created_at)='$date'")
           ->fetch_assoc();
    $analytics_data[$date] = $row && $row['total'] ? (int)$row['total'] : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CTU Mystery Bin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
  :root{
    --primary:#2e7d32;--primary-light:#4caf50;--primary-dark:#1b5e20;
    --secondary:#f57c00;--danger:#d32f2f;--warning:#fbc02d;--success:#388e3c;--info:#1976d2;
    --light:#f5f5f5;--dark:#333;--gray:#e0e0e0;--card-shadow:0 4px 12px rgba(0,0,0,.08);--transition:all .3s ease;
  }
  *{margin:0;padding:0;box-sizing:border-box;font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif}
  body{background:#f8f9fa;color:var(--dark);line-height:1.6}
  .dashboard-container{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
  .sidebar{background:var(--primary);color:#fff;padding:20px 0;position:fixed;width:250px;height:100vh;overflow-y:auto}
  .brand{padding:0 20px 20px;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:20px}
  .brand-content{display:flex;align-items:center;gap:12px}
  .logo-img{width:50px;height:50px;object-fit:contain;border-radius:6px}
  .brand h1{font-size:1.3rem;margin:0;line-height:1.2}
  .brand-subtitle{font-size:0.8rem;opacity:0.8;margin-top:2px}
  .nav-item{padding:12px 20px;display:flex;align-items:center;gap:12px;color:rgba(255,255,255,.8);text-decoration:none;transition:var(--transition)}
  .nav-item:hover,.nav-item.active{background:rgba(255,255,255,.1);color:#fff}
  .main-content{grid-column:2;padding:20px 30px}
  .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;padding-bottom:15px;border-bottom:1px solid var(--gray)}
  .user-actions{display:flex;align-items:center;gap:15px}
  .notification-container{position:relative}
  .notification-btn{background:var(--primary-light);color:#fff;border:none;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;transition:var(--transition)}
  .notification-btn:hover{background:var(--primary-dark)}
  .notification-badge{position:absolute;top:-5px;right:-5px;background:var(--danger);color:#fff;border-radius:50%;width:20px;height:20px;font-size:.7rem;display:flex;align-items:center;justify-content:center}
  .notification-dropdown{position:absolute;top:50px;right:0;width:350px;background:#fff;border-radius:8px;box-shadow:0 5px 15px rgba(0,0,0,.1);z-index:100;display:none}
  .notification-dropdown.active{display:block}
  .notification-header{padding:15px;border-bottom:1px solid var(--gray);display:flex;justify-content:space-between;align-items:center}
  .notification-list{max-height:300px;overflow-y:auto}
  .notification-item{padding:12px 15px;border-bottom:1px solid var(--gray);display:flex;gap:10px;cursor:pointer;transition:var(--transition)}
  .notification-item:hover{background:#f9f9f9}
  .notification-item.unread{background:#f0f7ff}
  .notification-icon{color:var(--info);font-size:1.2rem}
  .notification-content{flex:1}
  .notification-time{font-size:.8rem;color:#777}
  .logout-btn{background:var(--primary);color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;display:flex;align-items:center;gap:8px;transition:var(--transition)}
  .logout-btn:hover{background:var(--primary-dark)}
  .stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-bottom:30px}
  .stat-card{background:#fff;border-radius:12px;padding:20px;box-shadow:var(--card-shadow);transition:var(--transition)}
  .stat-card:hover{transform:translateY(-5px);box-shadow:0 6px 16px rgba(0,0,0,.12)}
  .stat-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
  .stat-icon{width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5rem}
  .collection-icon{background:rgba(46,125,50,.1);color:var(--primary)}
  .bin-icon{background:rgba(211,47,47,.1);color:var(--danger)}
  .reward-icon{background:rgba(245,124,0,.1);color:var(--secondary)}
  .stat-value{font-size:2rem;font-weight:700;margin-bottom:5px}
  .stat-title{color:#666;font-size:.9rem}
  .alert-badge{background:var(--danger);color:#fff;padding:4px 8px;border-radius:20px;font-size:.8rem;margin-top:10px;display:inline-block}
  .analytics-card{background:#fff;border-radius:12px;padding:20px;box-shadow:var(--card-shadow);margin-bottom:30px}
  .chart-container{height:300px;margin-top:20px;position:relative}
  .chart-bar{display:flex;align-items:flex-end;justify-content:space-between;height:100%;gap:10px;padding:0 10px}
  .chart-column{flex:1;background:var(--primary-light);border-radius:6px 6px 0 0;position:relative;transition:var(--transition);cursor:pointer}
  .chart-column:hover{background:var(--primary)}
  .chart-label{position:absolute;bottom:-25px;left:0;right:0;text-align:center;font-size:.8rem;color:#666}
  .chart-value{position:absolute;top:-25px;left:0;right:0;text-align:center;font-weight:700}
  .table-container{background:#fff;border-radius:12px;overflow:hidden;box-shadow:var(--card-shadow);margin-bottom:30px;overflow-x:auto}
  table{width:100%;border-collapse:collapse}
  th,td{padding:16px;text-align:left;border-bottom:1px solid var(--gray)}
  th{background:#f7f7f7;font-weight:600;color:#555}
  tr:last-child td{border-bottom:none}
  tr:hover{background:#f9f9f9}
  .reset-section{text-align:center;margin:30px 0}
  .reset-btn{background:var(--primary);color:#fff;border:none;padding:12px 24px;border-radius:6px;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:8px;transition:var(--transition)}
  .reset-btn:hover{background:var(--primary-dark);transform:translateY(-2px)}
  .page-content{display:none}.page-content.active{display:block}

  /* Toast popup (LARGER) */
  .toast { position: fixed; right: 24px; bottom: 24px; background: #fff; color: #222; padding: 16px 20px; border-radius: 14px; box-shadow: 0 14px 40px rgba(0,0,0,.18); display: flex; align-items: center; gap: 12px; z-index: 9999; opacity: 0; transform: translateY(12px); min-width: 380px; max-width: 560px; font-size: 1rem; line-height: 1.35; animation: toast-in .25s ease forwards; }
  .toast i { font-size: 1.25rem; color: var(--info); }
  @keyframes toast-in { to { opacity: 1; transform: translateY(0); } }

  @media(max-width:992px){
    .dashboard-container{grid-template-columns:1fr}
    .sidebar{display:none}
    .main-content{grid-column:1}
    .stats-grid{grid-template-columns:repeat(auto-fill,minmax(250px,1fr))}
    .notification-dropdown{width:300px;right:-50px}
  }
  @media(max-width:576px){
    .main-content{padding:15px}
    .stats-grid{grid-template-columns:1fr}
    .header{flex-direction:column;align-items:flex-start;gap:15px}
    .notification-dropdown{width:280px;right:-80px}
  }
  </style>
</head>
<body>
<div class="dashboard-container">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-content">
        <img src="binlogo.png" alt="CTU Mystery Bin Logo" class="logo-img">
        <div class="brand-text">
          <h1>Mystery Bin</h1>
          <span class="brand-subtitle">CTU</span>
        </div>
      </div>
    </div>
    <a href="#" class="nav-item active" data-page="dashboard"><i class="fas fa-home"></i> Dashboard</a>
    <a href="#" class="nav-item" data-page="settings"><i class="fas fa-cog"></i> Settings</a>
    <a href="index.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>

  <!-- Main -->
  <main class="main-content">
    <div class="header">
      <h2>Dashboard Overview</h2>
      <div class="user-actions">
        <span>Welcome, Admin</span>
        <div class="notification-container">
          <button class="notification-btn" id="notifBtn">
            <i class="fas fa-bell"></i>
            <?php if ($unread_count>0): ?>
              <span class="notification-badge" id="notifBadge"><?php echo (int)$unread_count; ?></span>
            <?php else: ?>
              <span class="notification-badge" id="notifBadge" style="display:none;">0</span>
            <?php endif; ?>
          </button>
          <div class="notification-dropdown" id="notifDropdown">
            <div class="notification-header">
              <h3>Notifications</h3>
              <a href="#" class="mark-all-read">Mark all as read</a>
            </div>
            <div class="notification-list" id="notifList">
              <?php if (count($notifications)): foreach ($notifications as $n): ?>
                <div class="notification-item <?php echo $n['is_read']?'':'unread'; ?>" data-id="<?php echo (int)$n['id']; ?>">
                  <div class="notification-icon"><i class="fas fa-bell"></i></div>
                  <div class="notification-content">
                    <p><?php echo htmlspecialchars($n['message']); ?></p>
                    <div class="notification-time"><?php echo htmlspecialchars($n['created_at']); ?></div>
                  </div>
                </div>
              <?php endforeach; else: ?>
                <div class="notification-item"><div class="notification-content"><p>No notifications</p></div></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <a href="index.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>

    <!-- Dashboard Page -->
    <div class="page-content active" id="dashboard-page">

      <!-- Stats row: Today's Collection, Tank Level, Reward A, Reward B -->
      <div class="stats-grid">
        <!-- Today's Collection -->
        <div class="stat-card">
          <div class="stat-header">
            <div>
              <div class="stat-value" id="todayCollection"><?php echo (int)$total_today; ?> g</div>
              <div class="stat-title">Today's Collection</div>
            </div>
            <div class="stat-icon collection-icon"><i class="fas fa-weight-hanging"></i></div>
          </div>
        </div>

        <!-- Tank Level (status only: FULL / MEDIUM / LOW) -->
        <div class="stat-card">
          <div class="stat-header">
            <div>
              <div class="stat-value" id="tankStatus"><?php echo htmlspecialchars($tank_label); ?></div>
              <div class="stat-title" id="tankDetail">
                Tank Level<?php if ($tank_inches!==null) echo ' — ('.htmlspecialchars($tank_inches).' in)'; ?>
              </div>
              <div class="alert-badge" id="tankAlert" style="<?php echo ($tank_label==='FULL')?'':'display:none;'; ?>">Needs Attention</div>
              <div style="font-size:.8rem;color:#777;margin-top:6px;" id="tankUpdated" <?php echo $tank_time?'':'style="display:none"'; ?>>
                <?php if ($tank_time) echo 'Updated: '.htmlspecialchars($tank_time); ?>
              </div>
            </div>
            <div class="stat-icon bin-icon" id="tankIconWrap"><i class="fas fa-trash-alt"></i></div>
          </div>
        </div>

        <!-- Reward A -->
        <div class="stat-card">
          <div class="stat-header">
            <div>
              <div class="stat-value" id="rewardAValue">
                <?php echo $rewardA['level']!==null ? (int)$rewardA['level'].'%' : 'N/A'; ?>
              </div>
              <div class="stat-title">Reward A Status</div>
            </div>
            <div class="stat-icon reward-icon" id="rewardAIcon"><i class="fas fa-gift"></i></div>
          </div>
        </div>

        <!-- Reward B -->
        <div class="stat-card">
          <div class="stat-header">
            <div>
              <div class="stat-value" id="rewardBValue">
                <?php echo $rewardB['level']!==null ? (int)$rewardB['level'].'%' : 'N/A'; ?>
              </div>
              <div class="stat-title">Reward B Status</div>
            </div>
            <div class="stat-icon reward-icon" id="rewardBIcon"><i class="fas fa-gift"></i></div>
          </div>
        </div>
      </div>

      <!-- Analytics Section -->
      <div class="analytics-card">
        <h3 class="section-title"><i class="fas fa-chart-line"></i> Collection Analytics (Last 7 Days)</h3>
        <div class="chart-container">
          <div class="chart-bar">
            <?php 
            $max_value = max($analytics_data);
            foreach ($analytics_data as $date=>$value):
              $h = $max_value>0 ? ($value/$max_value)*100 : 0;
              $day = date('D', strtotime($date));
            ?>
              <div class="chart-column" style="height: <?php echo $h; ?>%" title="<?php echo $date; ?>: <?php echo $value; ?>g">
                <div class="chart-value"><?php echo $value; ?>g</div>
                <div class="chart-label"><?php echo $day; ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Recent Transactions -->
      <h3 class="section-title"><i class="fas fa-history"></i> Recent Transactions</h3>
      <div class="table-container">
        <table>
          <thead><tr>
            <th>Transaction ID</th><th>Bin ID</th><th>Weight</th><th>Accepted Items</th><th>Reward</th><th>Date</th>
          </tr></thead>
          <tbody id="recentTxBody">
            <?php while ($log=$logs->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($log['transaction_id']); ?></td>
                <td><?php echo htmlspecialchars($log['bin_id']); ?></td>
                <td><?php echo (int)$log['total_grams']; ?> g</td>
                <td><?php echo (int)$log['accepted_count']; ?></td>
                <td><?php echo htmlspecialchars($log['reward_label']).' ('.htmlspecialchars($log['reward_type']).')'; ?></td>
                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- Reset -->
      <div class="reset-section">
        <form action="reset.php" method="post">
          <button type="submit" name="reset" class="reset-btn">
            <i class="fas fa-sync-alt"></i> Reset Bin Status
          </button>
        </form>
      </div>
    </div>

    <!-- Settings Page (CONFIG FORM EMBEDDED) -->
    <div class="page-content" id="settings-page">
      <h3 class="section-title"><i class="fas fa-cog"></i> Device Configuration</h3>

      <?php if (!empty($noticeMsg)): ?>
        <div style="background:#e8f5e9;border:1px solid #c8e6c9;color:#1b5e20;padding:12px 14px;border-radius:10px;margin:12px 0;">
          <i class="fas fa-check-circle"></i> <?= htmlspecialchars($noticeMsg) ?>
        </div>
      <?php endif; ?>

      <!-- Version hint -->
      <p style="color:#666;margin:6px 0 14px;">
        Current config version: <b><?= (int)$configData['config_version'] ?></b> • Updated:
        <b><?= htmlspecialchars($configData['updated_at']) ?></b>
      </p>

      <div class="analytics-card" style="margin-top:10px;">
        <form method="post" class="config-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
          <div class="stat-card">
            <div class="stat-header">
              <div><div class="stat-title">Reject if single bottle weight ≥ (g)</div></div>
              <div class="stat-icon collection-icon"><i class="fas fa-weight-hanging"></i></div>
            </div>
            <input type="number" step="0.1" min="1" max="1000" name="weight_thresh_g"
                   value="<?= htmlspecialchars($configData['weight_thresh_g']) ?>"
                   style="width:100%;padding:12px 10px;border:1px solid #ccc;border-radius:10px;font-size:16px;">
          </div>

          <div class="stat-card">
            <div class="stat-header">
              <div><div class="stat-title">Water sensor threshold (ADC ≥)</div></div>
              <div class="stat-icon bin-icon"><i class="fas a-tint fas fa-tint"></i></div>
            </div>
            <input type="number" min="0" max="4095" name="water_threshold"
                   value="<?= htmlspecialchars($configData['water_threshold']) ?>"
                   style="width:100%;padding:12px 10px;border:1px solid #ccc;border-radius:10px;font-size:16px;">
          </div>

          <div class="stat-card">
            <div class="stat-header">
              <div><div class="stat-title">Reward ON time (ms)</div></div>
              <div class="stat-icon reward-icon"><i class="fas fa-stopwatch"></i></div>
            </div>
            <input type="number" min="200" max="30000" name="reward_on_ms"
                   value="<?= htmlspecialchars($configData['reward_on_ms']) ?>"
                   style="width:100%;padding:12px 10px;border:1px solid #ccc;border-radius:10px;font-size:16px;">
          </div>

          <div class="stat-card">
            <div class="stat-header"><div><div class="stat-title">Reward A min (g)</div></div>
              <div class="stat-icon reward-icon"><i class="fas fa-gift"></i></div>
            </div>
            <input type="number" step="0.1" min="0" name="a_min"
                   value="<?= htmlspecialchars($configData['a_min']) ?>"
                   style="width:100%;padding:12px 10px;border:1px solid #ccc;border-radius:10px;font-size:16px;">
          </div>

          <div class="stat-card">
            <div class="stat-header"><div><div class="stat-title">Reward A max (g)</div></div>
              <div class="stat-icon reward-icon"><i class="fas fa-gift"></i></div>
            </div>
            <input type="number" step="0.1" min="0" name="a_max"
                   value="<?= htmlspecialchars($configData['a_max']) ?>"
                   style="width:100%;padding:12px 10px;border:1px solid #ccc;border-radius:10px;font-size:16px;">
          </div>

          <div class="stat-card">
            <div class="stat-header"><div><div class="stat-title">Reward B min (g)</div></div>
              <div class="stat-icon reward-icon"><i class="fas fa-gift"></i></div>
            </div>
            <input type="number" step="0.1" min="0" name="b_min"
                   value="<?= htmlspecialchars($configData['b_min']) ?>"
                   style="width:100%;padding:12px 10px;border:1px solid #ccc;border-radius:10px;font-size:16px;">
          </div>

          <div class="stat-card">
            <div class="stat-header"><div><div class="stat-title">Reward B max (g)</div></div>
              <div class="stat-icon reward-icon"><i class="fas fa-gift"></i></div>
            </div>
            <input type="number" step="0.1" min="0" name="b_max"
                   value="<?= htmlspecialchars($configData['b_max']) ?>"
                   style="width:100%;padding:12px 10px;border:1px solid #ccc;border-radius:10px;font-size:16px;">
          </div>

          <div class="full" style="grid-column:1/-1;display:flex;gap:10px;align-items:center;justify-content:flex-start;">
            <button type="submit" name="save_config" class="reset-btn" style="display:inline-flex;">
              <i class="fas fa-save"></i> Save Configuration
            </button>
            <a href="get_config.php" target="_blank" style="text-decoration:none;font-size:14px;color:#1976d2;">
              View raw <code>get_config.php</code> output
            </a>
            <span style="font-size:13px;color:#666;">ESP32 polls every 5s; applies only when version changes.</span>
          </div>
        </form>
      </div>

      <div class="analytics-card" style="margin-top:18px;">
        <h4 style="margin-bottom:10px;"><i class="fas fa-info-circle"></i> Hints</h4>
        <ul style="padding-left:18px;color:#555;">
          <li>Single-bottle rejection uses <b>weight ≥ threshold</b> (grams).</li>
          <li>Reward windows: A = <b>a_min–a_max</b>, B = <b>b_min–b_max</b>. If total exceeds <b>b_max</b>, device shows <b>Max Bottles</b> and locks until dispense.</li>
          <li>Water threshold compares <code>ADC ≥ value</code> to flag <b>WET</b>.</li>
        </ul>
      </div>
    </div>

    <!-- (Optional) other pages -->
    <div class="page-content" id="transactions-page"></div>
    <div class="page-content" id="rewards-page"></div>
  </main>
</div>

<script>
// Page nav
document.querySelectorAll('.nav-item[data-page]').forEach(item=>{
  item.addEventListener('click',e=>{
    e.preventDefault();
    const page=item.getAttribute('data-page');
    document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
    item.classList.add('active');
    document.querySelectorAll('.page-content').forEach(p=>p.classList.remove('active'));
    document.getElementById(`${page}-page`).classList.add('active');
    document.querySelector('.header h2').textContent=page.charAt(0).toUpperCase()+page.slice(1)+' Management';
  });
});

// Notification dropdown
const notifBtn=document.getElementById('notifBtn');
const notifDropdown=document.getElementById('notifDropdown');
const notifBadge=document.getElementById('notifBadge');
const notifList=document.getElementById('notifList');
notifBtn.addEventListener('click',()=>notifDropdown.classList.toggle('active'));
document.addEventListener('click',e=>{
  if(!e.target.closest('.notification-container')) notifDropdown.classList.remove('active');
});

// ---------- AUDIO ENGINE (persistent + loud) ----------
let AUDIO_CTX = null, MASTER = null, COMP = null;
let audioUnlocked = false;

function initAudioEngine(){
  if (AUDIO_CTX) return;
  AUDIO_CTX = new (window.AudioContext || window.webkitAudioContext)();
  // Limiter-style compressor
  COMP = AUDIO_CTX.createDynamicsCompressor();
  COMP.threshold.setValueAtTime(-10, AUDIO_CTX.currentTime);
  COMP.knee.setValueAtTime(0, AUDIO_CTX.currentTime);
  COMP.ratio.setValueAtTime(20, AUDIO_CTX.currentTime);
  COMP.attack.setValueAtTime(0.003, AUDIO_CTX.currentTime);
  COMP.release.setValueAtTime(0.15, AUDIO_CTX.currentTime);

  MASTER = AUDIO_CTX.createGain();
  MASTER.gain.setValueAtTime(0.9, AUDIO_CTX.currentTime);

  COMP.connect(MASTER).connect(AUDIO_CTX.destination);
}

async function unlockAudio(){
  try{
    initAudioEngine();
    if (AUDIO_CTX.state === 'suspended') await AUDIO_CTX.resume();
    audioUnlocked = true;
  }catch(e){}
}

// Unlock on first interaction
['pointerdown','touchstart','click','keydown'].forEach(evt=>{
  window.addEventListener(evt, unlockAudio, { once: true, passive: true });
});

// ---------- Super loud "POP + DING" ----------
function playBeep(){
  try{
    initAudioEngine();
    if (!audioUnlocked && AUDIO_CTX.state === 'suspended') {
      AUDIO_CTX.resume().then(()=>{ audioUnlocked = true; });
    }
    const now = AUDIO_CTX.currentTime;

    // POP
    const popOsc = AUDIO_CTX.createOscillator();
    const popGain = AUDIO_CTX.createGain();
    popOsc.type = 'triangle';
    popOsc.frequency.setValueAtTime(220, now);
    popGain.gain.setValueAtTime(0.0001, now);
    popGain.gain.exponentialRampToValueAtTime(0.45, now + 0.015);
    popGain.gain.exponentialRampToValueAtTime(0.0001, now + 0.09);
    popOsc.connect(popGain).connect(COMP);
    popOsc.start(now);
    popOsc.stop(now + 0.1);

    // Noise burst
    const noiseDur = 0.06;
    const noiseBuf = AUDIO_CTX.createBuffer(1, AUDIO_CTX.sampleRate * noiseDur, AUDIO_CTX.sampleRate);
    const data = noiseBuf.getChannelData(0);
    for (let i=0;i<data.length;i++){ data[i] = (Math.random()*2 - 1) * 0.7; }
    const noise = AUDIO_CTX.createBufferSource();
    noise.buffer = noiseBuf;
    const noiseGain = AUDIO_CTX.createGain();
    noiseGain.gain.setValueAtTime(0.001, now);
    noiseGain.gain.exponentialRampToValueAtTime(0.6, now + 0.01);
    noiseGain.gain.exponentialRampToValueAtTime(0.001, now + noiseDur);
    noise.connect(noiseGain).connect(COMP);
    noise.start(now);
    noise.stop(now + noiseDur);

    // DING
    const freqs = [1046.5, 1318.5, 2093.0]; // C6, E6, C7
    freqs.forEach((f, idx)=>{
      const osc = AUDIO_CTX.createOscillator();
      const g   = AUDIO_CTX.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(f, now + 0.09);
      osc.frequency.exponentialRampToValueAtTime(f*0.9, now + 0.20 + idx*0.02);
      g.gain.setValueAtTime(0.0001, now + 0.09);
      g.gain.exponentialRampToValueAtTime(0.6/(idx+1), now + 0.12);
      g.gain.exponentialRampToValueAtTime(0.0001, now + 0.7 + idx*0.05);
      osc.connect(g).connect(COMP);
      osc.start(now + 0.09);
      osc.stop(now + 0.8 + idx*0.05);
    });
  }catch(e){ console.warn('Audio error', e); }
}

// Toast popup
function showToast(msg){
  const t=document.createElement('div');
  t.className='toast';
  t.innerHTML=`<i class="fas fa-bell"></i><div>${String(msg).replace(/</g,"&lt;")}</div>`;
  document.body.appendChild(t);
  playBeep();

  setTimeout(()=>{
    t.style.transition='opacity .45s ease, transform .45s ease';
    t.style.opacity='0';
    t.style.transform='translateY(12px)';
    setTimeout(()=>t.remove(), 480);
  }, 10000);
}

// Track last IDs we've seen
let lastNotifId = (()=>{
  const first = document.querySelector('#notifList .notification-item');
  return first ? parseInt(first.getAttribute('data-id')||'0',10) : 0;
})();
let lastWaterId = 0; // from water_status_logs
let lastTankMeasuredAt = <?php echo $tank_time ? json_encode($tank_time) : 'null'; ?>;

// Poll status.php every 5s
async function fetchStatus(){
  try{
    const r = await fetch('status.php',{cache:'no-store'});
    if(!r.ok) return;
    const data = await r.json();

    // Today's collection
    const todayEl=document.getElementById('todayCollection');
    if (todayEl) todayEl.textContent = (data.total_today ?? 0) + ' g';

    // Tank Level
    if (data.tank){
      const statusText = data.tank.label ?? 'N/A';
      document.getElementById('tankStatus').textContent = statusText;
      const detail = 'Tank Level' + (data.tank.inches!==null ? ` — (${data.tank.inches} in)` : '');
      document.getElementById('tankDetail').textContent = detail;

      const upd = document.getElementById('tankUpdated');
      if (data.tank.measured_at){ upd.style.display=''; upd.textContent='Updated: '+data.tank.measured_at; }

      const alertEl=document.getElementById('tankAlert');
      if (statusText==='FULL'){ alertEl.style.display=''; } else { alertEl.style.display='none'; }

      if (lastTankMeasuredAt !== data.tank.measured_at){
        lastTankMeasuredAt = data.tank.measured_at;
        showToast(`Tank Level: ${statusText}${data.tank.inches!==null? ` • ${data.tank.inches} in` : ''}`);
      }
    }

    // Reward A & B (from reward_level via status.php)
    if (data.rewards){
      const A=data.rewards.A, B=data.rewards.B;
      const aEl=document.getElementById('rewardAValue');
      const bEl=document.getElementById('rewardBValue');
      if (aEl) aEl.textContent = (A.level!==null && A.level!==undefined) ? `${A.level}%` : 'N/A';
      if (bEl) bEl.textContent = (B.level!==null && B.level!==undefined) ? `${B.level}%` : 'N/A';

      const aIcon=document.getElementById('rewardAIcon');
      const bIcon=document.getElementById('rewardBIcon');
      if (A.level!==null && A.level<25){ aIcon.style.boxShadow='0 0 0 3px rgba(211,47,47,.2) inset'; } else { aIcon.style.boxShadow='none'; }
      if (B.level!==null && B.level<25){ bIcon.style.boxShadow='0 0 0 3px rgba(211,47,47,.2) inset'; } else { bIcon.style.boxShadow='none'; }
    }

    // Recent transactions (latest 10)
    if (Array.isArray(data.recent_tx)){
      const tbody=document.getElementById('recentTxBody');
      if (tbody){
        tbody.innerHTML = data.recent_tx.map(tx=>`
          <tr>
            <td>${tx.transaction_id}</td>
            <td>${tx.bin_id}</td>
            <td>${tx.total_grams} g</td>
            <td>${tx.accepted_count}</td>
            <td>${tx.reward_label} (${tx.reward_type})</td>
            <td>${tx.created_at}</td>
          </tr>`).join('');
      }
    }

    // Notifications from server
    if (data.notifications){
      const latestServerId = data.notifications.latest_id || 0;
      if (latestServerId > lastNotifId){
        const items = data.notifications.items || [];
        for (const rec of items.slice().reverse()){
          if ((rec.id||0) > lastNotifId){
            const el=document.createElement('div');
            el.className = 'notification-item unread';
            el.setAttribute('data-id', rec.id);
            el.innerHTML = `
              <div class="notification-icon"><i class="fas fa-bell"></i></div>
              <div class="notification-content">
                <p>${rec.message.replace(/</g,"&lt;")}</p>
                <div class="notification-time">${rec.created_at}</div>
              </div>`;
            notifList.prepend(el);
            showToast(rec.message);
          }
        }
        lastNotifId = latestServerId;
      }
      const cnt = data.notifications.unread_count || 0;
      if (cnt > 0){
        notifBadge.style.display='';
        notifBadge.textContent = String(cnt);
      } else {
        notifBadge.style.display='none';
        notifBadge.textContent = '0';
      }
    }

    // Water logs (latest 5) — show toast for brand new entries
    if (data.water_logs){
      const latestWater = data.water_logs.latest_id || 0;
      if (lastWaterId === 0) lastWaterId = latestWater;
      if (latestWater > lastWaterId){
        const newOnes = (data.water_logs.items || []).filter(w => (w.id||0) > lastWaterId);
        newOnes.sort((a,b)=> (a.id||0)-(b.id||0));
        for (const w of newOnes){
          const msg = `Water sensor: ${w.status} on ${w.bin_id}`;
          showToast(msg);
          const el=document.createElement('div');
          el.className='notification-item unread';
          el.setAttribute('data-id', w.id*100000);
          el.innerHTML = `
            <div class="notification-icon"><i class="fas fa-bell"></i></div>
            <div class="notification-content">
              <p>${msg.replace(/</g,"&lt;")}</p>
              <div class="notification-time">${w.created_at}</div>
            </div>`;
          notifList.prepend(el);
        }
        lastWaterId = latestWater;
        const cur=parseInt(notifBadge.textContent||'0',10);
        notifBadge.style.display='';
        notifBadge.textContent = String(cur + newOnes.length);
      }
    }

  }catch(e){ console.error(e); }
}
setInterval(fetchStatus, 5000);
fetchStatus();

// Mark all as read
document.querySelector('.mark-all-read')?.addEventListener('click', async (e)=>{
  e.preventDefault();
  try{
    const r = await fetch('notifications_mark_all.php', {method:'POST'});
    if (r.ok){
      document.querySelectorAll('#notifList .notification-item').forEach(n=>n.classList.remove('unread'));
      notifBadge.style.display='none'; notifBadge.textContent='0';
      showToast('All notifications marked as read');
    }
  }catch(err){}
});

// Auto-open Settings after save (server sets POST flag)
const openSettingsAfterSave = <?php echo isset($_POST['save_config']) ? 'true' : 'false'; ?>;
if (openSettingsAfterSave) {
  const settingsLink = document.querySelector('.nav-item[data-page="settings"]');
  if (settingsLink) settingsLink.click();
}
</script>
</body>
</html>