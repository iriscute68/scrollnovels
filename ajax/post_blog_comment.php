<?php
// ajax/post_blog_comment.php - Post comment on blog post
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Not logged in']));
}

$data = json_decode(file_get_contents('php://input'), true);
$blog_id = intval($data['blog_post_id'] ?? 0);
$text = trim($data['comment_text'] ?? '');

if (!$blog_id || !$text || strlen($text) < 3) {
    exit(json_encode(['ok' => false, 'message' => 'Invalid comment']));
}

try {
    // Check blog post exists
    $stmt = $pdo->prepare("SELECT id FROM announcements WHERE id = ?");
    $stmt->execute([$blog_id]);
    if (!$stmt->fetch()) {
        exit(json_encode(['ok' => false, 'message' => 'Blog post not found']));
    }

    // Insert comment (requires moderation)
    $stmt = $pdo->prepare("
        INSERT INTO blog_comments (blog_post_id, user_id, comment_text, is_approved, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$blog_id, $_SESSION['user_id'], $text]);

    exit(json_encode(['ok' => true, 'message' => 'Comment posted']));
} catch (Exception $e) {
    exit(json_encode(['ok' => false, 'message' => $e->getMessage()]));
}
?>
