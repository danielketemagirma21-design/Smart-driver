<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin_login.php');
    exit();
}

// Get activity logs
$logs = mysqli_query($conn, "
    SELECT l.*, a.full_name as admin_name 
    FROM activity_logs l 
    LEFT JOIN admins a ON l.admin_id = a.id 
    ORDER BY l.created_at DESC 
    LIMIT 100
");

$page_title = 'Activity Logs - Smart Driver Admin';
$base_url = '../';
include '../includes/header.php';
?>

<div class="admin-container">
    <div class="sidebar">
        <h2><i class="fas fa-car"></i> Smart Driver</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="violations.php"><i class="fas fa-gavel"></i> Violations</a></li>
            <li><a href="fuel.php"><i class="fas fa-gas-pump"></i> Fuel</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="activity.php" class="active"><i class="fas fa-clock"></i> Activity</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h1><i class="fas fa-clock"></i> Activity Logs</h1>
            <span class="badge badge-info">Last 100 entries</span>
        </header>

        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($log = mysqli_fetch_assoc($logs)): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo $log['admin_name'] ?? 'System'; ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo strpos($log['action'], 'login') !== false ? 'success' : 
                                            (strpos($log['action'], 'delete') !== false ? 'danger' : 
                                            (strpos($log['action'], 'block') !== false ? 'danger' : 
                                            (strpos($log['action'], 'approve') !== false ? 'success' : 'info'))); 
                                    ?>">
                                        <?php echo str_replace('_', ' ', $log['action']); ?>
                                    </span>
                                </td>
                                <td><?php echo $log['description']; ?></td>
                                <td><?php echo $log['ip_address'] ?? 'N/A'; ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($logs) == 0): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-check-circle" style="font-size: 48px; color: #2e7d32;"></i>
                                    <p>No activity logs found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>