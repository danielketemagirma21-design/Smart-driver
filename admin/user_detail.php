<?php
session_start();
include '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../admin_login.php');
    exit();
}

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) {
    header('Location: users.php');
    exit();
}

// ✅ Get user with license information
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    header('Location: users.php');
    exit();
}

// ✅ Get license verification request
$license_request = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM license_verification_requests 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT 1
"));

// ✅ Get license verification logs
$license_logs = mysqli_query($conn, "
    SELECT l.*, a.full_name as admin_name 
    FROM license_verification_logs l
    LEFT JOIN admins a ON l.admin_id = a.id
    WHERE l.request_id = (SELECT id FROM license_verification_requests WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 1)
    ORDER BY l.created_at DESC
");

// Get user statistics
$violations = mysqli_query($conn, "
    SELECT v.*, r.rule_name 
    FROM violations v 
    JOIN traffic_rules r ON v.rule_id = r.id 
    WHERE v.user_id = $user_id 
    ORDER BY v.created_at DESC
");

$fuel_records = mysqli_query($conn, "
    SELECT * FROM fuel_records 
    WHERE user_id = $user_id 
    ORDER BY recorded_at DESC
");

$reports = mysqli_query($conn, "
    SELECT * FROM road_reports 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC
");

$total_violations = mysqli_num_rows($violations);
$total_fuel = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(fuel_amount) as total FROM fuel_records WHERE user_id = $user_id"))['total'] ?? 0;
$total_cost = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(cost) as total FROM fuel_records WHERE user_id = $user_id"))['total'] ?? 0;
$paid_fines = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(fine_amount) as total FROM violations WHERE user_id = $user_id AND paid_status = 'paid'"))['total'] ?? 0;

$page_title = 'User Details - Smart Driver Admin';
$base_url = '../';
include '../includes/header.php';
?>

<style>
/* Driver License Card */
.license-card {
    max-width: 450px;
    margin: 0 auto 20px auto;
    background: linear-gradient(145deg, #1a237e, #0d47a1);
    border-radius: 16px;
    padding: 25px;
    color: white;
    box-shadow: 0 10px 40px rgba(26, 35, 126, 0.4);
    position: relative;
    overflow: hidden;
    border: 2px solid rgba(255, 255, 255, 0.1);
}
.license-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.1) 0%, transparent 70%);
    border-radius: 50%;
}
.license-card::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 150px;
    height: 150px;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.05) 0%, transparent 70%);
    border-radius: 50%;
}
.license-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    padding-bottom: 15px;
    margin-bottom: 15px;
}
.license-card .card-header .title {
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 1px;
}
.license-card .card-header .badge-license {
    background: rgba(255, 215, 0, 0.2);
    color: #ffd700;
    padding: 4px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.license-card .card-body {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 15px;
    position: relative;
    z-index: 1;
}
.license-card .card-body .photo-section {
    text-align: center;
}
.license-card .card-body .photo-section .photo-placeholder {
    width: 100px;
    height: 120px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px auto;
    border: 2px solid rgba(255, 255, 255, 0.2);
    font-size: 48px;
    color: rgba(255, 255, 255, 0.3);
}
.license-card .card-body .photo-section .photo-placeholder img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 6px;
}
.license-card .card-body .photo-section .license-id {
    font-size: 11px;
    opacity: 0.7;
}
.license-card .card-body .info-section {
    font-size: 13px;
}
.license-card .card-body .info-section .field {
    margin-bottom: 6px;
}
.license-card .card-body .info-section .field .label {
    opacity: 0.6;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
}
.license-card .card-body .info-section .field .value {
    font-weight: 600;
    font-size: 14px;
}
.license-card .card-footer {
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 12px;
    margin-top: 15px;
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    opacity: 0.7;
    position: relative;
    z-index: 1;
}
.license-card .card-footer .hologram {
    display: flex;
    align-items: center;
    gap: 5px;
}
.license-card .card-footer .hologram span {
    display: inline-block;
    width: 30px;
    height: 15px;
    background: linear-gradient(90deg, #ffd700, #ff6b00, #ffd700, #ff6b00);
    background-size: 200% 100%;
    animation: shimmer 2s infinite;
    border-radius: 3px;
    opacity: 0.6;
}
@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.license-status {
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
}
.license-status.verified {
    background: #e8f5e9;
    color: #2e7d32;
}
.license-status.pending {
    background: #fff3e0;
    color: #e65100;
}
.license-status.rejected {
    background: #ffebee;
    color: #c62828;
}

.verification-timeline {
    position: relative;
    padding-left: 30px;
}
.verification-timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e0e0e0;
}
.verification-timeline .timeline-item {
    position: relative;
    margin-bottom: 15px;
    padding-left: 15px;
}
.verification-timeline .timeline-item::before {
    content: '';
    position: absolute;
    left: -26px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #1a237e;
    border: 2px solid white;
}
.verification-timeline .timeline-item.verified::before {
    background: #2e7d32;
}
.verification-timeline .timeline-item.rejected::before {
    background: #c62828;
}
.verification-timeline .timeline-item .time {
    font-size: 12px;
    color: #999;
}
.verification-timeline .timeline-item .action {
    font-weight: 600;
    color: #1a237e;
}
</style>

