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

// Get car brands for dropdown
$car_brands = mysqli_query($conn, "SELECT * FROM car_brands ORDER BY brand_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    // Sanitize inputs
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $driver_license = mysqli_real_escape_string($conn, $_POST['driver_license']);
    $vehicle_plate = mysqli_real_escape_string($conn, $_POST['vehicle_plate']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $car_model = mysqli_real_escape_string($conn, $_POST['car_model']);
    $car_color = mysqli_real_escape_string($conn, $_POST['car_color']);
    $car_year = (int)$_POST['car_year'];
    $password = $_POST['password'];
    
    // If "Other" is selected, use the custom input
    if ($car_model == 'other') {
        $car_model = mysqli_real_escape_string($conn, $_POST['car_model_other']);
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if email exists
    $check = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
    if (mysqli_num_rows($check) > 0) {
        $error = "❌ Email already registered!";
    } else {
        // Handle car image upload
        $car_image = '';
        if (isset($_FILES['car_image']) && $_FILES['car_image']['error'] == 0) {
            $target_dir = "../assets/uploads/cars/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['car_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $image_name = time() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', basename($_FILES['car_image']['name']));
                $target_file = $target_dir . $image_name;
                
                // Compress image if too large
                $max_size = 2 * 1024 * 1024; // 2MB
                if ($_FILES['car_image']['size'] > $max_size) {
                    // Simple resize using GD (basic implementation)
                    $temp_image = imagecreatefromstring(file_get_contents($_FILES['car_image']['tmp_name']));
                    if ($temp_image) {
                        $width = imagesx($temp_image);
                        $height = imagesy($temp_image);
                        $new_width = 800;
                        $new_height = intval($height * ($new_width / $width));
                        $resized = imagecreatetruecolor($new_width, $new_height);
                        imagecopyresampled($resized, $temp_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                        imagejpeg($resized, $target_file, 80);
                        imagedestroy($temp_image);
                        imagedestroy($resized);
                        $car_image = $image_name;
                    } else {
                        // Fallback: just move the file
                        if (move_uploaded_file($_FILES['car_image']['tmp_name'], $target_file)) {
                            $car_image = $image_name;
                        }
                    }
                } else {
                    if (move_uploaded_file($_FILES['car_image']['tmp_name'], $target_file)) {
                        $car_image = $image_name;
                    }
                }
            } else {
                $error = "❌ Invalid image format. Please upload JPG, PNG, or GIF.";
            }
        }
        
        if (empty($error)) {
            // Insert user with car details
            $sql = "INSERT INTO users (
                        full_name, email, phone, driver_license, vehicle_plate, city, 
                        car_model, car_color, car_year, car_image, 
                        password_hash, driver_score, status
                    ) VALUES (
                        '$full_name', '$email', '$phone', '$driver_license', '$vehicle_plate', '$city', 
                        '$car_model', '$car_color', $car_year, '$car_image', 
                        '$hashed_password', 100, 'active'
                    )";
            
            if (mysqli_query($conn, $sql)) {
                $user_id = mysqli_insert_id($conn);
                
                // Log activity
                mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                                    VALUES (" . $_SESSION['admin_id'] . ", 'add_user', 'Added new user: $email with car: $car_model', '" . $_SERVER['REMOTE_ADDR'] . "')");
                
                // Send welcome notification
                mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type) 
                                    VALUES ($user_id, 'Welcome to Smart Driver!', 
                                    'Your account has been created by the admin. Car: $car_model $car_color. Drive safely!', 'system')");
                
                $success = "✅ User added successfully!<br>";
                $success .= "👤 Name: <strong>$full_name</strong><br>";
                $success .= "🚗 Car: <strong>$car_model $car_color</strong><br>";
                $success .= "🔑 Password: <strong>$password</strong><br>";
                $success .= "📸 Image: " . ($car_image ? "✅ Uploaded" : "❌ Not uploaded");
            } else {
                $error = "❌ Failed to add user: " . mysqli_error($conn);
            }
        }
    }
}

$page_title = 'Add User - Smart Driver Admin';
$base_url = '../';
include '../includes/header.php';
?>

<style>
.form-section {
    background: #f8f9ff;
    padding: 20px;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    margin-bottom: 20px;
}
.form-section-title {
    margin: 0 0 15px 0;
    color: #1a237e;
    font-size: 16px;
    font-weight: 700;
}
.image-preview {
    width: 150px;
    height: 150px;
    border: 2px dashed #ccc;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 10px;
    overflow: hidden;
    background: #fafafa;
}
.image-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: cover;
}
.color-option {
    display: inline-block;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    border: 2px solid #ddd;
    cursor: pointer;
    margin: 2px;
    transition: transform 0.2s;
}
.color-option:hover {
    transform: scale(1.2);
}
.color-option.selected {
    border-color: #1a237e;
    box-shadow: 0 0 0 2px #1a237e;
}
</style>

