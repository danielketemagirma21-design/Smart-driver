<?php
session_start();
include 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    // Get admin record
    $sql = "SELECT * FROM admins WHERE username = '$username' AND status = 'active'";
    $result = mysqli_query($conn, $sql);
    $admin = mysqli_fetch_assoc($result);
    
    if ($admin) {
        $stored_password = $admin['password_hash'];
        
        // 🔐 CHECK BOTH WAYS
        $login_success = false;
        
        // Check 1: Is it hashed? Use password_verify
        if (strpos($stored_password, '$2y$') === 0) {
            if (password_verify($password, $stored_password)) {
                $login_success = true;
            }
        } 
        // Check 2: Is it plain text? Direct comparison
        else {
            if ($password === $stored_password) {
                $login_success = true;
                
                // Upgrade plain text to hash automatically!
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE admins SET password_hash = '$hashed' WHERE id = {$admin['id']}");
            }
        }
        
        if ($login_success) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            
            mysqli_query($conn, "UPDATE admins SET last_login = NOW() WHERE id = " . $admin['id']);
            
            mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                                VALUES (" . $admin['id'] . ", 'login', 'Admin logged in', '" . $_SERVER['REMOTE_ADDR'] . "')");
            
            header('Location: admin/dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password!';
        }
    } else {
        $error = 'Invalid username or password!';
    }
}

$page_title = 'Admin Login - Smart Driver';
$base_url = '';
include 'includes/header.php';
?>

<div class="container">
    <div class="login-container">
        <h2><i class="fas fa-user-shield"></i> Admin Login</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 16px;">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>