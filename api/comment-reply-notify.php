<?php
/**
 * api/comment-reply-notify.php
 * Send notification when someone replies to a comment
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$user_id = $_SESSION['user_id'];

// Validate input
$comment_id = (int)($input['comment_id'] ?? 0);
$reply_comment_id = (int)($input['reply_comment_id'] ?? 0);
$comment_author_id = (int)($input['comment_author_id'] ?? 0);

if (!$comment_id || !$comment_author_id || $comment_author_id == $user_id) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    // Check if notification already sent (prevent duplicates within 5 minutes)
    $stmt = $pdo->prepare("
        SELECT id FROM notifications 
        WHERE user_id = ? 
        AND actor_id = ? 
        AND type = 'comment_reply'
        AND related_id = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        LIMIT 1
    ");
    $stmt->execute([$comment_author_id, $user_id, $comment_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Reply notification already sent']);
        exit;
    }

    // Get replier username and truncate their reply for preview
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $replier = $stmt->fetch(PDO::FETCH_ASSOC);
    $replierName = $replier['username'] ?? 'Someone';

    // Get reply comment content (optional preview)
    $replyContent = '';
    if ($reply_comment_id > 0) {
        $stmt = $pdo->prepare("SELECT content FROM comments WHERE id = ? LIMIT 1");
        $stmt->execute([$reply_comment_id]);
        $replyComment = $stmt->fetch(PDO::FETCH_ASSOC);
        $replyContent = $replyComment['content'] ?? '';
    }

    // Create notification
    $notifMsg = "💬 {$replierName} replied to your comment";
    if ($replyContent) {
        $preview = substr(strip_tags($replyContent), 0, 50);
        if (strlen($replyContent) > 50) $preview .= '...';
        $notifMsg .= ': "' . $preview . '"';
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications 
        (user_id, actor_id, type, subtype, message, related_id, url, is_read, created_at) 
        VALUES (?, ?, 'comment_reply', 'reply', ?, ?, ?, 0, NOW())
    ");
    
    $stmt->execute([
        $comment_author_id,
        $user_id,
        $notifMsg,
        $comment_id,
        '/pages/notification.php?type=comment_reply'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Reply notification sent',
        'notification_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    http_response_code(500);
}
?>
