<?php
session_start();
include 'config/database.php';

// ✅ CHECK IF USER IS LOGGED IN
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// ✅ GET USER ID FROM SESSION
$user_id = $_SESSION['user_id'];

// ✅ FETCH USER DATA FROM DATABASE
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);

// ✅ IF USER NOT FOUND IN DATABASE, LOGOUT
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// ✅ NOW SAFELY USE THE USER DATA
$driver_score = $user['driver_score'] ?? 0;
$full_name = $user['full_name'] ?? 'User';
$driver_balance = $user['driver_balance'] ?? 0;

// ✅ GET STATISTICS
$total_violations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations WHERE user_id = $user_id"))['count'] ?? 0;
$total_fuel = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM fuel_records WHERE user_id = $user_id"))['count'] ?? 0;
$total_reports = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM road_reports WHERE user_id = $user_id"))['count'] ?? 0;
$unread_notifications = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0"))['count'] ?? 0;

// ✅ GET UNPAID VIOLATIONS COUNT
$unpaid_violations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM violations WHERE user_id = $user_id AND paid_status = 'unpaid' AND status = 'verified'"))['count'] ?? 0;

// ✅ GET TOTAL UNPAID FINES
$total_unpaid_fines = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(fine_amount) as total FROM violations WHERE user_id = $user_id AND paid_status = 'unpaid' AND status = 'verified'"))['total'] ?? 0;

