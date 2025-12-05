<?php
// api/admin/remove-admin.php - Remove admin role from user
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Check admin permission - check users table directly
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
$id = (int)($data['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Admin ID required']));
}

// Prevent removing yourself
if ($id === $_SESSION['user_id']) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Cannot remove yourself as admin']));
}

try {
    // Check if user exists and is an admin
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'User not found']));
    }
    
    if (!in_array($user['role'], ['admin', 'super_admin', 'moderator'])) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'error' => 'User is not an admin']));
    }
    
    // Remove admin role - set to reader role (default)
    $stmt = $pdo->prepare("UPDATE users SET role = 'reader' WHERE id = ?");
    $stmt->execute([$id]);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Admin removed successfully']);
    
} catch (Exception $e) {
    error_log('Remove admin error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to remove admin']);
}
?>
