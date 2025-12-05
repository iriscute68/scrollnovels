<?php
// api/post_comment.php - Handle blog post comments with replies and notifications
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Parse JSON input
$data = json_decode(file_get_contents('php://input'), true);
$post_id = (int)($data['post_id'] ?? 0);
$content = trim($data['content'] ?? $data['comment_text'] ?? '');
$parent_comment_id = !empty($data['parent_comment_id']) ? (int)$data['parent_comment_id'] : null;

// Validation
if (!$post_id || !$content) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing post_id or content']);
    exit;
}

try {
    // Ensure post_comments table exists with parent support
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_comments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        post_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        content LONGTEXT NOT NULL,
        parent_comment_id INT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_post_id (post_id),
        INDEX idx_user_id (user_id),
        INDEX idx_parent (parent_comment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Insert comment
    $stmt = $pdo->prepare("
        INSERT INTO post_comments (post_id, user_id, content, parent_comment_id) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$post_id, $_SESSION['user_id'], $content, $parent_comment_id]);
    $commentId = $pdo->lastInsertId();

    // Send notification if this is a reply
    if ($parent_comment_id) {
        // Get parent comment author
        $parentStmt = $pdo->prepare("SELECT user_id FROM post_comments WHERE id = ?");
        $parentStmt->execute([$parent_comment_id]);
        $parentComment = $parentStmt->fetch();
        
        if ($parentComment && $parentComment['user_id'] != $_SESSION['user_id']) {
            // Get commenter's username
            $userStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $userStmt->execute([$_SESSION['user_id']]);
            $commenter = $userStmt->fetch();
            $commenterName = $commenter['username'] ?? 'Someone';
            
            // Create notification
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT,
                    link VARCHAR(500),
                    is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_read (user_id, is_read),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                
                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, link) 
                    VALUES (?, 'comment_reply', ?, ?, ?)
                ");
                $notifStmt->execute([
                    $parentComment['user_id'],
                    $commenterName . ' replied to your comment',
                    substr($content, 0, 100) . (strlen($content) > 100 ? '...' : ''),
                    '/pages/blog.php?id=' . $post_id . '#comment-' . $commentId
                ]);
            } catch (Exception $e) {
                error_log('Notification error: ' . $e->getMessage());
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Comment posted successfully',
        'comment_id' => $commentId
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