// ✅ GET RECENT VIOLATIONS
$recent_violations = mysqli_query($conn, "
    SELECT v.*, r.rule_name 
    FROM violations v 
    JOIN traffic_rules r ON v.rule_id = r.id 
    WHERE v.user_id = $user_id 
    ORDER BY v.created_at DESC 
    LIMIT 5
");

// ✅ GET RECENT FUEL RECORDS
$recent_fuel = mysqli_query($conn, "
    SELECT * FROM fuel_records 
    WHERE user_id = $user_id 
    ORDER BY recorded_at DESC 
    LIMIT 5
");

$page_title = 'Dashboard - Smart Driver';
$base_url = '';
include 'includes/header.php';
?>

<div class="container">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: #1a237e;">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </h1>
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <span style="background: #1a237e; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px;">
                <i class="fas fa-user"></i> Welcome, <?php echo $full_name; ?>
            </span>
            <span style="background: #2e7d32; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px;">
                <i class="fas fa-star"></i> Score: <?php echo $driver_score; ?>/100
            </span>
            <span style="background: <?php echo $driver_balance < 100 ? '#c62828' : '#1a237e'; ?>; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px;">
                <i class="fas fa-wallet"></i> Balance: <?php echo number_format($driver_balance, 2); ?> ETB
            </span>
            <span style="background: #c62828; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px;">
                <i class="fas fa-bell"></i> <?php echo $unread_notifications; ?> unread
            </span>
        </div>
    </div>

    <!-- Alert for Unpaid Fines -->
    <?php if ($unpaid_violations > 0): ?>
        <div style="background: #ffebee; padding: 15px 20px; border-radius: 8px; border-left: 4px solid #c62828; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <strong style="color: #c62828;">
                    <i class="fas fa-exclamation-triangle"></i> You have <?php echo $unpaid_violations; ?> unpaid violation(s)!
                </strong>
                <span style="color: #666; margin-left: 10px;">
                    Total amount: <?php echo number_format($total_unpaid_fines, 2); ?> ETB
                </span>
            </div>
            <a href="driver/pay_penalty.php" style="padding: 10px 20px; background: #c62828; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">
                <i class="fas fa-money-bill"></i> Pay Now
            </a>
        </div>
    <?php endif; ?>

    <!-- Alert for Low Balance -->
    <?php if ($driver_balance < 100 && $unpaid_violations > 0): ?>
        <div style="background: #fff3e0; padding: 15px 20px; border-radius: 8px; border-left: 4px solid #f9a825; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <strong style="color: #e65100;">
                    <i class="fas fa-exclamation-circle"></i> Low Balance!
                </strong>
                <span style="color: #666; margin-left: 10px;">
                    Your balance (<?php echo number_format($driver_balance, 2); ?> ETB) is insufficient for penalties.
                </span>
            </div>
            <a href="driver/deposit.php" style="padding: 10px 20px; background: #f9a825; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">
                <i class="fas fa-wallet"></i> Deposit Now
            </a>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
        <div style="background: white; border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 5px solid #1a237e;">
            <div style="font-size: 40px; margin-bottom: 10px;">🚦</div>
            <h3 style="font-size: 30px; color: #1a237e;"><?php echo $total_violations; ?></h3>
            <p style="color: #666;">Total Violations</p>
        </div>
        
        <div style="background: white; border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 5px solid #2e7d32;">
            <div style="font-size: 40px; margin-bottom: 10px;">⛽</div>
            <h3 style="font-size: 30px; color: #1a237e;"><?php echo $total_fuel; ?></h3>
            <p style="color: #666;">Fuel Records</p>
        </div>
        
        <div style="background: white; border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 5px solid #f9a825;">
            <div style="font-size: 40px; margin-bottom: 10px;">🛣️</div>
            <h3 style="font-size: 30px; color: #1a237e;"><?php echo $total_reports; ?></h3>
            <p style="color: #666;">Road Reports</p>
        </div>
        
        <div style="background: white; border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 5px solid #<?php echo $driver_score >= 80 ? '2e7d32' : ($driver_score >= 50 ? 'f9a825' : 'c62828'); ?>;">
            <div style="font-size: 40px; margin-bottom: 10px;">⭐</div>
            <h3 style="font-size: 30px; color: #<?php echo $driver_score >= 80 ? '2e7d32' : ($driver_score >= 50 ? 'f9a825' : 'c62828'); ?>;">
                <?php echo $driver_score; ?>
            </h3>
            <p style="color: #666;">
                <?php 
                    if ($driver_score >= 90) echo '🌟 Excellent Driver';
                    elseif ($driver_score >= 70) echo '👍 Good Driver';
                    elseif ($driver_score >= 50) echo '⚠️ Fair Driver';
                    elseif ($driver_score >= 30) echo '⚠️ Risky Driver';
                    else echo '🚫 Dangerous Driver';
                ?>
            </p>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- ✅ ACTION BUTTONS WITH DEPOSIT LINK          -->
    <!-- ============================================ -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <!-- Profile Button -->
        <a href="driver/profile.php" style="background: #1a237e; color: white; padding: 20px 15px; border-radius: 12px; text-align: center; text-decoration: none; transition: transform 0.2s;">
            <i class="fas fa-user" style="font-size: 28px; display: block; margin-bottom: 8px;"></i>
            My Profile
        </a>
        
        <!-- Violations Button -->
        <a href="driver/violations.php" style="background: #c62828; color: white; padding: 20px 15px; border-radius: 12px; text-align: center; text-decoration: none; transition: transform 0.2s;">
            <i class="fas fa-gavel" style="font-size: 28px; display: block; margin-bottom: 8px;"></i>
            My Violations
            <?php if ($unpaid_violations > 0): ?>
                <span style="background: #ffebee; color: #c62828; padding: 2px 10px; border-radius: 20px; font-size: 12px; display: inline-block; margin-top: 5px;">
                    <?php echo $unpaid_violations; ?> unpaid
                </span>
            <?php endif; ?>
        </a>
        
        <!-- Fuel Records Button -->
        <a href="driver/fuel_records.php" style="background: #2e7d32; color: white; padding: 20px 15px; border-radius: 12px; text-align: center; text-decoration: none; transition: transform 0.2s;">
            <i class="fas fa-gas-pump" style="font-size: 28px; display: block; margin-bottom: 8px;"></i>
            Fuel Records
        </a>
        
        <!-- Report Road Button -->
        <a href="driver/report_road.php" style="background: #f9a825; color: white; padding: 20px 15px; border-radius: 12px; text-align: center; text-decoration: none; transition: transform 0.2s;">
            <i class="fas fa-road" style="font-size: 28px; display: block; margin-bottom: 8px;"></i>
            Report Road
        </a>
        
        <!-- ✅ DEPOSIT / CHARGE BUTTON (FIXED) -->
        <a href="driver/deposit.php" style="background: #6a1b9a; color: white; padding: 20px 15px; border-radius: 12px; text-align: center; text-decoration: none; transition: transform 0.2s; border: 2px solid #ce93d8;">
            <i class="fas fa-wallet" style="font-size: 28px; display: block; margin-bottom: 8px;"></i>
            Deposit / Charge
            <?php if ($driver_balance < 100): ?>
                <span style="background: #ffebee; color: #c62828; padding: 2px 10px; border-radius: 20px; font-size: 12px; display: inline-block; margin-top: 5px;">
                    Low Balance!
                </span>
            <?php endif; ?>
        </a>
        
        <!-- Pay Penalties Button -->
        <a href="driver/pay_penalty.php" style="background: #c62828; color: white; padding: 20px 15px; border-radius: 12px; text-align: center; text-decoration: none; transition: transform 0.2s; border: 2px solid #ffcdd2;">
            <i class="fas fa-money-bill" style="font-size: 28px; display: block; margin-bottom: 8px;"></i>
            Pay Penalties
            <?php if ($unpaid_violations > 0): ?>
                <span style="background: #ffebee; color: #c62828; padding: 2px 10px; border-radius: 20px; font-size: 12px; display: inline-block; margin-top: 5px;">
                    <?php echo $unpaid_violations; ?> unpaid
                </span>
            <?php endif; ?>
        </a>
        
        <!-- Notifications Button -->
        <a href="driver/notifications.php" style="background: #0d47a1; color: white; padding: 20px 15px; border-radius: 12px; text-align: center; text-decoration: none; transition: transform 0.2s;">
            <i class="fas fa-bell" style="font-size: 28px; display: block; margin-bottom: 8px;"></i>
            Notifications
            <?php if ($unread_notifications > 0): ?>
                <span style="background: #ffebee; color: #c62828; padding: 2px 10px; border-radius: 20px; font-size: 12px; display: inline-block; margin-top: 5px;">
                    <?php echo $unread_notifications; ?> new
                </span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Recent Violations -->
    <div style="background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h3><i class="fas fa-gavel"></i> Recent Violations</h3>
            <a href="driver/violations.php" style="color: #1a237e; text-decoration: none;">View All →</a>
        </div>
        
        <?php if (mysqli_num_rows($recent_violations) > 0): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #1a237e; color: white;">
                        <tr>
                            <th style="padding: 12px;">Rule</th>
                            <th style="padding: 12px;">Date</th>
                            <th style="padding: 12px;">Fine</th>
                            <th style="padding: 12px;">Points</th>
                            <th style="padding: 12px;">Status</th>
                            <th style="padding: 12px;">Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($v = mysqli_fetch_assoc($recent_violations)): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><?php echo $v['rule_name']; ?></td>
                                <td style="padding: 12px;"><?php echo date('M d, Y', strtotime($v['violation_date'])); ?></td>
                                <td style="padding: 12px;"><?php echo number_format($v['fine_amount'], 2); ?> ETB</td>
                                <td style="padding: 12px; color: #c62828;">-<?php echo $v['points_deducted']; ?></td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?php echo $v['status'] == 'verified' ? '#e8f5e9' : ($v['status'] == 'pending' ? '#fff3e0' : '#ffebee'); ?>; color: <?php echo $v['status'] == 'verified' ? '#2e7d32' : ($v['status'] == 'pending' ? '#e65100' : '#c62828'); ?>;">
                                        <?php echo ucfirst($v['status']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?php echo $v['paid_status'] == 'paid' ? '#e8f5e9' : ($v['paid_status'] == 'overdue' ? '#ffebee' : '#fff3e0'); ?>; color: <?php echo $v['paid_status'] == 'paid' ? '#2e7d32' : ($v['paid_status'] == 'overdue' ? '#c62828' : '#e65100'); ?>;">
                                        <?php echo ucfirst($v['paid_status'] ?? 'Unpaid'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: #666; text-align: center; padding: 20px;">
                <i class="fas fa-check-circle" style="font-size: 24px; color: #2e7d32;"></i>
                No violations recorded yet. Drive safe! 🚗
            </p>
        <?php endif; ?>
    </div>

    <!-- Recent Fuel Records -->
    <div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h3><i class="fas fa-gas-pump"></i> Recent Fuel Records</h3>
            <a href="driver/fuel_records.php" style="color: #1a237e; text-decoration: none;">View All →</a>
        </div>
        
        <?php if (mysqli_num_rows($recent_fuel) > 0): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #1a237e; color: white;">
                        <tr>
                            <th style="padding: 12px;">Date</th>
                            <th style="padding: 12px;">Fuel (L)</th>
                            <th style="padding: 12px;">Distance</th>
                            <th style="padding: 12px;">Efficiency</th>
                            <th style="padding: 12px;">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($f = mysqli_fetch_assoc($recent_fuel)): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><?php echo date('M d, Y', strtotime($f['recorded_at'])); ?></td>
                                <td style="padding: 12px;"><?php echo $f['fuel_amount']; ?></td>
                                <td style="padding: 12px;"><?php echo $f['distance_covered']; ?> km</td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?php echo $f['fuel_efficiency'] >= 15 ? '#e8f5e9' : ($f['fuel_efficiency'] >= 10 ? '#fff3e0' : '#ffebee'); ?>; color: <?php echo $f['fuel_efficiency'] >= 15 ? '#2e7d32' : ($f['fuel_efficiency'] >= 10 ? '#e65100' : '#c62828'); ?>;">
                                        <?php echo number_format($f['fuel_efficiency'], 2); ?> km/L
                                    </span>
                                </td>
                                <td style="padding: 12px;"><?php echo number_format($f['cost'], 2); ?> ETB</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color: #666; text-align: center; padding: 20px;">
                <i class="fas fa-gas-pump" style="font-size: 24px; color: #666;"></i>
                No fuel records yet. Start tracking your fuel consumption! ⛽
            </p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>