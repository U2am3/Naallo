<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Get unread notifications count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = $stmt->fetch()['count'];

    // Get latest 5 notifications
    $stmt = $pdo->prepare("
        SELECT n.*, t.title as type_title 
        FROM notifications n 
        LEFT JOIN notification_types t ON n.type = t.type 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'notifications' => $notifications
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error fetching notifications: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching notifications']);
}
