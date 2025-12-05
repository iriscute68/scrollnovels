<?php
// api/delete-thread.php - Delete a forum thread and all its posts
require_once '../includes/auth.php';
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Check if user is admin
$stmt = $pdo->prepare("SELECT admin_level FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['admin_level'] < 2) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Admin access required']));
}

$data = json_decode(file_get_contents('php://input'), true);
$thread_id = (int)($data['thread_id'] ?? 0);
$reason = trim($data['reason'] ?? '');

if (!$thread_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Thread ID required']));
}

try {
    // Check thread exists
    $stmt = $pdo->prepare("SELECT author_id FROM forum_topics WHERE id = ?");
    $stmt->execute([$thread_id]);
    $thread = $stmt->fetch();
    
    if (!$thread) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Thread not found']));
    }
    
    // Delete all posts in this thread
    $stmt = $pdo->prepare("DELETE FROM forum_posts WHERE thread_id = ?");
    $stmt->execute([$thread_id]);
    
    // Delete the thread itself
    $stmt = $pdo->prepare("DELETE FROM forum_topics WHERE id = ?");
    $stmt->execute([$thread_id]);
    
    // Log moderation action
    $stmt = $pdo->prepare("
        INSERT INTO admin_action_logs (actor_id, action_type, target_type, target_id, data)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        'forum_delete_thread',
        'forum_topic',
        $thread_id,
        json_encode(['reason' => $reason])
    ]);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Thread deleted successfully']);
    
} catch (Exception $e) {
    error_log('Delete thread error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete thread']);
}
?>
