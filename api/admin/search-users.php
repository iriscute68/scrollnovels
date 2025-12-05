<?php
// api/admin/search-users.php - Search users for admin
if (session_status() === PHP_SESSION_NONE) session_start();

require_once dirname(__DIR__, 2) . '/config/db.php';
header('Content-Type: application/json');

// Check if logged in at all
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id']) && !isset($_SESSION['admin_user'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not authenticated']));
}

$userId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;

// Check admin permission
try {
    $stmt = $pdo->prepare("SELECT is_admin, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
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
        exit(json_encode(['error' => 'Not authorized']));
    }
} catch (Exception $e) {
    http_response_code(500);
    exit(json_encode(['error' => $e->getMessage()]));
}

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    exit(json_encode([]));
}

try {
    $search = '%' . $q . '%';
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, u.role, u.status, u.created_at,
               (SELECT COUNT(*) FROM stories WHERE author_id = u.id) as story_count
        FROM users u
        WHERE (u.username LIKE ? OR u.email LIKE ?)
        ORDER BY u.id DESC
        LIMIT 20
    ");
    $stmt->execute([$search, $search]);
    $users = $stmt->fetchAll();
    
    echo json_encode($users);
    
} catch (Exception $e) {
    error_log('Search users error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

