<?php
// api/moderate-forum-post.php - Forum moderation actions
header('Content-Type: application/json');
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Check moderation permissions
try {
    $stmt = $pdo->prepare("SELECT admin_level FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['admin_level'] < 2) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'error' => 'Moderation access required']));
    }
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => 'Server error']));
}

$data = json_decode(file_get_contents('php://input'), true);
$post_id = (int)($data['post_id'] ?? 0);
$action = trim($data['action'] ?? '');
$reason = trim($data['reason'] ?? '');
$notes = trim($data['notes'] ?? '');
$admin_id = $_SESSION['user_id'];

// Validate input
if (!$post_id || empty($action) || empty($reason)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Post ID, action, and reason required']));
}

if (!in_array($action, ['warn', 'delete', 'edit', 'suspend', 'restore'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid action']));
}

try {
    // Verify post exists
    $stmt = $pdo->prepare("SELECT user_id, thread_id FROM forum_posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Post not found']));
    }
    
    $post_user_id = $post['user_id'];
    
    // Execute action
    $new_status = 'active';
    $warning_severity = 'warning';
    
    switch ($action) {
        case 'warn':
            // Log action
            log_moderation_action($pdo, $post_id, $admin_id, 'warn', $reason, $notes);
            
            // Create warning
            $stmt = $pdo->prepare("
                INSERT INTO user_warnings (user_id, moderator_id, reason, severity) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$post_user_id, $admin_id, $reason, 'warning']);
            break;
            
        case 'delete':
            $new_status = 'deleted';
            $stmt = $pdo->prepare("UPDATE forum_posts SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $post_id]);
            log_moderation_action($pdo, $post_id, $admin_id, 'delete', $reason, $notes);
            break;
            
        case 'edit':
            // Update post with new content
            $new_content = $data['content'] ?? '';
            $stmt = $pdo->prepare("UPDATE forum_posts SET content = ?, edited_by = ? WHERE id = ?");
            $stmt->execute([$new_content, $admin_id, $post_id]);
            log_moderation_action($pdo, $post_id, $admin_id, 'edit', $reason, $notes);
            break;
            
        case 'suspend':
            $new_status = 'suspended';
            $stmt = $pdo->prepare("UPDATE forum_posts SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $post_id]);
            
            // Create temporary ban warning (7 days)
            $stmt = $pdo->prepare("
                INSERT INTO user_warnings (user_id, moderator_id, reason, severity, expires_at) 
                VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
            ");
            $stmt->execute([$post_user_id, $admin_id, $reason, 'temporary_ban']);
            log_moderation_action($pdo, $post_id, $admin_id, 'suspend', $reason, $notes);
            break;
            
        case 'restore':
            $new_status = 'active';
            $stmt = $pdo->prepare("UPDATE forum_posts SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $post_id]);
            log_moderation_action($pdo, $post_id, $admin_id, 'restore', $reason, $notes);
            break;
    }
    
    // Notify user
    $notif_titles = [
        'warn' => 'Forum Warning',
        'delete' => 'Post Deleted',
        'edit' => 'Post Edited by Moderator',
        'suspend' => 'Temporary Forum Ban',
        'restore' => 'Post Restored'
    ];
    
    $notif_messages = [
        'warn' => "Your forum post violated community guidelines: $reason",
        'delete' => "Your forum post was deleted for: $reason",
        'edit' => "Your forum post was edited by a moderator",
        'suspend' => "You have been temporarily suspended from posting: $reason",
        'restore' => "Your forum post has been restored"
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, reference_id, reference_type) 
        VALUES (?, ?, ?, ?, ?, 'forum_post')
    ");
    
    $type = 'forum_' . $action;
    $title = $notif_titles[$action];
    $message = $notif_messages[$action];
    
    $stmt->execute([$post_user_id, $type, $title, $message, $post_id]);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'action' => $action]);
    
} catch (Exception $e) {
    error_log('Forum moderation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Moderation action failed']);
}

function log_moderation_action($pdo, $post_id, $admin_id, $action, $reason, $notes) {
    $stmt = $pdo->prepare("
        INSERT INTO forum_moderation (post_id, moderator_id, action_type, reason, notes) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$post_id, $admin_id, $action, $reason, $notes]);
}
?>
