<?php
session_start();
include '../config/database.php';

// ✅ Check if driver is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Mark notification as read
if (isset($_GET['read']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE id = $id AND user_id = $user_id");
    header('Location: notifications.php');
    exit();
}

// Mark all as read
if (isset($_GET['read_all'])) {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    header('Location: notifications.php');
    exit();
}

// Get notifications
$notifications = mysqli_query($conn, "
    SELECT * FROM notifications 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC
");

$unread_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE user_id = $user_id AND is_read = 0"))['count'] ?? 0;

$page_title = 'Notifications - Smart Driver';
$base_url = '../';
include '../includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: #1a237e;">
            <i class="fas fa-bell"></i> Notifications
            <?php if ($unread_count > 0): ?>
                <span style="background: #c62828; color: white; padding: 4px 12px; border-radius: 20px; font-size: 14px; margin-left: 10px;">
                    <?php echo $unread_count; ?> unread
                </span>
            <?php endif; ?>
        </h1>
        <div style="display: flex; gap: 10px;">
            <?php if ($unread_count > 0): ?>
                <a href="notifications.php?read_all=1" style="padding: 10px 20px; background: #2e7d32; color: white; border-radius: 8px; text-decoration: none;" onclick="return confirm('Mark all as read?')">
                    <i class="fas fa-check-double"></i> Mark All Read
                </a>
            <?php endif; ?>
            <a href="../dashboard.php" style="padding: 10px 20px; background: #0d47a1; color: white; border-radius: 8px; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
        <?php if (mysqli_num_rows($notifications) > 0): ?>
            <?php while ($n = mysqli_fetch_assoc($notifications)): ?>
                <div style="border-bottom: 1px solid #e0e0e0; padding: 15px 0; <?php echo $n['is_read'] ? 'opacity: 0.7;' : 'background: #f8f9ff; padding: 15px 20px; border-radius: 8px; margin-bottom: 10px;'; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <h4 style="margin: 0 0 5px 0; color: #1a237e;">
                                <?php if (!$n['is_read']): ?>
                                    <span style="color: #c62828;">●</span>
                                <?php endif; ?>
                                <?php echo $n['title']; ?>
                            </h4>
                            <p style="margin: 0; color: #666;"><?php echo $n['message']; ?></p>
                            <small style="color: #999;">
                                <i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?>
                                <span style="background: #e3f2fd; color: #0d47a1; padding: 2px 10px; border-radius: 20px; font-size: 11px; margin-left: 5px;"><?php echo ucfirst($n['type']); ?></span>
                            </small>
                        </div>
                        <?php if (!$n['is_read']): ?>
                            <a href="notifications.php?read&id=<?php echo $n['id']; ?>" style="padding: 5px 15px; background: #2e7d32; color: white; border-radius: 5px; text-decoration: none; font-size: 12px;">
                                <i class="fas fa-check"></i> Read
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-bell-slash" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 10px;"></i>
                <h3 style="color: #666;">No notifications</h3>
                <p style="color: #999;">You're all caught up!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>