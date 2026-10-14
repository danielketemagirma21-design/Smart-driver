<?php
header('Content-Type: application/json');

session_start();
include '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "
    SELECT v.*, r.rule_name 
    FROM violations v 
    JOIN traffic_rules r ON v.rule_id = r.id 
    WHERE v.user_id = $user_id 
    ORDER BY v.created_at DESC 
    LIMIT 50
";

$result = mysqli_query($conn, $sql);
$violations = [];

while ($row = mysqli_fetch_assoc($result)) {
    $violations[] = [
        'id' => $row['id'],
        'rule_name' => $row['rule_name'],
        'violation_date' => $row['violation_date'],
        'location' => $row['location'],
        'fine_amount' => $row['fine_amount'],
        'points_deducted' => $row['points_deducted'],
        'status' => $row['status'],
        'paid_status' => $row['paid_status']
    ];
}

echo json_encode(['success' => true, 'data' => $violations]);
?>