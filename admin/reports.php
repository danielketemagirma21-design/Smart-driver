<?php
session_start();
include '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin_login.php');
    exit();
}

// Handle report actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $report_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'approve') {
        mysqli_query($conn, "UPDATE road_reports SET status = 'approved', approved_by = " . $_SESSION['admin_id'] . " WHERE id = $report_id");
        mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                            VALUES (" . $_SESSION['admin_id'] . ", 'approve_report', 'Approved road report ID: $report_id', '" . $_SERVER['REMOTE_ADDR'] . "')");
        header('Location: reports.php');
        exit();
    } elseif ($action == 'reject') {
        mysqli_query($conn, "UPDATE road_reports SET status = 'rejected' WHERE id = $report_id");
        mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                            VALUES (" . $_SESSION['admin_id'] . ", 'reject_report', 'Rejected road report ID: $report_id', '" . $_SERVER['REMOTE_ADDR'] . "')");
        header('Location: reports.php');
        exit();
    }
}

// Get all reports
$reports = mysqli_query($conn, "
    SELECT r.*, u.full_name, u.email 
    FROM road_reports r 
    JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC
");

// Get statistics
$total = mysqli_num_rows($reports);
$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM road_reports WHERE status = 'pending'"))['count'] ?? 0;
$approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM road_reports WHERE status = 'approved'"))['count'] ?? 0;
$rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM road_reports WHERE status = 'rejected'"))['count'] ?? 0;

$page_title = 'Report Management - Smart Driver Admin';
$base_url = '../';
include '../includes/header.php';
?>

<div class="admin-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <h2><i class="fas fa-car"></i> Smart Driver</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="add_user.php"><i class="fas fa-user-plus"></i> Add User</a></li>
            <li><a href="violations.php"><i class="fas fa-gavel"></i> Violations</a></li>
            <li><a href="fuel.php"><i class="fas fa-gas-pump"></i> Fuel</a></li>
            <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="activity.php"><i class="fas fa-clock"></i> Activity</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1><i class="fas fa-chart-bar"></i> Road Reports</h1>
            <span style="background: #1a237e; color: white; padding: 8px 15px; border-radius: 20px;">
                Total: <?php echo $total; ?> reports
            </span>
        </header>

        <!-- Statistics -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #f9a825;">
                <div style="font-size: 28px; font-weight: bold; color: #f9a825;"><?php echo $pending; ?></div>
                <div style="color: #666; font-size: 14px;">⏳ Pending</div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #2e7d32;">
                <div style="font-size: 28px; font-weight: bold; color: #2e7d32;"><?php echo $approved; ?></div>
                <div style="color: #666; font-size: 14px;">✅ Approved</div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #c62828;">
                <div style="font-size: 28px; font-weight: bold; color: #c62828;"><?php echo $rejected; ?></div>
                <div style="color: #666; font-size: 14px;">❌ Rejected</div>
            </div>
        </div>

        <!-- Reports Table -->
        <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #1a237e; color: white;">
                        <tr>
                            <th style="padding: 12px; text-align: left;">#</th>
                            <th style="padding: 12px; text-align: left;">Driver</th>
                            <th style="padding: 12px; text-align: left;">Type</th>
                            <th style="padding: 12px; text-align: left;">Location</th>
                            <th style="padding: 12px; text-align: left;">Description</th>
                            <th style="padding: 12px; text-align: left;">Status</th>
                            <th style="padding: 12px; text-align: left;">Date</th>
                            <th style="padding: 12px; text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($reports) > 0): ?>
                            <?php $i = 1; while ($r = mysqli_fetch_assoc($reports)): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 12px;"><?php echo $i++; ?></td>
                                    <td style="padding: 12px;">
                                        <strong><?php echo $r['full_name']; ?></strong><br>
                                        <small style="color: #666;"><?php echo $r['email']; ?></small>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php 
                                            $icons = [
                                                'construction' => '🚧',
                                                'accident' => '🚨',
                                                'traffic_jam' => '🚗',
                                                'pothole' => '🕳️',
                                                'broken_traffic_light' => '🚦',
                                                'other' => '📌'
                                            ];
                                            echo ($icons[$r['report_type']] ?? '📌') . ' ' . str_replace('_', ' ', $r['report_type']);
                                        ?>
                                    </td>
                                    <td style="padding: 12px;"><?php echo $r['location']; ?></td>
                                    <td style="padding: 12px;">
                                        <?php echo $r['description'] ? substr($r['description'], 0, 50) . (strlen($r['description']) > 50 ? '...' : '') : 'N/A'; ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?php echo $r['status'] == 'approved' ? '#e8f5e9' : ($r['status'] == 'pending' ? '#fff3e0' : '#ffebee'); ?>; color: <?php echo $r['status'] == 'approved' ? '#2e7d32' : ($r['status'] == 'pending' ? '#e65100' : '#c62828'); ?>;">
                                            <?php echo ucfirst($r['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px;"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                                    <td style="padding: 12px;">
                                        <?php if ($r['status'] == 'pending'): ?>
                                            <a href="reports.php?action=approve&id=<?php echo $r['id']; ?>" 
                                               style="padding: 5px 12px; background: #2e7d32; color: white; border-radius: 5px; text-decoration: none; font-size: 12px; display: inline-block; margin: 2px;">
                                                ✅ Approve
                                            </a>
                                            <a href="reports.php?action=reject&id=<?php echo $r['id']; ?>" 
                                               style="padding: 5px 12px; background: #c62828; color: white; border-radius: 5px; text-decoration: none; font-size: 12px; display: inline-block; margin: 2px;">
                                                ❌ Reject
                                            </a>
                                        <?php else: ?>
                                            <span style="color: <?php echo $r['status'] == 'approved' ? '#2e7d32' : '#c62828'; ?>; font-weight: 600;">
                                                <?php echo $r['status'] == 'approved' ? '✅ Approved' : '❌ Rejected'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-road" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></i>
                                    <p style="color: #666; font-size: 16px;">No reports found</p>
                                    <p style="color: #999; font-size: 14px;">Drivers will submit road reports here.</p>
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