<?php
session_start();
include '../config/database.php';

// ✅ Check if driver is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user = get_user($user_id);

// ✅ Handle License Image Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_license'])) {
    $target_dir = "../assets/uploads/licenses/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $license_image = '';
    
    if (isset($_FILES['license_image']) && $_FILES['license_image']['error'] == 0) {
        $file_extension = strtolower(pathinfo($_FILES['license_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed)) {
            $license_image = time() . '_' . $user_id . '_license.' . $file_extension;
            move_uploaded_file($_FILES['license_image']['tmp_name'], $target_dir . $license_image);
            
            mysqli_query($conn, "UPDATE users SET license_image = '$license_image' WHERE id = $user_id");
            
            $check = mysqli_query($conn, "SELECT id FROM license_verification_requests WHERE user_id = $user_id AND status IN ('pending', 'verified')");
            if (mysqli_num_rows($check) > 0) {
                $request = mysqli_fetch_assoc($check);
                mysqli_query($conn, "UPDATE license_verification_requests SET license_front_image = '$license_image' WHERE id = {$request['id']}");
            } else {
                mysqli_query($conn, "INSERT INTO license_verification_requests (user_id, full_name, email, phone, driver_license, license_front_image, status) 
                                    VALUES ($user_id, '{$user['full_name']}', '{$user['email']}', '{$user['phone']}', '{$user['driver_license']}', '$license_image', 'pending')");
            }
            
            $_SESSION['license_upload_success'] = "✅ License image uploaded successfully! Waiting for admin verification.";
            header('Location: profile.php');
            exit();
        } else {
            $license_error = "❌ Invalid file format. Please upload JPG, PNG, or GIF.";
        }
    } else {
        $license_error = "❌ Please select a file to upload.";
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile']) && !isset($_POST['change_password']) && !isset($_POST['upload_license'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $vehicle_plate = mysqli_real_escape_string($conn, $_POST['vehicle_plate']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    
    $sql = "UPDATE users SET 
            full_name = '$full_name',
            phone = '$phone',
            vehicle_plate = '$vehicle_plate',
            city = '$city'
            WHERE id = $user_id";
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['full_name'] = $full_name;
        $success = "✅ Profile updated successfully!";
        $user = get_user($user_id);
    } else {
        $error = "❌ Failed to update profile.";
    }
}

// Change password
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if (password_verify($current, $user['password_hash'])) {
        if ($new == $confirm) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE users SET password_hash = '$hash' WHERE id = $user_id");
            $pass_success = "✅ Password changed successfully!";
        } else {
            $pass_error = "❌ New passwords do not match!";
        }
    } else {
        $pass_error = "❌ Current password is incorrect!";
    }
}

$license_status = $user['license_verified'] ?? 'pending';
$license_image = $user['license_image'] ?? '';

$license_request = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM license_verification_requests 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT 1
"));

$page_title = 'My Profile - Smart Driver';
$base_url = '../';
include '../includes/header.php';
?>

