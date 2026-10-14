<?php
session_start();
include '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin_login.php');
    exit();
}

// Handle violation actions (Verify, Reject, Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $violation_id = (int)$_GET['id'];
    $action = $_GET['action'];
    $admin_id = $_SESSION['admin_id'];
    
    if ($action == 'verify') {
        // Get violation details
        $violation = mysqli_fetch_assoc(mysqli_query($conn, 
            "SELECT v.*, u.full_name, u.email, r.rule_name 
             FROM violations v 
             JOIN users u ON v.user_id = u.id 
             JOIN traffic_rules r ON v.rule_id = r.id 
             WHERE v.id = $violation_id"
        ));
        
        if ($violation) {
            $user_id = $violation['user_id'];
            $points_to_deduct = $violation['points_deducted'];
            $fine_amount = $violation['fine_amount'];
            
            // ✅ Update violation status to verified
            mysqli_query($conn, "UPDATE violations SET 
                                status = 'verified', 
                                verified_by = $admin_id, 
                                verified_date = NOW() 
                                WHERE id = $violation_id");
            
            // ✅ Update driver score
            mysqli_query($conn, "UPDATE users SET driver_score = driver_score - $points_to_deduct WHERE id = $user_id");
            
            // ✅ Log activity
            $reported_by = $violation['reported_by'] ?? 'unknown';
            mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                                VALUES ($admin_id, 'verify_violation', 
                                'Verified violation ID: $violation_id reported by: $reported_by - Driver: {$violation['full_name']} - Fine: $fine_amount ETB - Points: -$points_to_deduct', 
                                '" . $_SERVER['REMOTE_ADDR'] . "')");
            
            // ✅ Send notification to driver
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type) 
                                VALUES ($user_id, '⚠️ Violation Verified & Score Updated', 
                                'Your violation has been verified. Fine: " . number_format($fine_amount, 2) . " ETB. 
                                $points_to_deduct points deducted from your score.
                                Reported by: " . ucfirst($reported_by) . "
                                If you believe this is a mistake, please contact the admin.', 'violation')");
        }
        
        header('Location: violations.php');
        exit();
        
    } elseif ($action == 'reject') {
        // ✅ Get violation details before rejecting
        $violation = mysqli_fetch_assoc(mysqli_query($conn, 
            "SELECT v.*, u.full_name, u.email 
             FROM violations v 
             JOIN users u ON v.user_id = u.id 
             WHERE v.id = $violation_id"
        ));
        
        if ($violation) {
            // ✅ Update violation status to rejected
            mysqli_query($conn, "UPDATE violations SET 
                                status = 'rejected', 
                                rejected_by = $admin_id, 
                                rejected_date = NOW(), 
                                rejection_reason = 'Insufficient evidence' 
                                WHERE id = $violation_id");
            
            // ✅ Log activity with reason
            $reported_by = $violation['reported_by'] ?? 'unknown';
            mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                                VALUES ($admin_id, 'reject_violation', 
                                'Rejected violation ID: $violation_id reported by: $reported_by - Driver: {$violation['full_name']} - Reason: Insufficient evidence', 
                                '" . $_SERVER['REMOTE_ADDR'] . "')");
            
            // ✅ IF REPORTED BY POLICE - Mark as false accusation
            if ($reported_by == 'police') {
                // Log false accusation against police
                mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description) 
                                    VALUES ($admin_id, 'false_accusation', 
                                    '🚨 FALSE ACCUSATION DETECTED! Police officer reported a violation that was rejected by admin. Driver: {$violation['full_name']} - Violation ID: $violation_id')");
                
                // ✅ Send notification to driver about false accusation
                mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type) 
                                    VALUES ({$violation['user_id']}, '✅ False Accusation Cleared', 
                                    'The violation reported against you has been rejected by the admin.
                                    Your driver score has not been affected.
                                    If you have any questions, please contact the admin.', 'system')");
                
                // ✅ Send notification to admin about false accusation
                mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type) 
                                    VALUES (1, '🚨 False Accusation Alert', 
                                    'A police officer falsely accused driver: {$violation['full_name']}.
                                    Please review the police officer\'s reports.', 'system')");
            } else {
                // Send notification to driver for regular rejection
                mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type) 
                                    VALUES ({$violation['user_id']}, '✅ Violation Rejected', 
                                    'The violation reported against you has been rejected by the admin.
                                    Your driver score has not been affected.', 'system')");
            }
        }
        
        header('Location: violations.php');
        exit();
        
    } elseif ($action == 'delete') {
        mysqli_query($conn, "DELETE FROM violations WHERE id = $violation_id");
        mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                            VALUES ($admin_id, 'delete_violation', 'Deleted violation ID: $violation_id', '" . $_SERVER['REMOTE_ADDR'] . "')");
        header('Location: violations.php');
        exit();
    }
}

