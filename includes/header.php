<?php
// ✅ Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);
$is_admin = isset($_SESSION['admin_id']);
$is_driver = isset($_SESSION['user_id']);
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';

// ✅ Determine base URL for correct linking
$root_path = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $root_path = '../';
} elseif (strpos($_SERVER['PHP_SELF'], '/driver/') !== false) {
    $root_path = '../';
} else {
    $root_path = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Smart Driver'; ?></title>
    <link rel="stylesheet" href="<?php echo $root_path; ?>css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="<?php echo $root_path; ?>index.php" style="text-decoration: none; color: white; font-size: 24px; font-weight: 700;">
                    <i class="fas fa-car" style="color: #ffd54f;"></i> Smart Driver
                </a>
            </div>
            
            <ul class="nav-menu" style="display: flex; list-style: none; gap: 5px; align-items: center; flex-wrap: wrap;">
                <?php if ($is_logged_in): ?>
                    <?php if ($is_admin): ?>
                        <!-- ✅ ADMIN NAVIGATION -->
                        <li><a href="<?php echo $root_path; ?>admin/dashboard.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>admin/users.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-users"></i> Users
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>admin/violations.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-gavel"></i> Violations
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>admin/reports.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>admin/license_verification.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-id-card"></i> License Verify
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>logout.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a></li>
                    <?php elseif ($is_driver): ?>
                        <!-- ✅ DRIVER NAVIGATION -->
                        <li><a href="<?php echo $root_path; ?>dashboard.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>driver/profile.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-user"></i> Profile
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>driver/violations.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-gavel"></i> Violations
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>driver/fuel_records.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-gas-pump"></i> Fuel
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>driver/report_road.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-road"></i> Report Road
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>driver/notifications.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-bell"></i> Notifications
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>driver/pay_penalty.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-money-bill"></i> Pay Penalties
                        </a></li>
                        <li><a href="<?php echo $root_path; ?>logout.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a></li>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- ✅ PUBLIC NAVIGATION -->
                    <li><a href="<?php echo $root_path; ?>index.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-home"></i> Home
                    </a></li>
                    <li><a href="<?php echo $root_path; ?>login.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-sign-in-alt"></i> Driver Login
                    </a></li>
                    <li><a href="<?php echo $root_path; ?>admin_login.php" style="color: white; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 500; transition: background 0.3s; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-user-shield"></i> Admin
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <main>