<?php
session_start();

// Log activity
if (isset($_SESSION['admin_id'])) {
    include 'config/database.php';
    $admin_id = $_SESSION['admin_id'];
    mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                        VALUES ($admin_id, 'logout', 'Admin logged out', '" . $_SERVER['REMOTE_ADDR'] . "')");
}

// Destroy session
session_destroy();

// Redirect to home
header('Location: index.php');
exit();
?>