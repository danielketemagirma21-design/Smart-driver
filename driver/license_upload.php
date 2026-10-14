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

// ✅ GET USER INFO
$user_sql = "SELECT * FROM users WHERE id = $user_id";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

// Check if already submitted
$existing_request = mysqli_fetch_assoc(mysqli_query($conn, 
    "SELECT * FROM license_verification_requests WHERE user_id = $user_id AND status IN ('pending', 'verified')"
));

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_license'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $driver_license = mysqli_real_escape_string($conn, $_POST['driver_license']);
    $license_issue_date = mysqli_real_escape_string($conn, $_POST['license_issue_date']);
    $license_expiry_date = mysqli_real_escape_string($conn, $_POST['license_expiry_date']);
    
    $front_image = '';
    $back_image = '';
    
    // ✅ Upload front image
    if (isset($_FILES['license_front']) && $_FILES['license_front']['error'] == 0) {
        $target_dir = "../assets/uploads/licenses/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $front_image = time() . '_front_' . basename($_FILES['license_front']['name']);
        move_uploaded_file($_FILES['license_front']['tmp_name'], $target_dir . $front_image);
    }
    
    // ✅ Upload back image
    if (isset($_FILES['license_back']) && $_FILES['license_back']['error'] == 0) {
        $target_dir = "../assets/uploads/licenses/";
        $back_image = time() . '_back_' . basename($_FILES['license_back']['name']);
        move_uploaded_file($_FILES['license_back']['tmp_name'], $target_dir . $back_image);
    }
    
    // ✅ Insert into verification requests
    $sql = "INSERT INTO license_verification_requests (
                user_id, full_name, email, phone, driver_license,
                license_front_image, license_back_image, 
                license_issue_date, license_expiry_date, status
            ) VALUES (
                $user_id, '$full_name', '$email', '$phone', '$driver_license',
                '$front_image', '$back_image',
                '$license_issue_date', '$license_expiry_date', 'pending'
            )";
    
    if (mysqli_query($conn, $sql)) {
        $success = "✅ License submitted for verification! Please wait for admin approval.";
    } else {
        $error = "❌ Failed to submit: " . mysqli_error($conn);
    }
}

$page_title = 'License Verification - Smart Driver';
$base_url = '../';
include '../includes/header.php';
?>

