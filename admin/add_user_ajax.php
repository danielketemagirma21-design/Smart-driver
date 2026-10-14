<?php
session_start();
include '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get form data
$full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
$email = mysqli_real_escape_string($conn, $_POST['email']);
$phone = mysqli_real_escape_string($conn, $_POST['phone']);
$driver_license = mysqli_real_escape_string($conn, $_POST['driver_license']);
$vehicle_plate = mysqli_real_escape_string($conn, $_POST['vehicle_plate']);
$city = mysqli_real_escape_string($conn, $_POST['city']);
$password = $_POST['password'];

// Validate required fields
if (empty($full_name) || empty($email) || empty($phone) || empty($driver_license) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit();
}

// Check if email already exists
$check = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
if (mysqli_num_rows($check) > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered!']);
    exit();
}

// Check if driver license already exists
$check = mysqli_query($conn, "SELECT * FROM users WHERE driver_license = '$driver_license'");
if (mysqli_num_rows($check) > 0) {
    echo json_encode(['success' => false, 'message' => 'Driver license already registered!']);
    exit();
}

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$sql = "INSERT INTO users (full_name, email, phone, driver_license, vehicle_plate, city, password_hash, driver_score, status) 
        VALUES ('$full_name', '$email', '$phone', '$driver_license', '$vehicle_plate', '$city', '$hashed_password', 100, 'active')";

if (mysqli_query($conn, $sql)) {
    $user_id = mysqli_insert_id($conn);
    
    // Log activity
    mysqli_query($conn, "INSERT INTO activity_logs (admin_id, action, description, ip_address) 
                        VALUES (" . $_SESSION['admin_id'] . ", 'add_user', 'Added new user: $email', '" . $_SERVER['REMOTE_ADDR'] . "')");
    
    // Send welcome notification
    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type) 
                        VALUES ($user_id, 'Welcome to Smart Driver!', 'Your account has been created by the admin. Drive safely!', 'system')");
    
    echo json_encode(['success' => true, 'message' => 'User added successfully! Password: ' . $password]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add user: ' . mysqli_error($conn)]);
}
?>