<?php
// api/admin/save-achievement.php - Create or update achievement
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
$title = trim($data['title'] ?? $data['name'] ?? ''); // Handle both 'title' and 'name' from frontend
$description = trim($data['description'] ?? '');
$icon = trim($data['icon'] ?? 'fa-star');
$badge_color = trim($data['badge_color'] ?? '#FFD700');
$points = (int)($data['points'] ?? 0);

if (!$title || !$description) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Title and description required']));
}

try {
    if ($id > 0) {
        // Update existing achievement
        $stmt = $pdo->prepare("
            UPDATE achievements 
            SET title = ?, description = ?, icon = ?, badge_color = ?, points = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $icon, $badge_color, $points, $id]);
        $message = 'Achievement updated successfully';
    } else {
        // Create new achievement
        $stmt = $pdo->prepare("
            INSERT INTO achievements (title, description, icon, badge_color, points, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$title, $description, $icon, $badge_color, $points]);
        $id = $pdo->lastInsertId();
        $message = 'Achievement created successfully';
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $id
    ]);
    
} catch (Exception $e) {
    error_log('Save achievement error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save achievement: ' . $e->getMessage()]);
}
?>
