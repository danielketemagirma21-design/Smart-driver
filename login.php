<?php
session_start();
include 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    // Check if user exists and is active
    $sql = "SELECT * FROM users WHERE email = '$email' AND status = 'active'";
    $result = mysqli_query($conn, $sql);
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        $stored_password = $user['password_hash'];
        $authenticated = false;
        
        // ✅ CHECK BOTH PASSWORD TYPES
        // Check 1: Is it hashed? (starts with $2y$)
        if (strpos($stored_password, '$2y$') === 0) {
            // It's a hashed password - use password_verify
            if (password_verify($password, $stored_password)) {
                $authenticated = true;
            }
        } 
        // Check 2: Is it plain text? Direct comparison
        else {
            if ($password === $stored_password) {
                $authenticated = true;
                
                // 🔥 AUTOMATICALLY UPGRADE TO HASH (Security Improvement)
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE users SET password_hash = '$new_hash' WHERE id = {$user['id']}");
            }
        }
        
        // ✅ LOGIN SUCCESSFUL
        if ($authenticated) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['driver_score'] = $user['driver_score'];
            
            // Update last login time (if you have this column)
            // mysqli_query($conn, "UPDATE users SET last_login = NOW() WHERE id = {$user['id']}");
            
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "❌ Invalid password! Please try again.";
        }
    } else {
        $error = "❌ Email not found or account is inactive!";
    }
}

$page_title = 'Driver Login - Smart Driver';
$base_url = '';
include 'includes/header.php';
?>

<div class="container">
    <div style="max-width: 400px; margin: 80px auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h2 style="text-align: center; color: #1a237e;">
            <i class="fas fa-car" style="color: #ffd54f;"></i> Driver Login
        </h2>
        
        <?php if ($error): ?>
            <div style="background: #ffebee; padding: 12px 15px; border-radius: 8px; color: #c62828; margin: 15px 0; border-left: 4px solid #c62828;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                    <i class="fas fa-envelope"></i> Email Address
                </label>
                <input type="email" name="email" class="form-control" required 
                       placeholder="Enter your email" 
                       style="width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
            </div>
            
            <div class="form-group">
                <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                    <i class="fas fa-lock"></i> Password
                </label>
                <input type="password" name="password" class="form-control" required 
                       placeholder="Enter your password" 
                       style="width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
            </div>
            
            <button type="submit" style="width: 100%; padding: 14px; background: linear-gradient(135deg, #1a237e, #0d47a1); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.3s;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
            <p style="color: #666; font-size: 14px;">
                Don't have an account? 
                <a href="register.php" style="color: #1a237e; text-decoration: none; font-weight: 600;">Register Here</a>
            </p>
            <p style="margin-top: 10px;">
                <a href="index.php" style="color: #666; text-decoration: none; font-size: 14px;">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>