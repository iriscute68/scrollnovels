<?php
// api/admin/save-admin.php - Add or update admin
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
$username = trim($data['username'] ?? '');
$role = trim($data['role'] ?? 'moderator');

// Validate role - only allow valid roles
if (!in_array($role, ['moderator', 'super_admin'])) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Invalid role. Valid roles: moderator, super_admin']));
}

try {
    if ($id > 0) {
        // Update existing admin role
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $id]);
        $message = 'Admin role updated successfully';
    } else {
        // Find user by username or email and assign admin role
        if (empty($username)) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'error' => 'Username or email required']));
        }
        
        // Extract username without email part if format is "username (email)"
        $username_clean = preg_replace('/\s*\(.*\)/', '', $username);
        $username_clean = trim($username_clean);
        
        // Find user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username_clean, $username_clean]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(404);
            exit(json_encode(['success' => false, 'error' => 'User not found']));
        }
        
        // Update user role
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $user['id']]);
        $id = $user['id'];
        $message = 'User promoted to admin successfully';
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $id
    ]);
    
} catch (Exception $e) {
    error_log('Save admin error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save admin: ' . $e->getMessage()]);
}
?>
