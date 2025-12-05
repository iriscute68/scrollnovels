<?php
// api/blog/get-comments.php - Get comments for a blog post or announcement
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';

$post_id = (int)($_GET['post_id'] ?? 0);
$type = $_GET['type'] ?? 'blog'; // 'blog' or 'announcement'

if (!$post_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Post ID required']);
    exit;
}

try {
    // Ensure tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blog_post_id INT NOT NULL,
        user_id INT,
        comment_text LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_post (blog_post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcement_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_id INT NOT NULL,
        user_id INT,
        comment_text LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_announcement (announcement_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Ensure likes table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS blog_comment_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comment_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (comment_id, user_id),
        FOREIGN KEY (comment_id) REFERENCES blog_comments(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($type === 'announcement') {
        $stmt = $pdo->prepare("
            SELECT ac.id, ac.comment_text, ac.created_at, u.username,
                   COALESCE((SELECT COUNT(*) FROM blog_comment_likes WHERE comment_id = ac.id), 0) as likes
            FROM announcement_comments ac
            LEFT JOIN users u ON ac.user_id = u.id
            WHERE ac.announcement_id = ?
            ORDER BY ac.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT bc.id, bc.comment_text, bc.created_at, u.username,
                   COALESCE((SELECT COUNT(*) FROM blog_comment_likes WHERE comment_id = bc.id), 0) as likes
            FROM blog_comments bc
            LEFT JOIN users u ON bc.user_id = u.id
            WHERE bc.blog_post_id = ?
            ORDER BY bc.created_at DESC
        ");
    }
    
    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'comments' => $comments]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
