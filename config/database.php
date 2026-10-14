<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smart_driver_db');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8");

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to escape strings
function escape_string($string) {
    global $conn;
    return mysqli_real_escape_string($conn, $string);
}

// Function to get user by ID
function get_user($id) {
    global $conn;
    $sql = "SELECT * FROM users WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result);
}

// Function to get driver score
function get_driver_score($user_id) {
    global $conn;
    $sql = "SELECT driver_score FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['driver_score'] ?? 0;
}

// Function to get violation count
function get_violation_count($user_id) {
    global $conn;
    $sql = "SELECT COUNT(*) as count FROM violations WHERE user_id = $user_id";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

// Function to get score color
function getScoreColor($score) {
    if ($score >= 90) return '#2e7d32';
    if ($score >= 70) return '#f9a825';
    if ($score >= 50) return '#f57c00';
    return '#c62828';
}

// Function to get score label
function getScoreLabel($score) {
    if ($score >= 90) return '🌟 Excellent Driver';
    if ($score >= 70) return '👍 Good Driver';
    if ($score >= 50) return '⚠️ Fair Driver';
    if ($score >= 30) return '⚠️ Risky Driver';
    return '🚫 Dangerous Driver';
}
?>