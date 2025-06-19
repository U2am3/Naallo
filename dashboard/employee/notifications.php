<?php
session_start();
require_once '../../config/database.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Get all notifications for the user
$stmt = $pdo->prepare("
    SELECT n.*, t.title as type_title, t.icon_class 
    FROM notifications n 
    LEFT JOIN notification_types t ON n.type = t.type 
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="page-header">
            <h2>Notifications</h2>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">No notifications yet</h4>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?> mb-3 p-3 rounded border">
                            <div class="d-flex align-items-start">
                                <div class="notification-icon me-3">
                                    <i class="fas <?php echo $notification['icon_class']; ?> text-primary"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="notification-content">
                                        <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small class="text-muted">
                                            <?php 
                                            $date = new DateTime($notification['created_at']);
                                            echo $date->format('M d, Y H:i'); 
                                            ?>
                                        </small>
                                    </div>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                <div class="notification-action">
                                    <button class="btn btn-sm btn-outline-primary mark-read-btn" data-id="<?php echo $notification['notification_id']; ?>">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
// Mark notification as read
document.addEventListener('click', function(e) {
    if (e.target.closest('.mark-read-btn')) {
        const notificationId = e.target.dataset.id;
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                notification_id: notificationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI
                const item = e.target.closest('.notification-item');
                item.classList.remove('unread');
                e.target.style.display = 'none';
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
    }
});
</script>
