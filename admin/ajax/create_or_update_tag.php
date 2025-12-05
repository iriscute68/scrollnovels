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
    
    $tagId = $_POST['tag_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (!$tagId && !$name) {
        echo json_encode(['error' => 'Missing tag_id for update or name for create']);
        exit;
    }
    
    if ($tagId) {
        $stmt = $pdo->prepare("UPDATE tags SET name = ?, description = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $description, $tagId]);
        $message = 'Tag updated successfully';
    } else {
        $stmt = $pdo->prepare("INSERT INTO tags (name, description, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$name, $description]);
        $tagId = $pdo->lastInsertId();
        $message = 'Tag created successfully';
    }
    
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, reason, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $tagId ? 'update_tag' : 'create_tag', "Tag: $name"]);
    
    echo json_encode(['ok' => true, 'message' => $message, 'tag_id' => $tagId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
