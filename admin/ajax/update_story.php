<?php
// admin/ajax/update_story.php
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
    
    $storyId = $_POST['story_id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? ''; // published, draft, archived
    
    if (!$storyId) {
        echo json_encode(['error' => 'Missing story_id']);
        exit;
    }
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    
    if ($title) {
        $updates[] = "title = ?";
        $params[] = $title;
    }
    if ($description) {
        $updates[] = "description = ?";
        $params[] = $description;
    }
    if ($status && in_array($status, ['published', 'draft', 'archived'])) {
        $updates[] = "status = ?";
        $params[] = $status;
    }
    
    if (empty($updates)) {
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $storyId;
    
    $query = "UPDATE stories SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    // Log action
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, reason, created_at) VALUES (?, 'update_story', ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], "Story ID: $storyId - " . implode(", ", array_keys(array_filter(['title' => $title, 'description' => $description, 'status' => $status])))]);
    
    echo json_encode(['ok' => true, 'message' => 'Story updated successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