<div class="admin-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <h2><i class="fas fa-car"></i> Smart Driver</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="add_user.php" class="active"><i class="fas fa-user-plus"></i> Add User</a></li>
            <li><a href="violations.php"><i class="fas fa-gavel"></i> Violations</a></li>
            <li><a href="fuel.php"><i class="fas fa-gas-pump"></i> Fuel</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="activity.php"><i class="fas fa-clock"></i> Activity</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header>
            <h1><i class="fas fa-user-plus"></i> Add New User</h1>
            <a href="users.php" style="padding: 10px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </header>

        <div style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <?php if ($success): ?>
                <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; border: 1px solid #a5d6a7; color: #2e7d32; margin-bottom: 20px;">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="background: #ffebee; padding: 15px; border-radius: 8px; border: 1px solid #ef9a9a; color: #c62828; margin-bottom: 20px;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="add_user" value="1">
                
                <!-- Personal Information -->
                <div class="form-section">
                    <h4 class="form-section-title">
                        <i class="fas fa-user"></i> Personal Information
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                Full Name <span style="color: #c62828;">*</span>
                            </label>
                            <input type="text" name="full_name" class="form-control" required 
                                   placeholder="Enter full name"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                Email <span style="color: #c62828;">*</span>
                            </label>
                            <input type="email" name="email" class="form-control" required 
                                   placeholder="Enter email address"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                        </div>
                        
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                Phone <span style="color: #c62828;">*</span>
                            </label>
                            <input type="text" name="phone" class="form-control" required 
                                   placeholder="e.g., +251911234567"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                        </div>
                        
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                City
                            </label>
                            <input type="text" name="city" class="form-control" 
                                   placeholder="e.g., Addis Ababa"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                        </div>
                        
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                Driver License <span style="color: #c62828;">*</span>
                            </label>
                            <input type="text" name="driver_license" class="form-control" required 
                                   placeholder="e.g., AA-123456"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                        </div>
                        
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                Vehicle Plate <span style="color: #c62828;">*</span>
                            </label>
                            <input type="text" name="vehicle_plate" class="form-control" required 
                                   placeholder="e.g., AA 1234"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                Password <span style="color: #c62828;">*</span>
                            </label>
                            <input type="text" name="password" class="form-control" required 
                                   placeholder="Enter temporary password (min 6 characters)"
                                   minlength="6"
                                   style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                            <small style="color: #666;">Password will be automatically hashed for security</small>
                        </div>
                    </div>
                </div>

                <!-- Car Information -->
                <div class="form-section" style="border-color: #1a237e; background: #f0f4ff;">
                    <h4 class="form-section-title" style="color: #1a237e;">
                        <i class="fas fa-car"></i> Car Information
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                Car Brand/Model <span style="color: #c62828;">*</span>
                            </label>
                            <select name="car_model" id="car_model" class="form-control" required 
                                    style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                                <option value="">-- Select Car Model --</option>
                                <?php while ($brand = mysqli_fetch_assoc($car_brands)): ?>
                                    <option value="<?php echo $brand['brand_name']; ?>"><?php echo $brand['brand_name']; ?></option>
                                <?php endwhile; ?>
                                <option value="other">🚗 Other (Specify)</option>
                            </select>
                            <input type="text" name="car_model_other" id="car_model_other" 
                                   class="form-control" 
                                   placeholder="Enter car model e.g., Toyota Corolla"
                                   style="display: none; width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px; margin-top: 5px;">
                        </div>
                        
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                Car Color <span style="color: #c62828;">*</span>
                            </label>
                            <select name="car_color" id="car_color" class="form-control" required 
                                    style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                                <option value="">-- Select Color --</option>
                                <option value="Black">⬛ Black</option>
                                <option value="White">⬜ White</option>
                                <option value="Silver">🔘 Silver</option>
                                <option value="Red">🟥 Red</option>
                                <option value="Blue">🟦 Blue</option>
                                <option value="Green">🟩 Green</option>
                                <option value="Yellow">🟨 Yellow</option>
                                <option value="Orange">🟧 Orange</option>
                                <option value="Brown">🟫 Brown</option>
                                <option value="Gray">⬜ Gray</option>
                                <option value="Gold">✨ Gold</option>
                                <option value="Purple">🟪 Purple</option>
                                <option value="Pink">🟪 Pink</option>
                                <option value="Maroon">🟫 Maroon</option>
                                <option value="Navy">🔵 Navy</option>
                                <option value="Teal">🟩 Teal</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                Car Year
                            </label>
                            <select name="car_year" class="form-control" 
                                    style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                                <option value="">-- Select Year --</option>
                                <?php for ($year = date('Y'); $year >= 1990; $year--): ?>
                                    <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                                Car Image
                            </label>
                            <input type="file" name="car_image" id="car_image" 
                                   accept="image/jpeg,image/png,image/gif,image/webp" 
                                   class="form-control" 
                                   onchange="previewImage(event)"
                                   style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                            <small style="color: #666;">Upload car photo (JPG, PNG, GIF) - Max 2MB</small>
                            <div class="image-preview" id="image_preview">
                                <span style="color: #ccc; font-size: 14px;">No image selected</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" style="padding: 14px 40px; background: linear-gradient(135deg, #1a237e, #0d47a1); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 16px;">
                        <i class="fas fa-save"></i> Add User
                    </button>
                    <a href="users.php" style="padding: 14px 30px; background: #c62828; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 16px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="reset" style="padding: 14px 30px; background: #666; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 16px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show/hide other car model input
document.getElementById('car_model').addEventListener('change', function() {
    const otherInput = document.getElementById('car_model_other');
    if (this.value === 'other') {
        otherInput.style.display = 'block';
        otherInput.required = true;
    } else {
        otherInput.style.display = 'none';
        otherInput.required = false;
    }
});

// Image preview
function previewImage(event) {
    const preview = document.getElementById('image_preview');
    const file = event.target.files[0];
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Car preview">`;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '<span style="color: #ccc; font-size: 14px;">No image selected</span>';
    }
}

// Color selection visual feedback
document.getElementById('car_color').addEventListener('change', function() {
    // You can add color preview here if needed
});
</script>

<?php include '../includes/footer.php'; ?>