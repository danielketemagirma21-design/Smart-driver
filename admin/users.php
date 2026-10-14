<?php
session_start();
include '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin_login.php');
    exit();
}

// Handle user actions (Block/Activate/Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'block') {
        mysqli_query($conn, "UPDATE users SET status = 'blocked' WHERE id = $user_id");
        mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                            VALUES (" . $_SESSION['admin_id'] . ", 'block_user', 'Blocked user ID: $user_id', '" . $_SERVER['REMOTE_ADDR'] . "')");
        header('Location: users.php');
        exit();
    } elseif ($action == 'activate') {
        mysqli_query($conn, "UPDATE users SET status = 'active' WHERE id = $user_id");
        mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                            VALUES (" . $_SESSION['admin_id'] . ", 'activate_user', 'Activated user ID: $user_id', '" . $_SERVER['REMOTE_ADDR'] . "')");
        header('Location: users.php');
        exit();
    } elseif ($action == 'delete') {
        mysqli_query($conn, "DELETE FROM users WHERE id = $user_id");
        mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                            VALUES (" . $_SESSION['admin_id'] . ", 'delete_user', 'Deleted user ID: $user_id', '" . $_SERVER['REMOTE_ADDR'] . "')");
        header('Location: users.php');
        exit();
    }
}

// Get all users
$users = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");

$page_title = 'User Management - Smart Driver Admin';
$base_url = '../';
include '../includes/header.php';
?>

<div class="admin-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <h2><i class="fas fa-car"></i> Smart Driver</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php" class="active"><i class="fas fa-users"></i> Users</a></li>
            <!-- ✅ ADD USER LINK IN SIDEBAR -->
            <li><a href="add_user.php"><i class="fas fa-user-plus"></i> Add User</a></li>
            <li><a href="violations.php"><i class="fas fa-gavel"></i> Violations</a></li>
            <li><a href="fuel.php"><i class="fas fa-gas-pump"></i> Fuel</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="activity.php"><i class="fas fa-clock"></i> Activity</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1><i class="fas fa-users"></i> User Management</h1>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <!-- ✅ ADD NEW USER BUTTON - BIG AND VISIBLE -->
                <a href="add_user.php" style="display: inline-block; padding: 12px 25px; background: #2e7d32; color: white; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 15px; transition: background 0.3s;">
                    <i class="fas fa-user-plus"></i> ➕ Add New User
                </a>
                <span style="background: #1a237e; color: white; padding: 8px 18px; border-radius: 20px; font-size: 14px;">
                    Total: <?php echo mysqli_num_rows($users); ?> users
                </span>
            </div>
        </header>

        <!-- Users Table -->
        <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #1a237e; color: white;">
                        <tr>
                            <th style="padding: 12px 15px; text-align: left;">#</th>
                            <th style="padding: 12px 15px; text-align: left;">Full Name</th>
                            <th style="padding: 12px 15px; text-align: left;">Email</th>
                            <th style="padding: 12px 15px; text-align: left;">Phone</th>
                            <th style="padding: 12px 15px; text-align: left;">License</th>
                            <th style="padding: 12px 15px; text-align: left;">Score</th>
                            <th style="padding: 12px 15px; text-align: left;">Status</th>
                            <th style="padding: 12px 15px; text-align: left;">Joined</th>
                            <th style="padding: 12px 15px; text-align: left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($user = mysqli_fetch_assoc($users)): ?>
                            <tr style="border-bottom: 1px solid #e0e0e0;">
                                <td style="padding: 12px 15px;"><?php echo $i++; ?></td>
                                <td style="padding: 12px 15px;"><strong><?php echo $user['full_name']; ?></strong></td>
                                <td style="padding: 12px 15px;"><?php echo $user['email']; ?></td>
                                <td style="padding: 12px 15px;"><?php echo $user['phone']; ?></td>
                                <td style="padding: 12px 15px;"><?php echo $user['driver_license']; ?></td>
                                <td style="padding: 12px 15px;">
                                    <span style="color: <?php echo $user['driver_score'] >= 80 ? '#2e7d32' : ($user['driver_score'] >= 50 ? '#f9a825' : '#c62828'); ?>; font-weight: bold;">
                                        <?php echo $user['driver_score']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 15px;">
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?php echo $user['status'] == 'active' ? '#e8f5e9' : '#ffebee'; ?>; color: <?php echo $user['status'] == 'active' ? '#2e7d32' : '#c62828'; ?>;">
                                        <?php echo $user['status']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 15px;"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td style="padding: 12px 15px;">
                                    <a href="user_detail.php?id=<?php echo $user['id']; ?>" style="padding: 5px 10px; background: #0d47a1; color: white; border-radius: 5px; text-decoration: none; font-size: 12px; display: inline-block; margin: 2px;">View</a>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <a href="users.php?action=block&id=<?php echo $user['id']; ?>" onclick="return confirm('Block this user?')" style="padding: 5px 10px; background: #c62828; color: white; border-radius: 5px; text-decoration: none; font-size: 12px; display: inline-block; margin: 2px;">Block</a>
                                    <?php elseif ($user['status'] == 'blocked'): ?>
                                        <a href="users.php?action=activate&id=<?php echo $user['id']; ?>" onclick="return confirm('Activate this user?')" style="padding: 5px 10px; background: #2e7d32; color: white; border-radius: 5px; text-decoration: none; font-size: 12px; display: inline-block; margin: 2px;">Activate</a>
                                    <?php endif; ?>
                                    <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" onclick="return confirm('Delete this user permanently?')" style="padding: 5px 10px; background: #c62828; color: white; border-radius: 5px; text-decoration: none; font-size: 12px; display: inline-block; margin: 2px;">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($users) == 0): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-users" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></i>
                                    <p style="color: #666;">No users found. Click <strong style="color: #2e7d32;">"Add New User"</strong> to create one.</p>
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