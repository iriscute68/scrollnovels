<?php
// api/admin/moderate-user.php - User moderation (mute, temp ban, perm ban)
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Check admin permission
try {
    $stmt = $pdo->prepare("SELECT is_admin, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
    
    $is_admin = false;
    if ($current_user) {
        if ((isset($current_user['is_admin']) && $current_user['is_admin'] == 1) ||
            (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'super_admin', 'moderator']))) {
            $is_admin = true;
        }
    }
    
    if (!$is_admin) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'error' => 'Admin access required']));
    }
} catch (Exception $e) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Permission check failed']));
}

$data = json_decode(file_get_contents('php://input'), true);

$user_id = (int)($data['user_id'] ?? 0);
$action = trim($data['action'] ?? '');
$days = (int)($data['days'] ?? 0);

if (!$user_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'User ID required']));
}

if (!in_array($action, ['mute', 'temp_ban', 'perm_ban'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid action']));
}

// Prevent moderating yourself
if ($user_id === $_SESSION['user_id']) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Cannot moderate yourself']));
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'User not found']));
    }
    
    // Perform moderation action
    if ($action === 'mute') {
        // Create mute record
        $until_date = $days > 0 ? date('Y-m-d H:i:s', strtotime("+$days days")) : date('Y-m-d 23:59:59', strtotime('+100 years'));
        
        $stmt = $pdo->prepare("
            INSERT INTO user_mutes (user_id, moderator_id, reason, muted_until, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                muted_until = VALUES(muted_until),
                moderator_id = VALUES(moderator_id),
                created_at = NOW()
        ");
        $stmt->execute([$user_id, $_SESSION['user_id'], 'Moderation action', $until_date]);
        
        $message = 'User muted successfully';
        
    } elseif ($action === 'temp_ban') {
        // Temporary ban - set status to suspended with date
        $until_date = date('Y-m-d H:i:s', strtotime("+$days days"));
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET status = 'suspended', 
                suspension_until = ?
            WHERE id = ?
        ");
        $stmt->execute([$until_date, $user_id]);
        
        $message = "User temporarily banned for $days days";
        
    } elseif ($action === 'perm_ban') {
        // Permanent ban
        $stmt = $pdo->prepare("
            UPDATE users 
            SET status = 'banned',
                suspension_until = NULL
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        
        $message = 'User permanently banned';
    }
    
    // Log the moderation action
    $stmt = $pdo->prepare("
        INSERT INTO admin_action_logs (actor_id, action, target_user_id, details, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], 'user_moderation', $user_id, $action . ($days > 0 ? " - $days days" : '')]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    error_log('Moderate user error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to moderate user: ' . $e->getMessage()]);
}
?>
