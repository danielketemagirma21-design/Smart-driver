<?php
session_start();
include '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin_login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$page_title = 'License Verification - Smart Driver Admin';
$base_url = '../';
include '../includes/header.php';

// Handle verification actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'verify') {
        // Get request details
        $request = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM license_verification_requests WHERE id = $request_id"));
        
        if ($request) {
            // Update request status
            mysqli_query($conn, "UPDATE license_verification_requests SET 
                                status = 'verified', 
                                verified_by = $admin_id, 
                                verified_date = NOW() 
                                WHERE id = $request_id");
            
            // Update user license verification status
            mysqli_query($conn, "UPDATE users SET 
                                license_verified = 'verified',
                                license_verified_date = NOW(),
                                license_verified_by = $admin_id,
                                license_expiry_date = '{$request['license_expiry_date']}',
                                license_issue_date = '{$request['license_issue_date']}'
                                WHERE id = {$request['user_id']}");
            
            // Log activity
            mysqli_query($conn, "INSERT INTO license_verification_logs (request_id, admin_id, action, notes) 
                                VALUES ($request_id, $admin_id, 'verified', 'License verified successfully')");
            
            // Send notification to driver
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type) 
                                VALUES ({$request['user_id']}, '✅ License Verified', 
                                'Your driver license has been verified successfully! You can now use the system.', 'license')");
            
            $success = "✅ License verified successfully!";
        }
        
    } elseif ($action == 'reject') {
        $reason = mysqli_real_escape_string($conn, $_GET['reason'] ?? 'Invalid or unclear license image');
        
        // Get request details
        $request = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM license_verification_requests WHERE id = $request_id"));
        
        if ($request) {
            // Update request status
            mysqli_query($conn, "UPDATE license_verification_requests SET 
                                status = 'rejected', 
                                verified_by = $admin_id, 
                                verified_date = NOW(),
                                rejection_reason = '$reason'
                                WHERE id = $request_id");
            
            // Update user license verification status
            mysqli_query($conn, "UPDATE users SET 
                                license_verified = 'rejected',
                                license_verified_date = NOW(),
                                license_verified_by = $admin_id
                                WHERE id = {$request['user_id']}");
            
            // Log activity
            mysqli_query($conn, "INSERT INTO license_verification_logs (request_id, admin_id, action, notes) 
                                VALUES ($request_id, $admin_id, 'rejected', '$reason')");
            
            // Send notification to driver
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type) 
                                VALUES ({$request['user_id']}, '❌ License Rejected', 
                                'Your driver license verification was rejected. Reason: $reason. Please upload a clear image.', 'license')");
            
            $success = "❌ License rejected successfully!";
        }
        
    } elseif ($action == 'delete') {
        mysqli_query($conn, "DELETE FROM license_verification_requests WHERE id = $request_id");
        $success = "🗑️ Request deleted successfully!";
    }
}

// Get statistics
$total_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM license_verification_requests"))['count'] ?? 0;
$pending_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM license_verification_requests WHERE status = 'pending'"))['count'] ?? 0;
$verified_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM license_verification_requests WHERE status = 'verified'"))['count'] ?? 0;
$rejected_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM license_verification_requests WHERE status = 'rejected'"))['count'] ?? 0;

// Get pending requests
$pending_requests_list = mysqli_query($conn, "
    SELECT * FROM license_verification_requests 
    WHERE status = 'pending' 
    ORDER BY created_at DESC
");

// Get all requests (for history)
$all_requests = mysqli_query($conn, "
    SELECT r.*, a.full_name as admin_name 
    FROM license_verification_requests r
    LEFT JOIN admins a ON r.verified_by = a.id
    ORDER BY r.created_at DESC
    LIMIT 20
");
?>

<style>
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.status-pending {
    background: #fff3e0;
    color: #e65100;
}
.status-verified {
    background: #e8f5e9;
    color: #2e7d32;
}
.status-rejected {
    background: #ffebee;
    color: #c62828;
}
.license-image {
    width: 80px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    cursor: pointer;
    transition: transform 0.3s;
}
.license-image:hover {
    transform: scale(2);
    z-index: 1000;
    position: relative;
}
</style>

<div class="admin-container">
    <div class="sidebar">
        <h2><i class="fas fa-car"></i> Smart Driver</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="add_user.php"><i class="fas fa-user-plus"></i> Add User</a></li>
            <li><a href="violations.php"><i class="fas fa-gavel"></i> Violations</a></li>
            <li><a href="license_verification.php" class="active"><i class="fas fa-id-card"></i> License Verify</a></li>
            <li><a href="fuel.php"><i class="fas fa-gas-pump"></i> Fuel</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="activity.php"><i class="fas fa-clock"></i> Activity</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h1><i class="fas fa-id-card"></i> License Verification</h1>
            <div style="display: flex; gap: 10px; align-items: center;">
                <span style="background: #f9a825; color: white; padding: 8px 15px; border-radius: 20px;">
                    ⏳ <?php echo $pending_requests; ?> Pending
                </span>
                <span style="background: #2e7d32; color: white; padding: 8px 15px; border-radius: 20px;">
                    ✅ <?php echo $verified_requests; ?> Verified
                </span>
                <span style="background: #c62828; color: white; padding: 8px 15px; border-radius: 20px;">
                    ❌ <?php echo $rejected_requests; ?> Rejected
                </span>
            </div>
        </header>

        <?php if (isset($success)): ?>
            <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; color: #2e7d32; margin-bottom: 20px; border-left: 4px solid #2e7d32;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Pending Requests -->
        <?php if (mysqli_num_rows($pending_requests_list) > 0): ?>
            <h2 style="color: #e65100; margin: 20px 0 15px 0;">
                <i class="fas fa-clock"></i> Pending Verification Requests
            </h2>
            
            <?php while ($request = mysqli_fetch_assoc($pending_requests_list)): ?>
                <div style="background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #f9a825;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: center;">
                        <div>
                            <h4 style="margin: 0; color: #1a237e;"><?php echo $request['full_name']; ?></h4>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                                <i class="fas fa-envelope"></i> <?php echo $request['email']; ?>
                            </p>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                                <i class="fas fa-phone"></i> <?php echo $request['phone']; ?>
                            </p>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                                <i class="fas fa-id-card"></i> License: <?php echo $request['driver_license']; ?>
                            </p>
                        </div>
                        <div style="text-align: center;">
                            <div><strong>Front Image</strong></div>
                            <?php if ($request['license_front_image']): ?>
                                <img src="../assets/uploads/licenses/<?php echo $request['license_front_image']; ?>" 
                                     alt="License Front" class="license-image">
                            <?php else: ?>
                                <span style="color: #999;">No image</span>
                            <?php endif; ?>
                        </div>
                        <div style="text-align: center;">
                            <div><strong>Back Image</strong></div>
                            <?php if ($request['license_back_image']): ?>
                                <img src="../assets/uploads/licenses/<?php echo $request['license_back_image']; ?>" 
                                     alt="License Back" class="license-image">
                            <?php else: ?>
                                <span style="color: #999;">No image</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="margin-bottom: 8px;">
                                <a href="license_verification.php?action=verify&id=<?php echo $request['id']; ?>" 
                                   style="display: block; padding: 8px 15px; background: #2e7d32; color: white; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 600;"
                                   onclick="return confirm('Verify this license?')">
                                    ✅ Verify
                                </a>
                            </div>
                            <div>
                                <button onclick="showRejectModal(<?php echo $request['id']; ?>, '<?php echo $request['full_name']; ?>')" 
                                        style="display: block; width: 100%; padding: 8px 15px; background: #c62828; color: white; border: none; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 600; cursor: pointer;">
                                    ❌ Reject
                                </button>
                            </div>
                            <div style="margin-top: 8px;">
                                <a href="license_verification.php?action=delete&id=<?php echo $request['id']; ?>" 
                                   style="display: block; padding: 8px 15px; background: #666; color: white; border-radius: 8px; text-decoration: none; text-align: center; font-size: 12px;"
                                   onclick="return confirm('Delete this request?')">
                                    🗑️ Delete
                                </a>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 10px; font-size: 12px; color: #999;">
                        Requested: <?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="background: #e8f5e9; padding: 20px; border-radius: 12px; text-align: center; margin: 20px 0;">
                <i class="fas fa-check-circle" style="font-size: 48px; color: #2e7d32;"></i>
                <h3 style="color: #2e7d32;">No Pending Requests</h3>
                <p style="color: #666;">All license verification requests have been processed.</p>
            </div>
        <?php endif; ?>

        <!-- All Requests History -->
        <h2 style="color: #1a237e; margin: 30px 0 15px 0;">
            <i class="fas fa-history"></i> Verification History
        </h2>
        
        <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #1a237e; color: white;">
                        <tr>
                            <th style="padding: 12px;">#</th>
                            <th style="padding: 12px;">Driver</th>
                            <th style="padding: 12px;">License</th>
                            <th style="padding: 12px;">Status</th>
                            <th style="padding: 12px;">Verified By</th>
                            <th style="padding: 12px;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($request = mysqli_fetch_assoc($all_requests)): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><?php echo $i++; ?></td>
                                <td style="padding: 12px;">
                                    <strong><?php echo $request['full_name']; ?></strong><br>
                                    <small style="color: #666;"><?php echo $request['email']; ?></small>
                                </td>
                                <td style="padding: 12px;"><?php echo $request['driver_license']; ?></td>
                                <td style="padding: 12px;">
                                    <span class="status-badge status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                    <?php if ($request['status'] == 'rejected' && $request['rejection_reason']): ?>
                                        <br><small style="color: #c62828;"><?php echo $request['rejection_reason']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px;">
                                    <?php echo $request['admin_name'] ?? 'N/A'; ?>
                                </td>
                                <td style="padding: 12px; font-size: 12px; color: #666;">
                                    <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($all_requests) == 0): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-inbox" style="font-size: 48px; color: #ccc;"></i>
                                    <p>No verification requests found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- REJECT MODAL                                  -->
<!-- ============================================ -->
<div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; border-radius: 16px; padding: 30px; max-width: 500px; width: 95%; position: relative;">
        <button onclick="closeRejectModal()" style="position: absolute; top: 10px; right: 15px; font-size: 24px; background: none; border: none; cursor: pointer; color: #999;">
            <i class="fas fa-times"></i>
        </button>
        
        <h2 style="color: #c62828; margin-bottom: 10px;">
            <i class="fas fa-times-circle"></i> Reject License
        </h2>
        <p style="color: #666; margin-bottom: 20px;">
            Provide a reason for rejecting <strong id="reject_driver_name"></strong>'s license.
        </p>
        
        <form method="GET" id="rejectForm">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" id="reject_id">
            
            <div class="form-group">
                <label style="font-weight: 600; display: block; margin-bottom: 5px;">Rejection Reason *</label>
                <select name="reason" class="form-control" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    <option value="Invalid or unclear license image">Invalid or unclear license image</option>
                    <option value="License expired">License expired</option>
                    <option value="License number mismatch">License number mismatch</option>
                    <option value="Fake or tampered license">Fake or tampered license</option>
                    <option value="Incomplete information">Incomplete information</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label style="font-weight: 600; display: block; margin-bottom: 5px;">Additional Notes</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Enter additional notes..." style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 30px; background: #c62828; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; flex: 1;">
                    ❌ Reject License
                </button>
                <button type="button" onclick="closeRejectModal()" style="padding: 12px 30px; background: #666; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal(id, name) {
    document.getElementById('reject_id').value = id;
    document.getElementById('reject_driver_name').textContent = name;
    document.getElementById('rejectModal').style.display = 'flex';
    document.getElementById('rejectModal').style.justifyContent = 'center';
    document.getElementById('rejectModal').style.alignItems = 'center';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('rejectModal');
    if (event.target === modal) {
        closeRejectModal();
    }
}
</script>

<?php include '../includes/footer.php'; ?>