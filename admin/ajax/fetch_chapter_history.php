<?php
// admin/ajax/fetch_chapter_history.php - Get edit history for a chapter

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
session_start();

// Only admins/moderators can view history
if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'moderator'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden', 'history' => []]));
}

$chapter_id = intval($_GET['chapter_id'] ?? 0);
if (!$chapter_id) {
    exit(json_encode(['error' => 'Missing chapter_id', 'history' => []]));
}

try {
    // Fetch chapter history (version snapshots)
    $stmt = $pdo->prepare("
        SELECT 
            cv.id,
            cv.chapter_id,
            cv.old_title,
            cv.old_content,
            cv.change_summary,
            cv.created_at,
            u.username AS editor_name
        FROM chapter_versions cv
        LEFT JOIN users u ON u.id = cv.admin_id
        WHERE cv.chapter_id = ?
        ORDER BY cv.created_at DESC
        LIMIT 100
    ");
    
    $stmt->execute([$chapter_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'history' => $history,
        'count' => count($history)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'history' => []
    ]);
}
?>