<div class="admin-container">
    <div class="sidebar">
        <h2><i class="fas fa-car"></i> Smart Driver</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php" class="active"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="violations.php"><i class="fas fa-gavel"></i> Violations</a></li>
            <li><a href="fuel.php"><i class="fas fa-gas-pump"></i> Fuel</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <h1><i class="fas fa-user"></i> User Details</h1>
            <a href="users.php" class="btn btn-info"><i class="fas fa-arrow-left"></i> Back</a>
        </header>

        <!-- ============================================ -->
        <!-- DRIVER LICENSE CARD                          -->
        <!-- ============================================ -->
        <div style="background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <h3 style="margin: 0; color: #1a237e;">
                    <i class="fas fa-id-card"></i> Driver License
                </h3>
                <div>
                    <span class="license-status <?php echo $user['license_verified'] ?? 'pending'; ?>">
                        <?php 
                            $status_labels = [
                                'verified' => '✅ Verified',
                                'pending' => '⏳ Pending',
                                'rejected' => '❌ Rejected'
                            ];
                            echo $status_labels[$user['license_verified'] ?? 'pending'] ?? '⏳ Pending';
                        ?>
                    </span>
                </div>
            </div>

            <!-- License Card -->
            <div class="license-card">
                <div class="card-header">
                    <div class="title">🚗 DRIVER LICENSE</div>
                    <div class="badge-license">ETHIOPIA</div>
                </div>
                <div class="card-body">
                    <div class="photo-section">
                        <div class="photo-placeholder">
                            <?php 
                                $profile_image = $user['profile_image'] ?? '';
                                if ($profile_image && file_exists("../assets/uploads/profiles/$profile_image")):
                            ?>
                                <img src="../assets/uploads/profiles/<?php echo $profile_image; ?>" alt="Photo">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                        </div>
                        <div class="license-id">ID: <?php echo $user['driver_license'] ?? 'N/A'; ?></div>
                    </div>
                    <div class="info-section">
                        <div class="field">
                            <span class="label">Full Name</span>
                            <span class="value"><?php echo $user['full_name'] ?? 'N/A'; ?></span>
                        </div>
                        <div class="field">
                            <span class="label">Date of Birth</span>
                            <span class="value"><?php echo date('M d, Y', strtotime($user['dob'] ?? '2000-01-01')); ?></span>
                        </div>
                        <div class="field">
                            <span class="label">Issue Date</span>
                            <span class="value"><?php echo $license_request ? date('M d, Y', strtotime($license_request['license_issue_date'])) : 'N/A'; ?></span>
                        </div>
                        <div class="field">
                            <span class="label">Expiry Date</span>
                            <span class="value">
                                <?php 
                                    $expiry = $license_request ? $license_request['license_expiry_date'] : null;
                                    if ($expiry) {
                                        $expired = strtotime($expiry) < time();
                                        echo date('M d, Y', strtotime($expiry));
                                        echo $expired ? ' ⚠️ EXPIRED' : ' ✅ Valid';
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </span>
                        </div>
                        <div class="field">
                            <span class="label">License Class</span>
                            <span class="value">Class B (Passenger Vehicle)</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div>Issued by: Ministry of Transport</div>
                    <div class="hologram">
                        <span></span> Hologram
                    </div>
                </div>
            </div>

            <!-- License Images -->
            <?php if ($license_request): ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <?php if ($license_request['license_front_image']): ?>
                <div style="text-align: center; background: #f5f5f5; padding: 15px; border-radius: 8px;">
                    <p style="font-weight: 600; color: #1a237e; margin-bottom: 10px;">📄 Front Side</p>
                    <img src="../assets/uploads/licenses/<?php echo $license_request['license_front_image']; ?>" 
                         alt="License Front" 
                         style="max-width: 100%; max-height: 200px; border-radius: 8px; border: 2px solid #ddd;">
                    <br>
                    <a href="../assets/uploads/licenses/<?php echo $license_request['license_front_image']; ?>" 
                       target="_blank" style="font-size: 13px; color: #1a237e; text-decoration: none;">
                        <i class="fas fa-external-link-alt"></i> View Full Image
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($license_request['license_back_image']): ?>
                <div style="text-align: center; background: #f5f5f5; padding: 15px; border-radius: 8px;">
                    <p style="font-weight: 600; color: #1a237e; margin-bottom: 10px;">📄 Back Side</p>
                    <img src="../assets/uploads/licenses/<?php echo $license_request['license_back_image']; ?>" 
                         alt="License Back" 
                         style="max-width: 100%; max-height: 200px; border-radius: 8px; border: 2px solid #ddd;">
                    <br>
                    <a href="../assets/uploads/licenses/<?php echo $license_request['license_back_image']; ?>" 
                       target="_blank" style="font-size: 13px; color: #1a237e; text-decoration: none;">
                        <i class="fas fa-external-link-alt"></i> View Full Image
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ============================================ -->
        <!-- USER INFORMATION                             -->
        <!-- ============================================ -->
        <div class="card" style="margin-bottom: 30px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <h3 style="color: #1a237e;"><?php echo $user['full_name']; ?></h3>
                    <p><i class="fas fa-envelope"></i> <?php echo $user['email']; ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo $user['phone']; ?></p>
                    <p><i class="fas fa-id-card"></i> License: <?php echo $user['driver_license']; ?></p>
                    <p><i class="fas fa-car"></i> Vehicle: <?php echo $user['vehicle_plate'] ?? 'N/A'; ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo $user['city'] ?? 'N/A'; ?></p>
                </div>
                <div>
                    <div style="background: #f5f5f5; padding: 20px; border-radius: 12px; text-align: center;">
                        <h2 style="color: <?php echo getScoreColor($user['driver_score']); ?>; font-size: 48px;">
                            <?php echo $user['driver_score']; ?>
                        </h2>
                        <p style="font-weight: 600; color: <?php echo getScoreColor($user['driver_score']); ?>;">
                            <?php echo getScoreLabel($user['driver_score']); ?>
                        </p>
                    </div>
                </div>
                <div>
                    <div style="background: #f5f5f5; padding: 20px; border-radius: 12px;">
                        <p><strong><i class="fas fa-gavel"></i> Total Violations:</strong> <?php echo $total_violations; ?></p>
                        <p><strong><i class="fas fa-gas-pump"></i> Total Fuel:</strong> <?php echo number_format($total_fuel ?? 0, 1); ?> L</p>
                        <p><strong><i class="fas fa-money-bill"></i> Total Fuel Cost:</strong> <?php echo number_format($total_cost ?? 0, 2); ?> ETB</p>
                        <p><strong><i class="fas fa-coins"></i> Paid Fines:</strong> <?php echo number_format($paid_fines ?? 0, 2); ?> ETB</p>
                        <p><strong><i class="fas fa-calendar"></i> Joined:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- LICENSE VERIFICATION HISTORY                  -->
        <!-- ============================================ -->
        <?php if (mysqli_num_rows($license_logs) > 0): ?>
        <div style="background: white; border-radius: 12px; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h3><i class="fas fa-history"></i> Verification History</h3>
            <div class="verification-timeline">
                <?php while ($log = mysqli_fetch_assoc($license_logs)): ?>
                    <div class="timeline-item <?php echo $log['action']; ?>">
                        <div class="time"><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></div>
                        <div class="action">
                            <?php 
                                $action_icons = [
                                    'verified' => '✅',
                                    'rejected' => '❌',
                                    'pending' => '⏳'
                                ];
                                echo ($action_icons[$log['action']] ?? '📝') . ' ' . ucfirst($log['action']);
                            ?>
                            <?php if ($log['admin_name']): ?>
                                by <?php echo $log['admin_name']; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($log['notes']): ?>
                            <div style="color: #666; font-size: 13px;">📝 <?php echo $log['notes']; ?></div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- TABS                                         -->
        <!-- ============================================ -->
        <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
            <button class="btn btn-primary" onclick="showTab('violations')">🚦 Violations</button>
            <button class="btn btn-info" onclick="showTab('fuel')">⛽ Fuel Records</button>
            <button class="btn btn-warning" onclick="showTab('reports')">🛣️ Road Reports</button>
        </div>

        <!-- Violations Tab -->
        <div id="tab-violations" class="tab-content card">
            <h3><i class="fas fa-gavel"></i> Violations</h3>
            <div class="table-container" style="box-shadow: none; padding: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Rule</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Fine</th>
                            <th>Points</th>
                            <th>Status</th>
                            <th>Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($v = mysqli_fetch_assoc($violations)): ?>
                            <tr>
                                <td><?php echo $v['rule_name']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($v['violation_date'])); ?></td>
                                <td><?php echo $v['location'] ?? 'N/A'; ?></td>
                                <td><?php echo number_format($v['fine_amount'], 2); ?> ETB</td>
                                <td>-<?php echo $v['points_deducted']; ?></td>
                                <td><span class="badge badge-<?php echo $v['status'] == 'verified' ? 'success' : 'warning'; ?>"><?php echo $v['status']; ?></span></td>
                                <td><span class="badge badge-<?php echo $v['paid_status'] == 'paid' ? 'success' : 'danger'; ?>"><?php echo $v['paid_status']; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($violations) == 0): ?>
                            <tr><td colspan="7" style="text-align: center;">No violations</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Fuel Tab -->
        <div id="tab-fuel" class="tab-content card" style="display: none;">
            <h3><i class="fas fa-gas-pump"></i> Fuel Records</h3>
            <div class="table-container" style="box-shadow: none; padding: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Fuel (L)</th>
                            <th>Distance</th>
                            <th>Efficiency</th>
                            <th>Cost</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($f = mysqli_fetch_assoc($fuel_records)): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($f['recorded_at'])); ?></td>
                                <td><?php echo $f['fuel_amount']; ?></td>
                                <td><?php echo $f['distance_covered']; ?> km</td>
                                <td><?php echo $f['fuel_efficiency']; ?> km/L</td>
                                <td><?php echo number_format($f['cost'], 2); ?> ETB</td>
                                <td><?php echo $f['location'] ?? 'N/A'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($fuel_records) == 0): ?>
                            <tr><td colspan="6" style="text-align: center;">No fuel records</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Reports Tab -->
        <div id="tab-reports" class="tab-content card" style="display: none;">
            <h3><i class="fas fa-road"></i> Road Reports</h3>
            <div class="table-container" style="box-shadow: none; padding: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($r = mysqli_fetch_assoc($reports)): ?>
                            <tr>
                                <td><span class="badge badge-info"><?php echo str_replace('_', ' ', $r['report_type']); ?></span></td>
                                <td><?php echo $r['location']; ?></td>
                                <td><?php echo substr($r['description'] ?? '', 0, 50) . '...'; ?></td>
                                <td><span class="badge badge-<?php echo $r['status'] == 'approved' ? 'success' : ($r['status'] == 'pending' ? 'warning' : 'danger'); ?>"><?php echo $r['status']; ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($reports) == 0): ?>
                            <tr><td colspan="5" style="text-align: center;">No reports</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => {
        el.style.display = 'none';
    });
    document.getElementById('tab-' + tab).style.display = 'block';
}

// Show first tab by default
document.addEventListener('DOMContentLoaded', function() {
    showTab('violations');
});
</script>

<?php include '../includes/footer.php'; ?>