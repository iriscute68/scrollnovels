<?php
/**
 * api/comment-like-notify.php
 * Send notification when someone likes a comment
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$user_id = $_SESSION['user_id'];

// Validate input
$comment_id = (int)($input['comment_id'] ?? 0);
$comment_author_id = (int)($input['comment_author_id'] ?? 0);

if (!$comment_id || !$comment_author_id || $comment_author_id == $user_id) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    // Check if notification already sent (prevent duplicates)
    $stmt = $pdo->prepare("
        SELECT id FROM notifications 
        WHERE user_id = ? 
        AND actor_id = ? 
        AND type = 'comment_like'
        AND related_id = ?
        AND DATE(created_at) = CURDATE()
        LIMIT 1
    ");
    $stmt->execute([$comment_author_id, $user_id, $comment_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Already liked today']);
        exit;
    }

    // Get commenter username
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $liker = $stmt->fetch(PDO::FETCH_ASSOC);
    $likerName = $liker['username'] ?? 'Someone';

    // Create notification
    $notifMsg = "❤️ {$likerName} liked your comment";
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, actor_id, type, subtype, message, related_id, url, is_read, created_at) 
        VALUES (?, ?, 'comment_like', 'like', ?, ?, ?, 0, NOW())
    ");
    
    $stmt->execute([
        $comment_author_id,
        $user_id,
        $notifMsg,
        $comment_id,
        '/pages/notification.php?type=comment_like'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Notification sent',
        'notification_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    http_response_code(500);
}
?>
