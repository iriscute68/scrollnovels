<?php
// api/admin/forum-moderation.php - Forum moderation actions
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/config/db.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Check admin auth
$adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
if (!$adminId) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Login required']));
}

// Verify admin role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$adminId]);
$user = $stmt->fetch();

if (!$user || !in_array($user['role'], ['admin', 'super_admin', 'moderator'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Admin access required']));
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$topicId = (int)($input['topic_id'] ?? 0);
$postId = (int)($input['post_id'] ?? 0);

try {
    // Ensure columns exist - use pinned instead of is_locked
    try {
        $pdo->exec("ALTER TABLE forum_topics ADD COLUMN pinned TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {}
    
    try {
        $pdo->exec("ALTER TABLE forum_posts ADD COLUMN is_flagged TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {}
    
    try {
        $pdo->exec("ALTER TABLE forum_posts ADD COLUMN reports INT DEFAULT 0");
    } catch (Exception $e) {}

    switch ($action) {
        case 'pin':
            if (!$topicId) throw new Exception('Topic ID required');
            $stmt = $pdo->prepare("UPDATE forum_topics SET pinned = 1 WHERE id = ?");
            $stmt->execute([$topicId]);
            echo json_encode(['success' => true, 'message' => 'Topic pinned']);
            break;
            
        case 'unpin':
            if (!$topicId) throw new Exception('Topic ID required');
            $stmt = $pdo->prepare("UPDATE forum_topics SET pinned = 0 WHERE id = ?");
            $stmt->execute([$topicId]);
            echo json_encode(['success' => true, 'message' => 'Topic unpinned']);
            break;
            
        case 'lock':
            if (!$topicId) throw new Exception('Topic ID required');
            $stmt = $pdo->prepare("UPDATE forum_topics SET status = 'closed' WHERE id = ?");
            $stmt->execute([$topicId]);
            echo json_encode(['success' => true, 'message' => 'Topic locked']);
            break;
            
        case 'unlock':
            if (!$topicId) throw new Exception('Topic ID required');
            $stmt = $pdo->prepare("UPDATE forum_topics SET status = 'active' WHERE id = ?");
            $stmt->execute([$topicId]);
            echo json_encode(['success' => true, 'message' => 'Topic unlocked']);
            break;
            
        case 'remove_topic':
            if (!$topicId) throw new Exception('Topic ID required');
            $stmt = $pdo->prepare("UPDATE forum_topics SET status = 'removed' WHERE id = ?");
            $stmt->execute([$topicId]);
            echo json_encode(['success' => true, 'message' => 'Topic removed']);
            break;
            
        case 'remove_post':
            if (!$postId) throw new Exception('Post ID required');
            $stmt = $pdo->prepare("DELETE FROM forum_posts WHERE id = ?");
            $stmt->execute([$postId]);
            echo json_encode(['success' => true, 'message' => 'Post removed']);
            break;
            
        case 'dismiss_flag':
            if (!$postId) throw new Exception('Post ID required');
            $stmt = $pdo->prepare("UPDATE forum_posts SET is_flagged = 0, reports = 0 WHERE id = ?");
            $stmt->execute([$postId]);
            echo json_encode(['success' => true, 'message' => 'Flag dismissed']);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
    // Log admin action
    try {
        $logStmt = $pdo->prepare("INSERT INTO admin_action_logs (actor_id, action, target_type, target_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $logStmt->execute([$_SESSION['user_id'], 'forum_' . $action, $topicId ? 'topic' : 'post', $topicId ?: $postId]);
    } catch (Exception $e) {}
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