<style>
.license-preview-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 15px 0;
}
.license-side {
    border: 2px dashed #ccc;
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: border-color 0.3s;
}
.license-side:hover {
    border-color: #1a237e;
}
.license-side img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 8px;
    object-fit: contain;
}
.license-side .placeholder {
    color: #999;
    font-size: 14px;
}
.license-side .placeholder i {
    font-size: 48px;
    display: block;
    margin-bottom: 10px;
    color: #ddd;
}
.license-side-label {
    font-weight: 600;
    color: #1a237e;
    margin-bottom: 10px;
}
.file-input-wrapper {
    position: relative;
    overflow: hidden;
    display: inline-block;
}
.file-input-wrapper input[type=file] {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}
.file-input-wrapper .btn-upload {
    display: inline-block;
    padding: 8px 20px;
    background: #1a237e;
    color: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    margin-top: 10px;
}
.file-input-wrapper .btn-upload:hover {
    background: #0d47a1;
}
</style>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: #1a237e;">
            <i class="fas fa-id-card"></i> License Verification
        </h1>
        <a href="../dashboard.php" style="padding: 10px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <?php if ($user['license_verified'] == 'verified'): ?>
        <!-- ✅ Already Verified -->
        <div style="background: #e8f5e9; padding: 30px; border-radius: 12px; text-align: center;">
            <i class="fas fa-check-circle" style="font-size: 64px; color: #2e7d32;"></i>
            <h2 style="color: #2e7d32;">✅ License Verified!</h2>
            <p style="color: #666;">Your driver license has been verified. You can now use all features.</p>
        </div>
        
    <?php elseif ($existing_request && $existing_request['status'] == 'pending'): ?>
        <!-- ⏳ Pending Verification -->
        <div style="background: #fff3e0; padding: 30px; border-radius: 12px; text-align: center; border-left: 4px solid #f9a825;">
            <i class="fas fa-clock" style="font-size: 64px; color: #e65100;"></i>
            <h2 style="color: #e65100;">⏳ Pending Verification</h2>
            <p style="color: #666;">Your license is currently being reviewed by the admin. You'll be notified once verified.</p>
            <p style="color: #999; font-size: 14px;">Submitted: <?php echo date('M d, Y h:i A', strtotime($existing_request['created_at'])); ?></p>
        </div>
        
    <?php elseif ($user['license_verified'] == 'rejected'): ?>
        <!-- ❌ Rejected -->
        <div style="background: #ffebee; padding: 30px; border-radius: 12px; text-align: center; border-left: 4px solid #c62828;">
            <i class="fas fa-times-circle" style="font-size: 64px; color: #c62828;"></i>
            <h2 style="color: #c62828;">❌ License Rejected</h2>
            <p style="color: #666;">Your license verification was rejected. Please upload clear images and try again.</p>
            <a href="license_upload.php" style="display: inline-block; margin-top: 10px; padding: 10px 25px; background: #1a237e; color: white; border-radius: 8px; text-decoration: none;">
                <i class="fas fa-upload"></i> Re-upload License
            </a>
        </div>
        
    <?php else: ?>
        <!-- 📝 Submit License Form -->
        <div style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h3><i class="fas fa-upload"></i> Submit Your Driver License</h3>
            <p style="color: #666;">Upload clear images of your driver license for verification. Both front and back in one page.</p>
            
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

            <form method="POST" enctype="multipart/form-data">
                <!-- Personal Information -->
                <div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #1a237e;">
                        <i class="fas fa-user"></i> Personal Information
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">Email *</label>
                            <input type="email" name="email" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">Phone *</label>
                            <input type="text" name="phone" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">Driver License Number *</label>
                            <input type="text" name="driver_license" class="form-control" required 
                                   value="<?php echo htmlspecialchars($user['driver_license']); ?>" 
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                            <small style="color: #666;">Format: AA-123456 (e.g., AA-123456)</small>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">Issue Date *</label>
                            <input type="date" name="license_issue_date" class="form-control" required 
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>
                        
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">Expiry Date *</label>
                            <input type="date" name="license_expiry_date" class="form-control" required 
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>
                    </div>
                </div>

                <!-- License Images - Front & Back Side by Side -->
                <div style="background: #f0f4ff; padding: 20px; border-radius: 8px; border: 2px solid #1a237e; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #1a237e;">
                        <i class="fas fa-image"></i> License Images
                    </h4>
                    <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                        Upload clear images of both sides of your license.
                    </p>
                    
                    <div class="license-preview-container">
                        <!-- Front Side -->
                        <div class="license-side" id="front_preview">
                            <div class="license-side-label">📄 Front Side</div>
                            <div class="placeholder" id="front_placeholder">
                                <i class="fas fa-id-card"></i>
                                <span>Click to upload front image</span>
                            </div>
                            <div class="file-input-wrapper">
                                <span class="btn-upload"><i class="fas fa-upload"></i> Choose Front Image</span>
                                <input type="file" name="license_front" accept="image/*" required
                                       onchange="previewLicenseImage(event, 'front')">
                            </div>
                        </div>
                        
                        <!-- Back Side -->
                        <div class="license-side" id="back_preview">
                            <div class="license-side-label">📄 Back Side</div>
                            <div class="placeholder" id="back_placeholder">
                                <i class="fas fa-id-card"></i>
                                <span>Click to upload back image</span>
                            </div>
                            <div class="file-input-wrapper">
                                <span class="btn-upload"><i class="fas fa-upload"></i> Choose Back Image</span>
                                <input type="file" name="license_back" accept="image/*"
                                       onchange="previewLicenseImage(event, 'back')">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Buttons -->
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="submit_license" style="padding: 14px 30px; background: linear-gradient(135deg, #1a237e, #0d47a1); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 16px;">
                        <i class="fas fa-paper-plane"></i> Submit for Verification
                    </button>
                    <a href="../dashboard.php" style="padding: 14px 30px; background: #c62828; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 16px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
            
            <!-- Instructions -->
            <div style="margin-top: 20px; padding: 15px; background: #fff3e0; border-radius: 8px; border-left: 4px solid #f9a825;">
                <p style="margin: 0; color: #e65100; font-size: 14px;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Tips for successful verification:</strong><br>
                    📸 Ensure images are clear and not blurry<br>
                    🖼️ All corners of the license must be visible<br>
                    💡 Use good lighting (no flash reflection)<br>
                    📅 License must be valid (not expired)<br>
                    ⏳ Verification may take 1-2 business days
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// ✅ Preview License Image
function previewLicenseImage(event, side) {
    const file = event.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const previewDiv = document.getElementById(side + '_preview');
        const placeholder = document.getElementById(side + '_placeholder');
        
        // Create or update image
        let img = previewDiv.querySelector('img');
        if (!img) {
            img = document.createElement('img');
            previewDiv.insertBefore(img, placeholder);
        }
        img.src = e.target.result;
        img.style.maxWidth = '100%';
        img.style.maxHeight = '200px';
        img.style.borderRadius = '8px';
        img.style.objectFit = 'contain';
        
        // Hide placeholder
        placeholder.style.display = 'none';
        
        // Update border color
        previewDiv.style.borderColor = '#2e7d32';
        previewDiv.style.borderStyle = 'solid';
    };
    reader.readAsDataURL(file);
}
</script>

<?php include '../includes/footer.php'; ?>