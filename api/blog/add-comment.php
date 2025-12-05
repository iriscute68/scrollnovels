<?php
// api/blog/add-comment.php - Add a comment to a blog post or announcement
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$post_id = (int)($data['post_id'] ?? 0);
$type = $data['type'] ?? 'blog'; // 'blog' or 'announcement'
$comment_text = trim($data['comment_text'] ?? '');

if (!$post_id || !$comment_text) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Post ID and comment text required']);
    exit;
}

if (strlen($comment_text) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Comment too long (max 5000 characters)']);
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

    if ($type === 'announcement') {
        $stmt = $pdo->prepare("
            INSERT INTO announcement_comments (announcement_id, user_id, comment_text, created_at)
            VALUES (?, ?, ?, NOW())
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO blog_comments (blog_post_id, user_id, comment_text, created_at)
            VALUES (?, ?, ?, NOW())
        ");
    }
    
    $stmt->execute([$post_id, $user_id, $comment_text]);
    
    echo json_encode(['success' => true, 'message' => 'Comment posted successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
