<?php
// api/notifications/mark-read.php - Mark notification(s) as read
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notification_id = (int)($_POST['notification_id'] ?? 0);
$mark_all = (int)($_POST['mark_all'] ?? 0);

try {
    if ($mark_all) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $count = $stmt->rowCount();
        echo json_encode(['success' => true, 'message' => "Marked $count notifications as read"]);
    } else {
        if (!$notification_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Notification ID required']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