<style>
.license-upload-area {
    border: 2px dashed #1a237e;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    background: #f8f9ff;
    transition: all 0.3s;
    cursor: pointer;
}
.license-upload-area:hover {
    background: #f0f4ff;
    border-color: #0d47a1;
}
.license-upload-area .icon {
    font-size: 48px;
    color: #1a237e;
    margin-bottom: 10px;
}
.license-preview {
    max-width: 100%;
    max-height: 200px;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    margin: 10px 0;
}
.status-badge {
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
}
.status-verified {
    background: #e8f5e9;
    color: #2e7d32;
}
.status-pending {
    background: #fff3e0;
    color: #e65100;
}
.status-rejected {
    background: #ffebee;
    color: #c62828;
}
</style>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: #1a237e;">
            <i class="fas fa-user"></i> My Profile
        </h1>
        <a href="../dashboard.php" style="padding: 10px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- License Upload Section -->
    <div style="background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-left: 4px solid #1a237e;">
        <h3 style="color: #1a237e;">
            <i class="fas fa-id-card"></i> Driver License
        </h3>
        
        <?php if (isset($_SESSION['license_upload_success'])): ?>
            <div style="background: #e8f5e9; padding: 12px; border-radius: 8px; color: #2e7d32; margin: 10px 0;">
                <?php echo $_SESSION['license_upload_success']; unset($_SESSION['license_upload_success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($license_error)): ?>
            <div style="background: #ffebee; padding: 12px; border-radius: 8px; color: #c62828; margin: 10px 0;"><?php echo $license_error; ?></div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">
            <div>
                <p><strong>License Status:</strong> 
                    <span class="status-badge status-<?php echo $license_status; ?>">
                        <?php 
                            $status_labels = [
                                'verified' => '✅ Verified',
                                'pending' => '⏳ Pending',
                                'rejected' => '❌ Rejected'
                            ];
                            echo $status_labels[$license_status] ?? '⏳ Pending';
                        ?>
                    </span>
                </p>
                <?php if ($license_request): ?>
                    <p><small style="color: #666;">Submitted: <?php echo date('M d, Y', strtotime($license_request['created_at'])); ?></small></p>
                <?php endif; ?>
                <?php if ($license_status == 'rejected' && $license_request): ?>
                    <p style="color: #c62828; font-size: 14px;">
                        <i class="fas fa-info-circle"></i> <?php echo $license_request['rejection_reason'] ?? 'Please upload a clear image.'; ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center;">
                <?php if ($license_image): ?>
                    <img src="../assets/uploads/licenses/<?php echo $license_image; ?>" 
                         alt="License" 
                         style="max-width: 100%; max-height: 150px; border-radius: 8px; border: 2px solid #e0e0e0;">
                <?php else: ?>
                    <div class="license-upload-area" id="licenseUploadArea">
                        <div class="icon">📄</div>
                        <p style="color: #666;">No license uploaded yet</p>
                        <p style="font-size: 13px; color: #999;">Click to upload your driver license</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" style="margin-top: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <input type="file" name="license_image" accept="image/*" class="form-control" 
                       style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                <small style="color: #666;">Upload JPG, PNG, or GIF (Max 5MB)</small>
            </div>
            <button type="submit" name="upload_license" style="padding: 12px 25px; background: #1a237e; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-upload"></i> Upload License
            </button>
        </form>
        
        <?php if ($license_status == 'pending'): ?>
            <div style="margin-top: 10px; padding: 12px; background: #fff3e0; border-radius: 8px;">
                <p style="margin: 0; color: #e65100; font-size: 14px;">
                    <i class="fas fa-clock"></i> Your license is pending verification. You will be notified once verified.
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Profile Info & Change Password -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h3><i class="fas fa-user-edit"></i> Edit Profile</h3>
            
            <?php if (isset($success)): ?>
                <div style="background: #e8f5e9; padding: 12px; border-radius: 8px; color: #2e7d32; margin: 10px 0;"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div style="background: #ffebee; padding: 12px; border-radius: 8px; color: #c62828; margin: 10px 0;"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo $user['full_name']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email (cannot be changed)</label>
                    <input type="email" class="form-control" value="<?php echo $user['email']; ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo $user['phone']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Vehicle Plate</label>
                    <input type="text" name="vehicle_plate" class="form-control" value="<?php echo $user['vehicle_plate'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" class="form-control" value="<?php echo $user['city'] ?? ''; ?>">
                </div>
                
                <button type="submit" name="update_profile" style="padding: 12px 30px; background: #1a237e; color: white; border: none; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-save"></i> Update Profile
                </button>
            </form>
        </div>

        <div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h3><i class="fas fa-lock"></i> Change Password</h3>
            
            <?php if (isset($pass_success)): ?>
                <div style="background: #e8f5e9; padding: 12px; border-radius: 8px; color: #2e7d32; margin: 10px 0;"><?php echo $pass_success; ?></div>
            <?php endif; ?>
            <?php if (isset($pass_error)): ?>
                <div style="background: #ffebee; padding: 12px; border-radius: 8px; color: #c62828; margin: 10px 0;"><?php echo $pass_error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" name="change_password" style="padding: 12px 30px; background: #f9a825; color: white; border: none; border-radius: 8px; cursor: pointer;">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('licenseUploadArea');
    if (uploadArea) {
        uploadArea.addEventListener('click', function() {
            document.querySelector('input[name="license_image"]').click();
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>