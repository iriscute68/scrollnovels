<?php
// api/admin/delete-comment.php - Delete a comment (admin only)
session_start();
header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/config/db.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Check admin auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Login required']));
}

// Verify admin role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !in_array($user['role'], ['admin', 'super_admin', 'moderator'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Admin access required']));
}

$input = json_decode(file_get_contents('php://input'), true);
$commentId = (int)($input['id'] ?? 0);

if (!$commentId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Comment ID required']));
}

try {
    // Delete comment from book_comments table
    $stmt = $pdo->prepare("DELETE FROM book_comments WHERE id = ?");
    $stmt->execute([$commentId]);
    
    if ($stmt->rowCount() === 0) {
        // Try comments table as fallback
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
    }
    
    // Log admin action
    try {
        $logStmt = $pdo->prepare("INSERT INTO admin_action_logs (actor_id, action, target_type, target_id, created_at) VALUES (?, 'delete_comment', 'comment', ?, NOW())");
        $logStmt->execute([$_SESSION['user_id'], $commentId]);
    } catch (Exception $e) {
        // Log table might not exist
    }
    
    echo json_encode(['success' => true, 'message' => 'Comment deleted successfully']);
    
} catch (Exception $e) {
    error_log('Delete comment error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
