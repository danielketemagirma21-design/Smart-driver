<?php
session_start();
include '../config/database.php';

// ✅ Check if driver is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// ✅ Get user violations
$violations = mysqli_query($conn, "
    SELECT v.*, r.rule_name, r.description as rule_description 
    FROM violations v 
    JOIN traffic_rules r ON v.rule_id = r.id 
    WHERE v.user_id = $user_id 
    ORDER BY v.created_at DESC
");

$page_title = 'My Violations - Smart Driver';
$base_url = '../';
include '../includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: #1a237e;">
            <i class="fas fa-gavel"></i> My Violations
        </h1>
        <a href="../dashboard.php" style="padding: 10px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #1a237e; color: white;">
                    <tr>
                        <th style="padding: 12px;">#</th>
                        <th style="padding: 12px;">Rule</th>
                        <th style="padding: 12px;">Date</th>
                        <th style="padding: 12px;">Location</th>
                        <th style="padding: 12px;">Fine</th>
                        <th style="padding: 12px;">Points</th>
                        <th style="padding: 12px;">Status</th>
                        <th style="padding: 12px;">Paid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($violations) > 0): ?>
                        <?php $i = 1; while ($v = mysqli_fetch_assoc($violations)): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><?php echo $i++; ?></td>
                                <td style="padding: 12px;">
                                    <strong><?php echo $v['rule_name']; ?></strong>
                                    <?php if ($v['rule_description']): ?>
                                        <br><small style="color: #666;"><?php echo substr($v['rule_description'], 0, 50); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px;"><?php echo date('M d, Y', strtotime($v['violation_date'])); ?></td>
                                <td style="padding: 12px;"><?php echo $v['location'] ?? 'N/A'; ?></td>
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
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                <i class="fas fa-check-circle" style="font-size: 48px; color: #2e7d32; display: block; margin-bottom: 10px;"></i>
                                <h3 style="color: #2e7d32;">No violations!</h3>
                                <p style="color: #666;">Keep driving safely! 🚗</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>