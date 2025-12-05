<?php
// /admin/ajax/notifications.php
// Handle notification operations

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/../config/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

function respond($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

try {
    switch ($action) {
        case 'follow_author':
            // Create a notification when user follows an author
            if (!$userId) {
                respond(false, 'Not authenticated');
            }
            
            $authorId = (int)($_POST['author_id'] ?? 0);
            if (!$authorId || $authorId === $userId) {
                respond(false, 'Invalid author');
            }
            
            // Check if already following
            $check = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND author_id = ?");
            $check->execute([$userId, $authorId]);
            if ($check->fetch()) {
                respond(false, 'Already following this author');
            }
            
            // Create follow record
            $insert = $pdo->prepare("INSERT INTO follows (follower_id, author_id, created_at) VALUES (?, ?, NOW())");
            $insert->execute([$userId, $authorId]);
            
            // Create notification for author
            $getUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $getUser->execute([$userId]);
            $follower = $getUser->fetch();
            
            $notification = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, created_at)
                VALUES (?, 'follow', 'New Follower', ?, NOW())
            ");
            $notification->execute([
                $authorId,
                "User {$follower['username']} started following you!"
            ]);
            
            respond(true, 'Now following author!');
            break;

        case 'unfollow_author':
            // Remove follow and notification
            if (!$userId) {
                respond(false, 'Not authenticated');
            }
            
            $authorId = (int)($_POST['author_id'] ?? 0);
            if (!$authorId) {
                respond(false, 'Invalid author');
            }
            
            $stmt = $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND author_id = ?");
            $stmt->execute([$userId, $authorId]);
            
            respond(true, 'Unfollowed author');
            break;

        case 'get_notifications':
            // Get user's notifications
            if (!$userId) {
                respond(false, 'Not authenticated');
            }
            
            $limit = (int)($_GET['limit'] ?? 20);
            $stmt = $pdo->prepare("
                SELECT id, type, title, message, is_read, created_at
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            $notifications = $stmt->fetchAll();
            
            respond(true, 'Notifications retrieved', ['notifications' => $notifications]);
            break;

        case 'mark_notification_read':
            // Mark notification as read
            if (!$userId) {
                respond(false, 'Not authenticated');
            }
            
            $notificationId = (int)($_POST['notification_id'] ?? 0);
            if (!$notificationId) {
                respond(false, 'Invalid notification ID');
            }
            
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);
            
            respond(true, 'Notification marked as read');
            break;

        case 'get_unread_count':
            // Get count of unread notifications
            if (!$userId) {
                respond(false, 'Not authenticated', ['count' => 0]);
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            $count = $stmt->fetch()['cnt'];
            
            respond(true, 'Count retrieved', ['count' => $count]);
            break;

        default:
            respond(false, 'Unknown action');
    }
} catch (Exception $e) {
    respond(false, 'Error: ' . $e->getMessage());
}
?>
