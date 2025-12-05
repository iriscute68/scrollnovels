<?php
// api/admin/delete-achievement.php - Delete achievement
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/db.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Check admin permission
if (!hasRole('admin')) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'Admin access required']));
}

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Achievement ID required']));
}

try {
    // Check if achievement exists
    $stmt = $pdo->prepare("SELECT id FROM achievements WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Achievement not found']));
    }
    
    // Delete all user achievements for this achievement
    $stmt = $pdo->prepare("DELETE FROM user_achievements WHERE achievement_id = ?");
    $stmt->execute([$id]);
    
    // Delete the achievement
    $stmt = $pdo->prepare("DELETE FROM achievements WHERE id = ?");
    $stmt->execute([$id]);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Achievement deleted successfully']);
    
} catch (Exception $e) {
    error_log('Delete achievement error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete achievement']);
}
?>
