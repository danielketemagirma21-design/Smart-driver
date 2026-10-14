<?php
session_start();

// ✅ FIXED: Correct path to database.php
include '../config/database.php';

// ✅ Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin_login.php');
    exit();
}

$page_title = 'Admin Dashboard - Smart Driver';
$base_url = '../';
include '../includes/header.php';

// ✅ GET STATISTICS
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users"))['count'] ?? 0;
$active_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status = 'active'"))['count'] ?? 0;
$blocked_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status = 'blocked'"))['count'] ?? 0;
$total_violations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations"))['count'] ?? 0;
$pending_violations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations WHERE status = 'pending'"))['count'] ?? 0;
$today_violations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations WHERE DATE(created_at) = CURDATE()"))['count'] ?? 0;
$total_fines = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(fine_amount) as total FROM violations WHERE paid_status = 'paid'"))['total'] ?? 0;
$total_reports = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM road_reports"))['count'] ?? 0;
$pending_reports = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM road_reports WHERE status = 'pending'"))['count'] ?? 0;

// ✅ Top violations
$top_violations = mysqli_query($conn, "
    SELECT r.rule_name, COUNT(v.id) as count 
    FROM violations v 
    JOIN traffic_rules r ON v.rule_id = r.id 
    GROUP BY v.rule_id 
    ORDER BY count DESC 
    LIMIT 5
");

// ✅ Top drivers
$top_drivers = mysqli_query($conn, "
    SELECT full_name, driver_score, email 
    FROM users 
    ORDER BY driver_score DESC 
    LIMIT 5
");

// ✅ Recent activity
$recent_activity = mysqli_query($conn, "
    SELECT * FROM activity_logs 
    ORDER BY created_at DESC 
    LIMIT 10
");

// ✅ Weekly violations data
$weekly_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations WHERE DATE(created_at) = '$date'"))['count'] ?? 0;
    $weekly_data[] = $count;
}
?>

<style>
.sidebar {
    width: 260px;
    background: linear-gradient(180deg, #1a237e, #0d47a1);
    color: white;
    padding: 30px 20px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    flex-shrink: 0;
}
.sidebar h2 {
    font-size: 22px;
    margin-bottom: 30px;
    border-bottom: 2px solid rgba(255,255,255,0.1);
    padding-bottom: 15px;
}
.sidebar h2 i {
    color: #ffd54f;
}
.sidebar ul {
    list-style: none;
    padding: 0;
}
.sidebar ul li {
    margin-bottom: 5px;
}
.sidebar ul li a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s;
    font-size: 14px;
}
.sidebar ul li a:hover,
.sidebar ul li a.active {
    background: rgba(255,255,255,0.15);
    color: white;
}
.sidebar ul li a i {
    width: 20px;
    text-align: center;
}
.admin-container {
    display: flex;
    min-height: 100vh;
}
.main-content {
    flex: 1;
    padding: 30px;
    background: #f4f6f9;
}
.main-content header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}
.main-content header h1 {
    font-size: 28px;
    color: #1a237e;
}
.admin-info {
    background: white;
    padding: 10px 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border-left: 5px solid #1a237e;
    transition: transform 0.3s;
}
.stat-card:hover {
    transform: translateY(-5px);
}
.stat-card .stat-icon {
    font-size: 40px;
    margin-bottom: 10px;
}
.stat-card h3 {
    font-size: 30px;
    font-weight: 700;
    color: #1a237e;
}
.stat-card p {
    color: #666;
    font-size: 14px;
}
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 25px;
    margin: 30px 0;
}
.chart-container {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.chart-container h3 {
    margin-bottom: 20px;
    color: #333;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin: 30px 0;
}
.info-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.info-card h3 {
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 10px;
}
.info-card ul {
    list-style: none;
    padding: 0;
}
.info-card ul li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
}
.info-card ul li .badge {
    background: #1a237e;
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 12px;
}
.activity-log {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.activity-log h3 {
    margin-bottom: 20px;
    color: #333;
}
.activity-log table {
    width: 100%;
    border-collapse: collapse;
}
.activity-log table th {
    background: #f5f5f5;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
}
.activity-log table td {
    padding: 10px 15px;
    border-bottom: 1px solid #f0f0f0;
}
</style>

<div class="admin-container">
    <!-- ✅ WORKING SIDEBAR -->
    <div class="sidebar">
        <h2><i class="fas fa-car"></i> Smart Driver</h2>
        <ul>
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="add_user.php"><i class="fas fa-user-plus"></i> Add User</a></li>
            <li><a href="violations.php"><i class="fas fa-gavel"></i> Violations</a></li>
            <li><a href="add_violation.php"><i class="fas fa-plus"></i> Add Violation</a></li>
            <li><a href="fuel.php"><i class="fas fa-gas-pump"></i> Fuel</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="activity.php"><i class="fas fa-clock"></i> Activity</a></li>
            <li><a href="license_verification.php"><i class="fas fa-id-card"></i> License Verify</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
            <div class="admin-info">
                <i class="fas fa-user-shield"></i> <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?> 
                (<?php echo $_SESSION['admin_role'] ?? 'admin'; ?>)
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👤</div>
                <h3><?php echo $total_users; ?></h3>
                <p>Total Users</p>
                <small style="color: #666;">Active: <?php echo $active_users; ?> | Blocked: <?php echo $blocked_users; ?></small>
            </div>
            <div class="stat-card" style="border-color: #c62828;">
                <div class="stat-icon">🚦</div>
                <h3><?php echo $total_violations; ?></h3>
                <p>Total Violations</p>
                <small style="color: #666;">Today: <?php echo $today_violations; ?> | Pending: <?php echo $pending_violations; ?></small>
            </div>
            <div class="stat-card" style="border-color: #2e7d32;">
                <div class="stat-icon">💰</div>
                <h3><?php echo number_format($total_fines ?? 0, 2); ?> ETB</h3>
                <p>Total Fines Collected</p>
            </div>
            <div class="stat-card" style="border-color: #f9a825;">
                <div class="stat-icon">📋</div>
                <h3><?php echo $total_reports; ?></h3>
                <p>Road Reports</p>
                <small style="color: #666;">Pending: <?php echo $pending_reports; ?></small>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-container">
                <h3><i class="fas fa-chart-bar"></i> Weekly Violations</h3>
                <canvas id="weeklyViolationsChart"></canvas>
            </div>
            <div class="chart-container">
                <h3><i class="fas fa-chart-pie"></i> Top Violation Types</h3>
                <canvas id="violationTypesChart"></canvas>
            </div>
        </div>

        <!-- Top Lists -->
        <div class="info-grid">
            <div class="info-card">
                <h3><i class="fas fa-gavel"></i> Most Common Violations</h3>
                <ul>
                    <?php while ($row = mysqli_fetch_assoc($top_violations)): ?>
                        <li>
                            <span><?php echo $row['rule_name']; ?></span>
                            <span class="badge" style="background: #c62828;"><?php echo $row['count']; ?></span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
            <div class="info-card">
                <h3><i class="fas fa-trophy"></i> Top Drivers</h3>
                <ul>
                    <?php while ($row = mysqli_fetch_assoc($top_drivers)): ?>
                        <li>
                            <span><?php echo $row['full_name']; ?></span>
                            <span class="badge" style="background: #2e7d32;"><?php echo $row['driver_score']; ?></span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="activity-log">
            <h3><i class="fas fa-clock"></i> Recent Activity</h3>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Action</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($recent_activity)): ?>
                        <tr>
                            <td><?php echo date('h:i A', strtotime($row['created_at'])); ?></td>
                            <td><?php echo $row['action']; ?></td>
                            <td><?php echo $row['description']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const weeklyCtx = document.getElementById('weeklyViolationsChart').getContext('2d');
new Chart(weeklyCtx, {
    type: 'bar',
    data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
            label: 'Violations',
            data: <?php echo json_encode($weekly_data); ?>,
            backgroundColor: 'rgba(26, 35, 126, 0.6)',
            borderColor: 'rgba(26, 35, 126, 1)',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

const typeCtx = document.getElementById('violationTypesChart').getContext('2d');
new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: <?php 
            $labels = [];
            $values = [];
            $query = mysqli_query($conn, "
                SELECT r.rule_name, COUNT(v.id) as count 
                FROM violations v 
                JOIN traffic_rules r ON v.rule_id = r.id 
                GROUP BY v.rule_id 
                ORDER BY count DESC 
                LIMIT 5
            ");
            while ($row = mysqli_fetch_assoc($query)) {
                $labels[] = $row['rule_name'];
                $values[] = $row['count'];
            }
            echo json_encode($labels);
        ?>,
        datasets: [{
            data: <?php echo json_encode($values); ?>,
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>