<?php
// api/blog/delete-comment.php - Delete a comment

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
$userId = $_SESSION['user_id'];

if (!$commentId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Comment ID required']);
    exit;
}

try {
    // Determine table name
    $table = ($type === 'announcement') ? 'announcement_comments' : 'blog_comments';
    
    // Verify ownership or admin status
    $stmt = $pdo->prepare("SELECT user_id FROM $table WHERE id = ? LIMIT 1");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();
    
    if (!$comment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Comment not found']);
        exit;
    }
    
    // Check if user owns the comment or is admin
    $isAdmin = false;
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $isAdmin = $user && in_array($user['role'], ['admin', 'superadmin', 'moderator']);
    } catch (Exception $e) {}
    
    if ($comment['user_id'] != $userId && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You can only delete your own comments']);
        exit;
    }
    
    // Delete associated likes and replies
    if ($type !== 'announcement') {
        $pdo->prepare("DELETE FROM blog_comment_likes WHERE comment_id = ?")->execute([$commentId]);
        $pdo->prepare("DELETE FROM blog_comment_replies WHERE comment_id = ?")->execute([$commentId]);
    }
    
    // Delete comment
    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->execute([$commentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment deleted successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Delete comment error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
