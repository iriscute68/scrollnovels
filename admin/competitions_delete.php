<?php
// admin/competitions_delete.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

// Check admin permission
if (!is_admin() && empty($_SESSION['admin_id']) && empty($_SESSION['admin_user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Handle JSON input from fetch() request
$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? $_POST['id'] ?? 0);

if (!$id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No competition ID provided']);
    exit;
}

try {
    // Basic deletion, cascade will remove entries due to foreign keys
    $stmt = $pdo->prepare("DELETE FROM competitions WHERE id = ?");
    $stmt->execute([$id]);

    // Log admin action (optionally)
    $adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
    $log = $pdo->prepare("INSERT INTO admin_activity (admin_id, action, meta, created_at) VALUES (?, ?, ?, NOW())");
    $log->execute([$adminId, 'delete_competition', json_encode(['id' => $id])]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Delete competition error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
exit;
