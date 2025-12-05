<?php
// api/lock-thread.php - Lock or unlock a forum thread
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

$thread_id = (int)($_POST['thread_id'] ?? $_GET['thread_id'] ?? 0);

if (!$thread_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Thread ID required']));
}

try {
    // Check current status
    $stmt = $pdo->prepare("SELECT status FROM forum_topics WHERE id = ?");
    $stmt->execute([$thread_id]);
    $thread = $stmt->fetch();
    
    if (!$thread) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Thread not found']));
    }
    
    // Toggle lock status
    $new_status = ($thread['status'] === 'closed') ? 'open' : 'closed';
    
    $stmt = $pdo->prepare("UPDATE forum_topics SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $thread_id]);
    
    // Log moderation action
    $action = ($new_status === 'closed') ? 'locked' : 'unlocked';
    $stmt = $pdo->prepare("
        INSERT INTO admin_action_logs (actor_id, action_type, target_type, target_id, data)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        'forum_' . $action,
        'forum_topic',
        $thread_id,
        json_encode(['action' => $action, 'status' => $new_status])
    ]);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'action' => $action, 'new_status' => $new_status]);
    
} catch (Exception $e) {
    error_log('Lock thread error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to lock/unlock thread']);
}
?>
