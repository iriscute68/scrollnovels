<?php
// api/admin/delete-blog.php - Delete a blog post (admin only)
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
$blogId = (int)($input['id'] ?? 0);

if (!$blogId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Blog ID required']));
}

try {
    // Delete blog comments first
    $stmt = $pdo->prepare("DELETE FROM blog_comments WHERE blog_post_id = ?");
    $stmt->execute([$blogId]);
    
    // Delete blog post
    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
    $stmt->execute([$blogId]);
    
    // Log admin action
    try {
        $logStmt = $pdo->prepare("INSERT INTO admin_action_logs (actor_id, action, target_type, target_id, created_at) VALUES (?, 'delete_blog', 'blog', ?, NOW())");
        $logStmt->execute([$_SESSION['user_id'], $blogId]);
    } catch (Exception $e) {
        // Log table might not exist
    }
    
    echo json_encode(['success' => true, 'message' => 'Blog post deleted successfully']);
    
} catch (Exception $e) {
    error_log('Delete blog error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
