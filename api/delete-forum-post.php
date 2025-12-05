<?php
// api/delete-forum-post.php - Delete a specific forum post
require_once '../includes/auth.php';
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Check if user is admin or post owner
$data = json_decode(file_get_contents('php://input'), true);
$post_id = (int)($data['post_id'] ?? 0);
$reason = trim($data['reason'] ?? '');

if (!$post_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Post ID required']));
}

try {
    // Check post exists and get details
    $stmt = $pdo->prepare("SELECT user_id, thread_id FROM forum_posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Post not found']));
    }
    
    // Check if user is admin
    $stmt = $pdo->prepare("SELECT admin_level FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    $is_admin = $user && $user['admin_level'] >= 2;
    $is_owner = ($post['user_id'] === $_SESSION['user_id']);
    
    if (!$is_admin && !$is_owner) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'error' => 'You cannot delete this post']));
    }
    
    // Delete the post
    $stmt = $pdo->prepare("DELETE FROM forum_posts WHERE id = ?");
    $stmt->execute([$post_id]);
    
    // Log moderation action
    $action_by = $is_admin ? 'admin' : 'owner';
    $stmt = $pdo->prepare("
        INSERT INTO admin_action_logs (actor_id, action_type, target_type, target_id, data)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        'forum_delete_post',
        'forum_post',
        $post_id,
        json_encode(['reason' => $reason, 'deleted_by' => $action_by])
    ]);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Post deleted successfully']);
    
} catch (Exception $e) {
    error_log('Delete forum post error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete post']);
}
?>
