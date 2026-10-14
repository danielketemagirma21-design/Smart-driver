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
    SELECT * FROM notifications 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC 
    LIMIT 20
";

$result = mysqli_query($conn, $sql);
$notifications = [];

while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'type' => $row['type'],
        'is_read' => (bool)$row['is_read'],
        'created_at' => $row['created_at']
    ];
}

$unread_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0"))['count'];

echo json_encode([
    'success' => true, 
    'data' => $notifications,
    'unread_count' => $unread_count
]);
?>