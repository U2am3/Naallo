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

// Get notification ID from POST data
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = isset($data['notification_id']) ? $data['notification_id'] : null;

if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Notification ID is required']);
    exit();
}

try {
    // Verify that the notification belongs to the current user
    $stmt = $pdo->prepare("SELECT user_id FROM notifications WHERE notification_id = ?");
    $stmt->execute([$notification_id]);
    $notification = $stmt->fetch();

    if (!$notification || $notification['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }

    // Update notification status
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
    $stmt->execute([$notification_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error marking notification as read: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating notification']);
}
