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
    $fuel_amount = (float)$_POST['fuel_amount'];
    $distance_covered = (float)$_POST['distance_covered'];
    $cost = (float)$_POST['cost'];
    $location = escape_string($_POST['location'] ?? '');
    $fuel_type = escape_string($_POST['fuel_type'] ?? 'petrol');
    
    $efficiency = $fuel_amount > 0 ? $distance_covered / $fuel_amount : 0;
    
    $sql = "INSERT INTO fuel_records (user_id, fuel_amount, cost, distance_covered, fuel_efficiency, fuel_type, location) 
            VALUES ($user_id, $fuel_amount, $cost, $distance_covered, $efficiency, '$fuel_type', '$location')";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Fuel record added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add fuel record']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>