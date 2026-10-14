<?php
session_start();
include '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin_login.php');
    exit();
}

$success = '';
$error = '';

// ✅ UPDATE SETTINGS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $settings = $_POST;
    unset($settings['update_settings']);
    
    foreach ($settings as $key => $value) {
        $value = mysqli_real_escape_string($conn, $value);
        $key = mysqli_real_escape_string($conn, $key);
        
        $sql = "UPDATE system_settings SET setting_value = '$value' WHERE setting_key = '$key'";
        if (!mysqli_query($conn, $sql)) {
            $error = "Failed to update setting: $key";
            break;
        }
    }
    
    if (empty($error)) {
        $success = "✅ Settings updated successfully!";
        // Log activity
        mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description) 
                            VALUES (" . $_SESSION['admin_id'] . ", 'update_settings', 'Updated system settings')");
    }
}

// ✅ GET ALL SETTINGS
$settings_query = mysqli_query($conn, "SELECT * FROM system_settings ORDER BY setting_key");
$settings = [];
while ($row = mysqli_fetch_assoc($settings_query)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$page_title = 'System Settings - Smart Driver Admin';
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
            <li><a href="add_violation.php"><i class="fas fa-plus"></i> Add Violation</a></li>
            <li><a href="fuel.php"><i class="fas fa-gas-pump"></i> Fuel</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="activity.php"><i class="fas fa-clock"></i> Activity</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h1><i class="fas fa-cog"></i> System Settings</h1>
            <a href="dashboard.php" class="btn btn-info" style="padding: 10px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </header>

        <?php if ($success): ?>
            <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; color: #2e7d32; margin-bottom: 20px; border-left: 4px solid #2e7d32;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #ffebee; padding: 15px; border-radius: 8px; color: #c62828; margin-bottom: 20px; border-left: 4px solid #c62828;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- General Settings -->
            <div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <h3><i class="fas fa-user-cog"></i> General Settings</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Admin Name</label>
                        <input type="text" name="admin_name" class="form-control" 
                               value="<?php echo $settings['admin_name'] ?? 'Smart Driver Admin'; ?>"
                               style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Email</label>
                        <input type="email" name="admin_email" class="form-control" 
                               value="<?php echo $settings['admin_email'] ?? 'admin@smartdriver.com'; ?>"
                               style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    </div>
                    
                    <div class="form-group" style="border: 2px solid #1a237e; padding: 15px; border-radius: 8px; background: #f8f9ff;">
                        <label style="font-weight: bold; color: #1a237e;">
                            <i class="fas fa-phone"></i> Admin Phone Number for TeleBirr & CBE Birr *
                        </label>
                        <input type="text" name="admin_phone" class="form-control" 
                               value="<?php echo $settings['admin_phone'] ?? '+251911234567'; ?>"
                               placeholder="e.g., +251911234567"
                               style="width: 100%; padding: 12px; border: 2px solid #1a237e; border-radius: 8px;">
                        <small style="color: #666;">This number will be used for TeleBirr and CBE Birr payments</small>
                    </div>
                    
                    <button type="submit" name="update_settings" style="padding: 12px 30px; background: #1a237e; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-save"></i> Update Settings
                    </button>
                </form>
            </div>

            <!-- Payment Settings -->
            <div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <h3><i class="fas fa-credit-card"></i> Payment Settings</h3>
                <form method="POST">
                    <div class="form-group" style="border: 2px solid #2e7d32; padding: 15px; border-radius: 8px; background: #f0f8f0;">
                        <label style="font-weight: bold; color: #2e7d32;">
                            <i class="fas fa-university"></i> Bank Account Details
                        </label>
                        <div class="form-group">
                            <label>Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" 
                                   value="<?php echo $settings['bank_name'] ?? 'Commercial Bank of Ethiopia'; ?>"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label>Account Number</label>
                            <input type="text" name="bank_account" class="form-control" 
                                   value="<?php echo $settings['bank_account'] ?? '1000 1234 5678'; ?>"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label>Account Holder Name</label>
                            <input type="text" name="bank_account_name" class="form-control" 
                                   value="<?php echo $settings['bank_account_name'] ?? 'Smart Driver'; ?>"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>
                    </div>
                    
                    <div class="form-group" style="border: 2px solid #f9a825; padding: 15px; border-radius: 8px; background: #fffdf0;">
                        <label style="font-weight: bold; color: #e65100;">
                            <i class="fas fa-building"></i> Office Details
                        </label>
                        <div class="form-group">
                            <label>Office Address</label>
                            <input type="text" name="office_address" class="form-control" 
                                   value="<?php echo $settings['office_address'] ?? 'Bole Road, Addis Ababa'; ?>"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>
                        <div class="form-group">
                            <label>Office Phone</label>
                            <input type="text" name="office_phone" class="form-control" 
                                   value="<?php echo $settings['office_phone'] ?? '+251 911 234 567'; ?>"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_settings" style="padding: 12px 30px; background: #2e7d32; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-save"></i> Update Payment Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>