<?php
// api/admin/get-admin.php - Fetch admin data
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
        // Check if is_admin=1 or role is admin/super_admin/moderator
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

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Admin ID required']));
}

try {
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Admin not found']));
    }
    
    http_response_code(200);
    echo json_encode($admin);
    
} catch (Exception $e) {
    error_log('Get admin error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch admin']);
}
?>
