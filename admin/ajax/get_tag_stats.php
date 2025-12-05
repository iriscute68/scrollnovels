<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    global $pdo;
    
    $limit = min((int)($_GET['limit'] ?? 50), 500);
    
    $stmt = $pdo->prepare("
        SELECT t.*, COUNT(st.story_id) as usage_count
        FROM tags t
        LEFT JOIN story_tags st ON t.id = st.tag_id
        GROUP BY t.id
        ORDER BY usage_count DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ok' => true,
        'tags' => $tags,
        'total' => count($tags)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
