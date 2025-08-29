<?php
session_start();
if (!isset($_SESSION['admin'])) { header("Location: index.php"); exit; }
include 'db.php';

// Fetch data for dashboard
$total_today = $conn->query("SELECT SUM(weight) as total FROM transactions WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['total'];
$logs = $conn->query("SELECT * FROM transactions ORDER BY created_at DESC LIMIT 10");
$rewards = $conn->query("SELECT * FROM rewards")->fetch_all(MYSQLI_ASSOC);
$bin = $conn->query("SELECT is_full FROM bin_status ORDER BY id DESC LIMIT 1")->fetch_assoc();

// Fetch notifications
$notifications = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$unread_count = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Analytics data (last 7 days)
$analytics_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $result = $conn->query("SELECT SUM(weight) as total FROM transactions WHERE DATE(created_at)='$date'");
    $total = $result->fetch_assoc()['total'];
    $analytics_data[$date] = $total ? $total : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTU Mystery Bin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2e7d32;
            --primary-light: #4caf50;
            --primary-dark: #1b5e20;
            --secondary: #f57c00;
            --danger: #d32f2f;
            --warning: #fbc02d;
            --success: #388e3c;
            --info: #1976d2;
            --light: #f5f5f5;
            --dark: #333;
            --gray: #e0e0e0;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: var(--dark);
            line-height: 1.6;
        }

        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: var(--primary);
            color: white;
            padding: 20px 0;
            position: fixed;
            width: 250px;
            height: 100vh;
            overflow-y: auto;
        }

        .brand {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .brand h1 {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand i {
            font-size: 1.8rem;
        }

        .nav-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        /* Main Content */
        .main-content {
            grid-column: 2;
            padding: 20px 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--gray);
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification-container {
            position: relative;
        }

        .notification-btn {
            background: var(--primary-light);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
            transition: var(--transition);
        }

        .notification-btn:hover {
            background: var(--primary-dark);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-dropdown {
            position: absolute;
            top: 50px;
            right: 0;
            width: 350px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            z-index: 100;
            display: none;
        }

        .notification-dropdown.active {
            display: block;
        }

        .notification-header {
            padding: 15px;
            border-bottom: 1px solid var(--gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid var(--gray);
            display: flex;
            gap: 10px;
            cursor: pointer;
            transition: var(--transition);
        }

        .notification-item:hover {
            background: #f9f9f9;
        }

        .notification-item.unread {
            background: #f0f7ff;
        }

        .notification-icon {
            color: var(--info);
            font-size: 1.2rem;
        }

        .notification-content {
            flex: 1;
        }

        .notification-time {
            font-size: 0.8rem;
            color: #777;
        }

        .notification-footer {
            padding: 10px;
            text-align: center;
            border-top: 1px solid var(--gray);
        }

        .logout-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: var(--primary-dark);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .collection-icon {
            background: rgba(46, 125, 50, 0.1);
            color: var(--primary);
        }

        .bin-icon {
            background: rgba(211, 47, 47, 0.1);
            color: var(--danger);
        }

        .bin-icon.ok {
            background: rgba(56, 142, 60, 0.1);
            color: var(--success);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-title {
            color: #666;
            font-size: 0.9rem;
        }

        .alert-badge {
            background: var(--danger);
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: 10px;
            display: inline-block;
        }

        /* Analytics Section */
        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .chart-container {
            height: 300px;
            margin-top: 20px;
            position: relative;
        }

        .chart-bar {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            height: 100%;
            gap: 10px;
            padding: 0 10px;
        }

        .chart-column {
            flex: 1;
            background: var(--primary-light);
            border-radius: 6px 6px 0 0;
            position: relative;
            transition: var(--transition);
            cursor: pointer;
        }

        .chart-column:hover {
            background: var(--primary);
        }

        .chart-label {
            position: absolute;
            bottom: -25px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 0.8rem;
            color: #666;
        }

        .chart-value {
            position: absolute;
            top: -25px;
            left: 0;
            right: 0;
            text-align: center;
            font-weight: bold;
        }

        /* Rewards Section */
        .section-title {
            margin: 30px 0 20px;
            font-size: 1.4rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .reward-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: var(--transition);
        }

        .reward-card:hover {
            transform: translateY(-5px);
        }

        .reward-name {
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }

        .progress-container {
            height: 10px;
            background: var(--gray);
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .progress-bar {
            height: 100%;
            border-radius: 5px;
        }

        .progress-green { background: var(--success); }
        .progress-yellow { background: var(--warning); }
        .progress-orange { background: var(--secondary); }
        .progress-red { background: var(--danger); }

        .reward-percentage {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        /* Transactions Table */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray);
        }

        th {
            background: #f7f7f7;
            font-weight: 600;
            color: #555;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: #f9f9f9;
        }

        /* Reset Button */
        .reset-section {
            text-align: center;
            margin: 30px 0;
        }

        .reset-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .reset-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Page Content */
        .page-content {
            display: none;
        }

        .page-content.active {
            display: block;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .main-content {
                grid-column: 1;
            }
            
            .stats-grid, .rewards-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }

            .notification-dropdown {
                width: 300px;
                right: -50px;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }
            
            .stats-grid, .rewards-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .notification-dropdown {
                width: 280px;
                right: -80px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <h1><i class="fas fa-trash-alt"></i> Mystery Bin</h1>
            </div>
            <a href="#" class="nav-item active" data-page="dashboard"><i class="fas fa-home"></i> Dashboard</a>
            <a href="#" class="nav-item" data-page="transactions"><i class="fas fa-history"></i> Transactions</a>
            <a href="#" class="nav-item" data-page="rewards"><i class="fas fa-gift"></i> Rewards</a>
            <a href="#" class="nav-item" data-page="settings"><i class="fas fa-cog"></i> Settings</a>
            <a href="index.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h2>Dashboard Overview</h2>
                <div class="user-actions">
                    <span>Welcome, Admin</span>
                    <div class="notification-container">
                        <button class="notification-btn">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="notification-dropdown">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                                <a href="#" class="mark-all-read">Mark all as read</a>
                            </div>
                            <div class="notification-list">
                                <?php if (count($notifications) > 0): ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notification['id']; ?>">
                                            <div class="notification-icon">
                                                <i class="fas fa-bell"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?php echo $notification['message']; ?></p>
                                                <div class="notification-time"><?php echo $notification['created_at']; ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="notification-item">
                                        <div class="notification-content">
                                            <p>No notifications</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="notification-footer">
                                <a href="#" class="view-all">View All Notifications</a>
                            </div>
                        </div>
                    </div>
                    <a href="index.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <!-- Dashboard Page -->
            <div class="page-content active" id="dashboard-page">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo $total_today ? $total_today : 0; ?> g</div>
                                <div class="stat-title">Today's Collection</div>
                            </div>
                            <div class="stat-icon collection-icon">
                                <i class="fas fa-weight-hanging"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo $bin['is_full'] ? 'FULL' : 'OK'; ?></div>
                                <div class="stat-title">Bin Status</div>
                                <?php if ($bin['is_full']): ?>
                                    <div class="alert-badge">Needs Attention</div>
                                <?php endif; ?>
                            </div>
                            <div class="stat-icon bin-icon <?php echo $bin['is_full'] ? '' : 'ok'; ?>">
                                <i class="fas fa-trash-alt"></i>
                            </div>
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
                            foreach ($analytics_data as $date => $value):
                                $height = $max_value > 0 ? ($value / $max_value) * 100 : 0;
                                $day = date('D', strtotime($date));
                            ?>
                                <div class="chart-column" style="height: <?php echo $height; ?>%" title="<?php echo $date; ?>: <?php echo $value; ?>g">
                                    <div class="chart-value"><?php echo $value; ?>g</div>
                                    <div class="chart-label"><?php echo $day; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Rewards Section -->
                <h3 class="section-title"><i class="fas fa-gift"></i> Rewards Status</h3>
                <div class="rewards-grid">
                    <?php foreach ($rewards as $r): 
                        $color = "green";
                        $progressClass = "progress-green";
                        if ($r['level'] < 75 && $r['level'] >= 50) {
                            $color = "yellow";
                            $progressClass = "progress-yellow";
                        } elseif ($r['level'] < 50 && $r['level'] >= 25) {
                            $color = "orange";
                            $progressClass = "progress-orange";
                        } elseif ($r['level'] < 25) {
                            $color = "red";
                            $progressClass = "progress-red";
                        }
                    ?>
                        <div class="reward-card">
                            <div class="reward-name"><?php echo $r['reward_name']; ?></div>
                            <div class="progress-container">
                                <div class="progress-bar <?php echo $progressClass; ?>" style="width: <?php echo $r['level']; ?>%"></div>
                            </div>
                            <div class="reward-percentage"><?php echo $r['level']; ?>%</div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Transactions Table -->
                <h3 class="section-title"><i class="fas fa-history"></i> Recent Transactions</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Weight</th>
                                <th>Reward</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $log['transaction_id']; ?></td>
                                    <td><?php echo $log['weight']; ?> g</td>
                                    <td><?php echo $log['reward_dispensed']; ?></td>
                                    <td><?php echo $log['created_at']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Reset Button -->
                <div class="reset-section">
                    <form action="reset.php" method="post">
                        <button type="submit" name="reset" class="reset-btn">
                            <i class="fas fa-sync-alt"></i> Reset Bin Status
                        </button>
                    </form>
                </div>
            </div>

            <!-- Transactions Page -->
            <div class="page-content" id="transactions-page">
                <h2 class="section-title"><i class="fas fa-history"></i> All Transactions</h2>
                
                <div class="filters" style="margin-bottom: 20px; display: flex; gap: 15px;">
                    <input type="date" id="date-filter" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                    <select id="reward-filter" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                        <option value="">All Rewards</option>
                        <?php foreach ($rewards as $r): ?>
                            <option value="<?php echo $r['reward_name']; ?>"><?php echo $r['reward_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button id="filter-btn" style="background: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Filter</button>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Weight (g)</th>
                                <th>Reward</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $all_logs = $conn->query("SELECT * FROM transactions ORDER BY created_at DESC");
                            while ($log = $all_logs->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $log['transaction_id']; ?></td>
                                    <td><?php echo $log['weight']; ?></td>
                                    <td><?php echo $log['reward_dispensed']; ?></td>
                                    <td><?php echo $log['created_at']; ?></td>
                                    <td>
                                        <button class="view-btn" style="background: var(--info); color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">View</button>
                                        <button class="delete-btn" style="background: var(--danger); color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Delete</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Rewards Page -->
            <div class="page-content" id="rewards-page">
                <h2 class="section-title"><i class="fas fa-gift"></i> Manage Rewards</h2>
                
                <div class="rewards-controls" style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <button id="add-reward-btn" style="background: var(--success); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-plus"></i> Add New Reward
                    </button>
                    
                    <div style="display: flex; gap: 10px;">
                        <button style="background: var(--info); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Export Data</button>
                        <button style="background: var(--warning); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Generate Report</button>
                    </div>
                </div>
                
                <div class="rewards-grid">
                    <?php foreach ($rewards as $r): 
                        $color = "green";
                        $progressClass = "progress-green";
                        if ($r['level'] < 75 && $r['level'] >= 50) {
                            $color = "yellow";
                            $progressClass = "progress-yellow";
                        } elseif ($r['level'] < 50 && $r['level'] >= 25) {
                            $color = "orange";
                            $progressClass = "progress-orange";
                        } elseif ($r['level'] < 25) {
                            $color = "red";
                            $progressClass = "progress-red";
                        }
                    ?>
                        <div class="reward-card">
                            <div class="reward-name"><?php echo $r['reward_name']; ?></div>
                            <div class="progress-container">
                                <div class="progress-bar <?php echo $progressClass; ?>" style="width: <?php echo $r['level']; ?>%"></div>
                            </div>
                            <div class="reward-percentage"><?php echo $r['level']; ?>%</div>
                            <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: center;">
                                <button class="edit-btn" style="background: var(--info); color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Edit</button>
                                <button class="delete-btn" style="background: var(--danger); color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Settings Page -->
            <div class="page-content" id="settings-page">
                <h2 class="section-title"><i class="fas fa-cog"></i> System Settings</h2>
                
                <div class="settings-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <div class="setting-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow);">
                        <h3 style="margin-bottom: 15px;">Bin Configuration</h3>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <label>Bin Capacity (g)</label>
                            <input type="number" value="5000" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            
                            <label>Alert Threshold (%)</label>
                            <input type="number" value="85" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            
                            <button style="background: var(--primary); color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer; margin-top: 10px;">Save Changes</button>
                        </div>
                    </div>
                    
                    <div class="setting-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow);">
                        <h3 style="margin-bottom: 15px;">Notification Settings</h3>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <label>
                                <input type="checkbox" checked> Email Notifications
                            </label>
                            
                            <label>
                                <input type="checkbox" checked> Bin Full Alerts
                            </label>
                            
                            <label>
                                <input type="checkbox" checked> Reward Level Alerts
                            </label>
                            
                            <label>
                                <input type="checkbox"> Daily Reports
                            </label>
                            
                            <button style="background: var(--primary); color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer; margin-top: 10px;">Save Preferences</button>
                        </div>
                    </div>
                    
                    <div class="setting-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: var(--card-shadow);">
                        <h3 style="margin-bottom: 15px;">User Management</h3>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <label>Admin Name</label>
                            <input type="text" value="Administrator" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            
                            <label>Email Address</label>
                            <input type="email" value="admin@mysterybin.com" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            
                            <label>Password</label>
                            <input type="password" placeholder="Enter new password" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                            
                            <button style="background: var(--primary); color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer; margin-top: 10px;">Update Profile</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Navigation between pages
        document.querySelectorAll('.nav-item[data-page]').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.getAttribute('data-page');
                
                // Update active nav item
                document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
                
                // Show the selected page
                document.querySelectorAll('.page-content').forEach(content => content.classList.remove('active'));
                document.getElementById(`${page}-page`).classList.add('active');
                
                // Update header title
                document.querySelector('.header h2').textContent = 
                    page.charAt(0).toUpperCase() + page.slice(1) + ' Management';
            });
        });

        // Notification dropdown toggle
        const notificationBtn = document.querySelector('.notification-btn');
        const notificationDropdown = document.querySelector('.notification-dropdown');
        
        notificationBtn.addEventListener('click', function() {
            notificationDropdown.classList.toggle('active');
        });

        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.notification-container')) {
                notificationDropdown.classList.remove('active');
            }
        });

        // Mark notification as read
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.getAttribute('data-id');
                this.classList.remove('unread');
                
                // In a real application, you would send an AJAX request to mark as read
                console.log(`Marking notification ${notificationId} as read`);
            });
        });

        // Mark all notifications as read
        document.querySelector('.mark-all-read').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.remove('unread');
            });
            
            // In a real application, you would send an AJAX request
            console.log('Marking all notifications as read');
        });

        // Simple animations for page elements
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on page load
            const cards = document.querySelectorAll('.stat-card, .reward-card');
            cards.forEach((card, index) => {
                card.style.animation = `fadeInUp 0.5s ease ${index * 0.1}s forwards`;
                card.style.opacity = '0';
            });
            
            // Add CSS for animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>