// ✅ GET ALL VIOLATIONS WITH USER AND RULE INFO
$violations = mysqli_query($conn, "
    SELECT v.*, 
           u.full_name, 
           u.email, 
           u.driver_score,
           r.rule_name,
           a1.full_name as verified_by_name,
           a2.full_name as rejected_by_name
    FROM violations v 
    JOIN users u ON v.user_id = u.id 
    JOIN traffic_rules r ON v.rule_id = r.id 
    LEFT JOIN admins a1 ON v.verified_by = a1.id
    LEFT JOIN admins a2 ON v.rejected_by = a2.id
    ORDER BY 
        FIELD(v.status, 'pending', 'verified', 'rejected'),
        v.created_at DESC
");

// ✅ GET STATISTICS
$total = mysqli_num_rows($violations);
$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations WHERE status = 'pending'"))['count'] ?? 0;
$verified = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations WHERE status = 'verified'"))['count'] ?? 0;
$rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations WHERE status = 'rejected'"))['count'] ?? 0;
$police_reported = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations WHERE reported_by = 'police'"))['count'] ?? 0;
$false_accusations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations WHERE reported_by = 'police' AND status = 'rejected'"))['count'] ?? 0;

$page_title = 'Violation Management - Smart Driver Admin';
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
            <li><a href="violations.php" class="active"><i class="fas fa-gavel"></i> Violations</a></li>
            <li><a href="add_violation.php"><i class="fas fa-plus"></i> Add Violation</a></li>
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
            <h1><i class="fas fa-gavel"></i> Violation Management</h1>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <a href="add_violation.php" style="padding: 10px 20px; background: #2e7d32; color: white; border-radius: 8px; text-decoration: none;">
                    <i class="fas fa-plus"></i> Add Violation
                </a>
                <span style="background: #1a237e; color: white; padding: 8px 15px; border-radius: 20px;">
                    Total: <?php echo $total; ?> violations
                </span>
            </div>
        </header>

        <!-- Statistics -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #f9a825;">
                <div style="font-size: 28px; font-weight: bold; color: #f9a825;"><?php echo $pending; ?></div>
                <div style="color: #666; font-size: 14px;">⏳ Pending Review</div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #2e7d32;">
                <div style="font-size: 28px; font-weight: bold; color: #2e7d32;"><?php echo $verified; ?></div>
                <div style="color: #666; font-size: 14px;">✅ Verified</div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #c62828;">
                <div style="font-size: 28px; font-weight: bold; color: #c62828;"><?php echo $rejected; ?></div>
                <div style="color: #666; font-size: 14px;">❌ Rejected</div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #e65100;">
                <div style="font-size: 28px; font-weight: bold; color: #e65100;"><?php echo $false_accusations; ?></div>
                <div style="color: #666; font-size: 14px;">🚨 False Accusations</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div style="background: white; border-radius: 12px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                <div>
                    <label style="font-weight: 600; font-size: 14px;">Status:</label>
                    <select name="status" class="form-control" style="width: 150px; padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>⏳ Pending</option>
                        <option value="verified" <?php echo (isset($_GET['status']) && $_GET['status'] == 'verified') ? 'selected' : ''; ?>>✅ Verified</option>
                        <option value="rejected" <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>❌ Rejected</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; font-size: 14px;">Reported By:</label>
                    <select name="reported_by" class="form-control" style="width: 150px; padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        <option value="">All Reporters</option>
                        <option value="police" <?php echo (isset($_GET['reported_by']) && $_GET['reported_by'] == 'police') ? 'selected' : ''; ?>>👮 Police</option>
                        <option value="admin" <?php echo (isset($_GET['reported_by']) && $_GET['reported_by'] == 'admin') ? 'selected' : ''; ?>>👨‍💼 Admin</option>
                        <option value="driver" <?php echo (isset($_GET['reported_by']) && $_GET['reported_by'] == 'driver') ? 'selected' : ''; ?>>🚗 Driver</option>
                        <option value="system" <?php echo (isset($_GET['reported_by']) && $_GET['reported_by'] == 'system') ? 'selected' : ''; ?>>🤖 System</option>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; font-size: 14px;">Search:</label>
                    <input type="text" name="search" class="form-control" placeholder="Driver or rule..." 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
                           style="width: 200px; padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                </div>
                <button type="submit" style="padding: 8px 20px; background: #1a237e; color: white; border: none; border-radius: 8px; cursor: pointer;">🔍 Filter</button>
                <a href="violations.php" style="padding: 8px 20px; background: #666; color: white; border: none; border-radius: 8px; text-decoration: none;">⟳ Reset</a>
            </form>
        </div>

        <!-- Violations Table -->
        <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #1a237e; color: white;">
                        <tr>
                            <th style="padding: 12px;">#</th>
                            <th style="padding: 12px;">Driver</th>
                            <th style="padding: 12px;">Rule</th>
                            <th style="padding: 12px;">Date</th>
                            <th style="padding: 12px;">Location</th>
                            <th style="padding: 12px;">Fine</th>
                            <th style="padding: 12px;">Points</th>
                            <th style="padding: 12px;">Score</th>
                            <th style="padding: 12px;">Status</th>
                            <th style="padding: 12px;">Reported By</th>
                            <th style="padding: 12px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($violations) > 0): ?>
                            <?php $i = 1; while ($v = mysqli_fetch_assoc($violations)): ?>
                                <tr style="border-bottom: 1px solid #eee; background: <?php echo $v['reported_by'] == 'police' && $v['status'] == 'pending' ? '#fff8e1' : ($v['reported_by'] == 'police' && $v['status'] == 'rejected' ? '#ffebee' : 'white'); ?>;">
                                    <td style="padding: 12px;"><?php echo $i++; ?></td>
                                    <td style="padding: 12px;">
                                        <strong><?php echo $v['full_name']; ?></strong><br>
                                        <small style="color: #666;"><?php echo $v['email']; ?></small>
                                    </td>
                                    <td style="padding: 12px;"><?php echo $v['rule_name']; ?></td>
                                    <td style="padding: 12px;"><?php echo date('M d, Y', strtotime($v['violation_date'])); ?></td>
                                    <td style="padding: 12px;"><?php echo $v['location'] ?? 'N/A'; ?></td>
                                    <td style="padding: 12px;"><?php echo number_format($v['fine_amount'], 2); ?> ETB</td>
                                    <td style="padding: 12px; color: #c62828;">-<?php echo $v['points_deducted']; ?></td>
                                    <td style="padding: 12px;">
                                        <span style="font-weight: bold; color: <?php echo $v['driver_score'] >= 80 ? '#2e7d32' : ($v['driver_score'] >= 50 ? '#f9a825' : '#c62828'); ?>;">
                                            <?php echo $v['driver_score']; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php if ($v['status'] == 'pending'): ?>
                                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #fff3e0; color: #e65100;">
                                                ⏳ Pending
                                            </span>
                                        <?php elseif ($v['status'] == 'verified'): ?>
                                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #e8f5e9; color: #2e7d32;">
                                                ✅ Verified
                                            </span>
                                        <?php else: ?>
                                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #ffebee; color: #c62828;">
                                                ❌ Rejected
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php 
                                            $reporter_icons = [
                                                'admin' => '👨‍💼 Admin',
                                                'police' => '👮 Police',
                                                'driver' => '🚗 Driver',
                                                'system' => '🤖 System'
                                            ];
                                            echo $reporter_icons[$v['reported_by']] ?? $v['reported_by'] ?? 'N/A';
                                        ?>
                                        <?php if ($v['reported_by'] == 'police' && $v['status'] == 'pending'): ?>
                                            <br><small style="color: #e65100; font-weight: bold;">⚠️ Police Report</small>
                                        <?php endif; ?>
                                        <?php if ($v['reported_by'] == 'police' && $v['status'] == 'rejected'): ?>
                                            <br><small style="color: #c62828; font-weight: bold;">🚨 False Accusation!</small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px;">
                                        <?php if ($v['status'] == 'pending'): ?>
                                            <a href="violations.php?action=verify&id=<?php echo $v['id']; ?>" 
                                               style="padding: 5px 12px; background: #2e7d32; color: white; border-radius: 5px; text-decoration: none; font-size: 12px; display: inline-block; margin: 2px;"
                                               onclick="return confirm('Verify this violation? This will deduct points from the driver.')">
                                                ✅ Verify
                                            </a>
                                            <a href="violations.php?action=reject&id=<?php echo $v['id']; ?>" 
                                               style="padding: 5px 12px; background: #c62828; color: white; border-radius: 5px; text-decoration: none; font-size: 12px; display: inline-block; margin: 2px;"
                                               onclick="return confirm('Reject this violation? This will clear the driver of any fault.')">
                                                ❌ Reject
                                            </a>
                                        <?php else: ?>
                                            <span style="color: <?php echo $v['status'] == 'verified' ? '#2e7d32' : '#c62828'; ?>; font-weight: 600;">
                                                <?php echo $v['status'] == 'verified' ? '✅ Verified' : '❌ Rejected'; ?>
                                            </span>
                                            <?php if ($v['status'] == 'verified' && isset($v['verified_by_name']) && $v['verified_by_name']): ?>
                                                <br><small style="color: #666;">by <?php echo $v['verified_by_name']; ?></small>
                                            <?php endif; ?>
                                            <?php if ($v['status'] == 'rejected' && isset($v['rejected_by_name']) && $v['rejected_by_name']): ?>
                                                <br><small style="color: #666;">by <?php echo $v['rejected_by_name']; ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <a href="violations.php?action=delete&id=<?php echo $v['id']; ?>" 
                                           onclick="return confirm('Delete this violation?')"
                                           style="padding: 5px 12px; background: #c62828; color: white; border-radius: 5px; text-decoration: none; font-size: 12px; display: inline-block; margin: 2px;">
                                            🗑 Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-check-circle" style="font-size: 48px; color: #2e7d32; display: block; margin-bottom: 10px;"></i>
                                    <p style="color: #666; font-size: 16px;">No violations found</p>
                                    <p style="color: #999; font-size: 14px;">All drivers are driving safely! 🚗</p>
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