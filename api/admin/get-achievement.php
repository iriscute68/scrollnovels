<?php
// api/admin/get-achievement.php - Fetch achievement data
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

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Achievement ID required']));
}

try {
    $stmt = $pdo->prepare("SELECT * FROM achievements WHERE id = ?");
    $stmt->execute([$id]);
    $achievement = $stmt->fetch();
    
    if (!$achievement) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Achievement not found']));
    }
    
    http_response_code(200);
    echo json_encode($achievement);
    
} catch (Exception $e) {
    error_log('Get achievement error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to fetch achievement']);
}
?>
