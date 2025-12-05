<?php
// api/blog/get-comment-replies.php - Get replies for a comment
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';

$comment_id = (int)($_GET['comment_id'] ?? 0);

if (!$comment_id) {
    exit(json_encode(['success' => false, 'error' => 'Comment ID required']));
}

try {
    // Ensure table exists
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
    
    // Get replies
    $stmt = $pdo->prepare("
        SELECT bcr.*, u.username 
        FROM blog_comment_replies bcr
        JOIN users u ON bcr.user_id = u.id
        WHERE bcr.comment_id = ?
        ORDER BY bcr.created_at ASC
    ");
    $stmt->execute([$comment_id]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'replies' => $replies
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
