<?php
session_start();
include '../config/database.php';

// ✅ Check if driver is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Add fuel record
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_fuel'])) {
    $fuel_amount = (float)$_POST['fuel_amount'];
    $distance_covered = (float)$_POST['distance_covered'];
    $cost = (float)$_POST['cost'];
    $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $fuel_type = mysqli_real_escape_string($conn, $_POST['fuel_type'] ?? 'petrol');
    
    $efficiency = $fuel_amount > 0 ? $distance_covered / $fuel_amount : 0;
    
    $sql = "INSERT INTO fuel_records (user_id, fuel_amount, cost, distance_covered, fuel_efficiency, fuel_type, location) 
            VALUES ($user_id, $fuel_amount, $cost, $distance_covered, $efficiency, '$fuel_type', '$location')";
    
    if (mysqli_query($conn, $sql)) {
        $success = "✅ Fuel record added successfully!";
    } else {
        $error = "❌ Failed to add fuel record.";
    }
}

// Get fuel records
$records = mysqli_query($conn, "
    SELECT * FROM fuel_records 
    WHERE user_id = $user_id 
    ORDER BY recorded_at DESC
");

$page_title = 'Fuel Records - Smart Driver';
$base_url = '../';
include '../includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: #1a237e;">
            <i class="fas fa-gas-pump"></i> Fuel Records
        </h1>
        <a href="../dashboard.php" style="padding: 10px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Add Record Form -->
    <div style="background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
        <h3><i class="fas fa-plus"></i> Add Fuel Record</h3>
        
        <?php if (isset($success)): ?>
            <div style="background: #e8f5e9; padding: 12px; border-radius: 8px; color: #2e7d32; margin: 10px 0;"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div style="background: #ffebee; padding: 12px; border-radius: 8px; color: #c62828; margin: 10px 0;"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label>Fuel Amount (Liters)</label>
                    <input type="number" step="0.01" name="fuel_amount" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Distance Covered (km)</label>
                    <input type="number" step="0.1" name="distance_covered" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Cost (ETB)</label>
                    <input type="number" step="0.01" name="cost" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Location (Optional)</label>
                    <input type="text" name="location" class="form-control" placeholder="Where did you refuel?">
                </div>
            </div>
            <button type="submit" name="add_fuel" style="padding: 12px 30px; background: #1a237e; color: white; border: none; border-radius: 8px; cursor: pointer;">
                <i class="fas fa-save"></i> Add Record
            </button>
        </form>
    </div>

    <!-- Records List -->
    <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
        <h3><i class="fas fa-list"></i> Fuel History</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #1a237e; color: white;">
                    <tr>
                        <th style="padding: 12px;">#</th>
                        <th style="padding: 12px;">Date</th>
                        <th style="padding: 12px;">Fuel (L)</th>
                        <th style="padding: 12px;">Distance</th>
                        <th style="padding: 12px;">Efficiency</th>
                        <th style="padding: 12px;">Cost</th>
                        <th style="padding: 12px;">Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($records) > 0): ?>
                        <?php $i = 1; while ($r = mysqli_fetch_assoc($records)): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><?php echo $i++; ?></td>
                                <td style="padding: 12px;"><?php echo date('M d, Y', strtotime($r['recorded_at'])); ?></td>
                                <td style="padding: 12px;"><?php echo $r['fuel_amount']; ?></td>
                                <td style="padding: 12px;"><?php echo $r['distance_covered']; ?> km</td>
                                <td style="padding: 12px;">
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?php echo $r['fuel_efficiency'] >= 15 ? '#e8f5e9' : ($r['fuel_efficiency'] >= 10 ? '#fff3e0' : '#ffebee'); ?>; color: <?php echo $r['fuel_efficiency'] >= 15 ? '#2e7d32' : ($r['fuel_efficiency'] >= 10 ? '#e65100' : '#c62828'); ?>;">
                                        <?php echo number_format($r['fuel_efficiency'], 2); ?> km/L
                                    </span>
                                </td>
                                <td style="padding: 12px;"><?php echo number_format($r['cost'], 2); ?> ETB</td>
                                <td style="padding: 12px;"><?php echo $r['location'] ?? 'N/A'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px;">
                                <i class="fas fa-gas-pump" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></i>
                                <p>No fuel records found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>