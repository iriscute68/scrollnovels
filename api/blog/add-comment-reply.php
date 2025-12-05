<?php
// api/blog/add-comment-reply.php - Add reply to a comment
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config/db.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Login required']));
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$comment_id = (int)($data['comment_id'] ?? 0);
$reply_text = trim($data['reply_text'] ?? '');

if (!$comment_id || !$reply_text) {
    exit(json_encode(['success' => false, 'error' => 'Comment ID and reply text required']));
}

if (strlen($reply_text) > 5000) {
    exit(json_encode(['success' => false, 'error' => 'Reply too long (max 5000 characters)']));
}

try {
    // Ensure blog_comments table exists first
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blog_post_id INT NOT NULL,
        user_id INT NOT NULL,
        content LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY (blog_post_id),
        KEY (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Try to create blog_comment_replies with FK constraint
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS blog_comment_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            user_id INT NOT NULL,
            reply_text LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (comment_id) REFERENCES blog_comments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (comment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $fkException) {
        // If FK constraint fails, try dropping and recreating
        try {
            $pdo->exec("DROP TABLE IF EXISTS blog_comment_replies");
            $pdo->exec("CREATE TABLE blog_comment_replies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                comment_id INT NOT NULL,
                user_id INT NOT NULL,
                reply_text LONGTEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (comment_id) REFERENCES blog_comments(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX (comment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {
            // If still fails, create without FK and log warning
            $pdo->exec("DROP TABLE IF EXISTS blog_comment_replies");
            $pdo->exec("CREATE TABLE blog_comment_replies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                comment_id INT NOT NULL,
                user_id INT NOT NULL,
                reply_text LONGTEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (comment_id),
                INDEX (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }
    
    // Get original comment info to notify user
    $stmt = $pdo->prepare("
        SELECT bc.user_id, bc.blog_post_id, 'blog' as comment_type
        FROM blog_comments bc
        WHERE bc.id = ?
        UNION ALL
        SELECT ac.user_id, ac.announcement_id as blog_post_id, 'announcement' as comment_type
        FROM announcement_comments ac
        WHERE ac.id = ?
    ");
    $stmt->execute([$comment_id, $comment_id]);
    $original_comment = $stmt->fetch();
    
    if (!$original_comment) {
        exit(json_encode(['success' => false, 'error' => 'Comment not found']));
    }
    
    // Insert reply
    $stmt = $pdo->prepare("
        INSERT INTO blog_comment_replies (comment_id, user_id, reply_text, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$comment_id, $user_id, $reply_text]);
    $reply_id = $pdo->lastInsertId();
    
    // Notify original commenter if not replying to themselves
    if ($original_comment['user_id'] && $original_comment['user_id'] != $user_id) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $replier = $stmt->fetch();
        
        $notification_message = htmlspecialchars(($replier['username'] ?? 'Someone') . ' replied to your comment');
        $comment_type = $original_comment['comment_type'] ?? 'blog';
        $url = '/scrollnovels/pages/blog-view.php?id=' . $original_comment['blog_post_id'] . '&type=' . $comment_type . '#comment-' . $comment_id;
        
        $pdo->prepare("
            INSERT INTO notifications (user_id, actor_id, type, message, url, created_at)
            VALUES (?, ?, 'comment_reply', ?, ?, NOW())
        ")->execute([
            $original_comment['user_id'],
            $user_id,
            $notification_message,
            $url
        ]);
    }
    
    exit(json_encode([
        'success' => true,
        'message' => 'Reply posted successfully',
        'reply_id' => $reply_id
    ]));
    
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => $e->getMessage()]));
}
