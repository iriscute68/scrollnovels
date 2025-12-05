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
    
    $primaryTagId = $_POST['primary_tag_id'] ?? 0;
    $tagIdsToMerge = $_POST['tag_ids'] ?? []; // array of tag IDs to merge
    
    if (!$primaryTagId || empty($tagIdsToMerge)) {
        echo json_encode(['error' => 'Missing primary_tag_id or tag_ids']);
        exit;
    }
    
    if (in_array($primaryTagId, (array)$tagIdsToMerge)) {
        echo json_encode(['error' => 'Primary tag cannot be in merge list']);
        exit;
    }
    
    foreach ($tagIdsToMerge as $tagId) {
        $stmt = $pdo->prepare("UPDATE story_tags SET tag_id = ? WHERE tag_id = ?");
        $stmt->execute([$primaryTagId, $tagId]);
        
        $stmt = $pdo->prepare("DELETE FROM tags WHERE id = ?");
        $stmt->execute([$tagId]);
    }
    
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, reason, created_at) VALUES (?, 'merge_tags', ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], "Merged tags into primary tag $primaryTagId"]);
    
    echo json_encode(['ok' => true, 'message' => 'Tags merged successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
