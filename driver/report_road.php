<?php
session_start();
include '../config/database.php';

// Check if driver is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Submit road report
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['report_road'])) {
    $report_type = mysqli_real_escape_string($conn, $_POST['report_type']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    
    $sql = "INSERT INTO road_reports (user_id, report_type, location, description, latitude, longitude, status) 
            VALUES ($user_id, '$report_type', '$location', '$description', $latitude, $longitude, 'pending')";
    
    if (mysqli_query($conn, $sql)) {
        $success = "✅ Road report submitted successfully! Admin will review it.";
        
        // Send notification to admin (optional)
        // mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type) 
        //                     VALUES (1, 'New Road Report', 'A new road report has been submitted.', 'road_report')");
    } else {
        $error = "❌ Failed to submit report: " . mysqli_error($conn);
    }
}

// Get user's reports
$reports = mysqli_query($conn, "
    SELECT * FROM road_reports 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC
");

$page_title = 'Report Road - Smart Driver';
$base_url = '../';
include '../includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0;">
        <h1 style="color: #1a237e;">
            <i class="fas fa-road"></i> Report Road Condition
        </h1>
        <a href="../dashboard.php" style="padding: 10px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- Report Form -->
        <div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h3><i class="fas fa-plus"></i> Submit Report</h3>
            
            <?php if ($success): ?>
                <div style="background: #e8f5e9; padding: 12px; border-radius: 8px; color: #2e7d32; margin: 10px 0;">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background: #ffebee; padding: 12px; border-radius: 8px; color: #c62828; margin: 10px 0;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Report Type *</label>
                    <select name="report_type" class="form-control" required>
                        <option value="construction">🚧 Road Construction</option>
                        <option value="accident">🚨 Accident</option>
                        <option value="traffic_jam">🚗 Traffic Jam</option>
                        <option value="pothole">🕳️ Pothole</option>
                        <option value="broken_traffic_light">🚦 Broken Traffic Light</option>
                        <option value="other">📌 Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Location *</label>
                    <input type="text" name="location" class="form-control" required placeholder="e.g., Bole Road, Addis Ababa">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Describe the road condition in detail..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Latitude (Optional)</label>
                        <input type="text" name="latitude" class="form-control" placeholder="e.g., 9.0322">
                    </div>
                    <div class="form-group">
                        <label>Longitude (Optional)</label>
                        <input type="text" name="longitude" class="form-control" placeholder="e.g., 38.7468">
                    </div>
                </div>
                
                <button type="submit" name="report_road" style="padding: 12px 30px; background: #1a237e; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px;">
                    <i class="fas fa-paper-plane"></i> Submit Report
                </button>
            </form>
            
            <div style="margin-top: 15px; padding: 15px; background: #fff3e0; border-radius: 8px; border-left: 4px solid #f9a825;">
                <p style="margin: 0; color: #e65100; font-size: 14px;">
                    <i class="fas fa-info-circle"></i> Your report will be reviewed by an admin. 
                    You'll be notified when it's approved or rejected.
                </p>
            </div>
        </div>

        <!-- My Reports -->
        <div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h3><i class="fas fa-list"></i> My Reports</h3>
            <div style="max-height: 500px; overflow-y: auto;">
                <?php if (mysqli_num_rows($reports) > 0): ?>
                    <?php while ($r = mysqli_fetch_assoc($reports)): ?>
                        <div style="border-bottom: 1px solid #e0e0e0; padding: 15px 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h4 style="margin: 0; color: #1a237e;">
                                    <?php 
                                        $types = [
                                            'construction' => '🚧 Construction',
                                            'accident' => '🚨 Accident', 
                                            'traffic_jam' => '🚗 Traffic Jam',
                                            'pothole' => '🕳️ Pothole',
                                            'broken_traffic_light' => '🚦 Broken Light',
                                            'other' => '📌 Other'
                                        ];
                                        echo $types[$r['report_type']] ?? $r['report_type'];
                                    ?>
                                </h4>
                                <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?php echo $r['status'] == 'approved' ? '#e8f5e9' : ($r['status'] == 'pending' ? '#fff3e0' : '#ffebee'); ?>; color: <?php echo $r['status'] == 'approved' ? '#2e7d32' : ($r['status'] == 'pending' ? '#e65100' : '#c62828'); ?>;">
                                    <?php echo ucfirst($r['status']); ?>
                                </span>
                            </div>
                            <p style="margin: 5px 0; color: #666; font-size: 14px;">
                                <i class="fas fa-map-marker-alt"></i> <?php echo $r['location']; ?>
                            </p>
                            <?php if ($r['description']): ?>
                                <p style="margin: 5px 0; color: #666; font-size: 14px;"><?php echo $r['description']; ?></p>
                            <?php endif; ?>
                            <small style="color: #999;">
                                <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($r['created_at'])); ?>
                            </small>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 30px;">
                        <i class="fas fa-road" style="font-size: 48px; display: block; margin-bottom: 10px; color: #ccc;"></i>
                        No reports submitted yet
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>