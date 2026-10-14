<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin_login.php');
    exit();
}

$success = '';
$error = '';

// Get all drivers for dropdown
$drivers = mysqli_query($conn, "SELECT id, full_name, email FROM users ORDER BY full_name");

// Get all traffic rules for dropdown
$rules = mysqli_query($conn, "SELECT id, rule_name, penalty_amount, points_deducted FROM traffic_rules");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_violation'])) {
    $user_id = (int)$_POST['user_id'];
    $rule_id = (int)$_POST['rule_id'];
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $violation_date = mysqli_real_escape_string($conn, $_POST['violation_date']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $reported_by = mysqli_real_escape_string($conn, $_POST['reported_by']); // ✅ POLICE OPTION
    
    // Get rule details
    $rule = mysqli_fetch_assoc(mysqli_query($conn, "SELECT penalty_amount, points_deducted FROM traffic_rules WHERE id = $rule_id"));
    $fine_amount = $rule['penalty_amount'];
    $points_deducted = $rule['points_deducted'];
    
    $sql = "INSERT INTO violations (user_id, rule_id, violation_date, location, fine_amount, points_deducted, description, reported_by, status) 
            VALUES ($user_id, $rule_id, '$violation_date', '$location', $fine_amount, $points_deducted, '$description', '$reported_by', 'pending')";
    
    if (mysqli_query($conn, $sql)) {
        $success = "✅ Violation added successfully!";
        
        // Log activity
        mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                            VALUES (" . $_SESSION['admin_id'] . ", 'add_violation', 'Added violation for user ID: $user_id reported by: $reported_by', '" . $_SERVER['REMOTE_ADDR'] . "')");
    } else {
        $error = "❌ Failed to add violation: " . mysqli_error($conn);
    }
}

$page_title = 'Add Violation - Smart Driver Admin';
$base_url = '../';
include '../includes/header.php';
?>

<div class="admin-container">
    <div class="sidebar">
        <h2><i class="fas fa-car"></i> Smart Driver</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="add_user.php"><i class="fas fa-user-plus"></i> Add User</a></li>
            <li><a href="violations.php"><i class="fas fa-gavel"></i> Violations</a></li>
            <li><a href="add_violation.php" class="active"><i class="fas fa-plus"></i> Add Violation</a></li>
            <li><a href="fuel.php"><i class="fas fa-gas-pump"></i> Fuel</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="activity.php"><i class="fas fa-clock"></i> Activity</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h1><i class="fas fa-plus"></i> Add New Violation</h1>
            <a href="violations.php" style="padding: 10px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Violations
            </a>
        </header>

        <div style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <?php if ($success): ?>
                <div style="background: #e8f5e9; padding: 12px; border-radius: 8px; color: #2e7d32; margin-bottom: 15px;"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background: #ffebee; padding: 12px; border-radius: 8px; color: #c62828; margin-bottom: 15px;"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group" style="grid-column: span 2;">
                        <label style="font-weight: 600;">Driver *</label>
                        <select name="user_id" class="form-control" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                            <option value="">Select Driver</option>
                            <?php while ($d = mysqli_fetch_assoc($drivers)): ?>
                                <option value="<?php echo $d['id']; ?>">
                                    <?php echo $d['full_name']; ?> - <?php echo $d['email']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="grid-column: span 2;">
                        <label style="font-weight: 600;">Rule *</label>
                        <select name="rule_id" class="form-control" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                            <option value="">Select Rule</option>
                            <?php while ($r = mysqli_fetch_assoc($rules)): ?>
                                <option value="<?php echo $r['id']; ?>">
                                    <?php echo $r['rule_name']; ?> - <?php echo number_format($r['penalty_amount'], 2); ?> ETB
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="grid-column: span 2;">
                        <label style="font-weight: 600;">Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g., Bole Road, Addis Ababa" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    </div>
                    
                    <div class="form-group" style="grid-column: span 2;">
                        <label style="font-weight: 600;">Violation Date & Time *</label>
                        <input type="datetime-local" name="violation_date" class="form-control" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    </div>
                    
                    <div class="form-group" style="grid-column: span 2;">
                        <label style="font-weight: 600;">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Enter violation details..." style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
                    </div>
                    
                    <!-- ✅ POLICE OPTION - "Reported By" Field -->
                    <div class="form-group" style="grid-column: span 2; border: 2px solid #1a237e; border-radius: 8px; padding: 15px; background: #f8f9ff;">
                        <label style="font-weight: 600; color: #1a237e;">
                            <i class="fas fa-info-circle"></i> Reported By *
                        </label>
                        <select name="reported_by" class="form-control" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                            <option value="admin">👨‍💼 Admin</option>
                            <option value="police">👮 Police</option>
                            <option value="driver">🚗 Driver (Self-Report)</option>
                            <option value="system">🤖 System</option>
                        </select>
                        <small style="color: #666;">👮 Select "Police" if the violation was reported by police officers</small>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="add_violation" style="padding: 12px 30px; background: #1a237e; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-save"></i> Add Violation
                    </button>
                    <a href="violations.php" style="padding: 12px 30px; background: #c62828; color: white; border: none; border-radius: 8px; text-decoration: none; font-weight: 600;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>