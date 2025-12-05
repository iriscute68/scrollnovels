<?php
// api/blog/like-comment.php - Like or unlike a comment
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Login required']));
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$comment_id = (int)($data['comment_id'] ?? 0);

if (!$comment_id) {
    exit(json_encode(['success' => false, 'error' => 'Comment ID required']));
}

try {
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_comment_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comment_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (comment_id, user_id),
        FOREIGN KEY (comment_id) REFERENCES blog_comments(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Check if user already liked this comment
    $stmt = $pdo->prepare("SELECT id FROM blog_comment_likes WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$comment_id, $user_id]);
    $existing_like = $stmt->fetch();
    
    if ($existing_like) {
        // Unlike
        $pdo->prepare("DELETE FROM blog_comment_likes WHERE comment_id = ? AND user_id = ?")
            ->execute([$comment_id, $user_id]);
    } else {
        // Like
        $pdo->prepare("INSERT INTO blog_comment_likes (comment_id, user_id) VALUES (?, ?)")
            ->execute([$comment_id, $user_id]);
    }
    
    // Get updated like count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM blog_comment_likes WHERE comment_id = ?");
    $stmt->execute([$comment_id]);
    $likes = $stmt->fetch()['count'] ?? 0;
    
    exit(json_encode([
        'success' => true,
        'likes' => $likes,
        'liked' => !$existing_like
    ]));
    
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => $e->getMessage()]));
}
