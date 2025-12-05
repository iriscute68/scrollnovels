<?php
// api/blog/edit-comment.php - Edit a comment

header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$commentId = (int)($input['comment_id'] ?? 0);
$type = trim($input['type'] ?? 'blog');
$commentText = trim($input['comment_text'] ?? '');
$userId = $_SESSION['user_id'];

if (!$commentId || !$commentText) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Comment ID and text required']);
    exit;
}

try {
    // Determine table name
    $table = ($type === 'announcement') ? 'announcement_comments' : 'blog_comments';
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT user_id FROM $table WHERE id = ? LIMIT 1");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();
    
    if (!$comment || $comment['user_id'] != $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only edit your own comments']);
        exit;
    }
    
    // Update comment
    $stmt = $pdo->prepare("UPDATE $table SET comment_text = ? WHERE id = ?");
    $stmt->execute([$commentText, $commentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Edit comment error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
