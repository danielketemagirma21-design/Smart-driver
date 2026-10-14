<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    session_start();
    include '../config/database.php';
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $report_type = escape_string($_POST['report_type']);
    $location = escape_string($_POST['location']);
    $description = escape_string($_POST['description'] ?? '');
    $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    
    $sql = "INSERT INTO road_reports (user_id, report_type, location, description, latitude, longitude) 
            VALUES ($user_id, '$report_type', '$location', '$description', $latitude, $longitude)";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Report submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit report']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>