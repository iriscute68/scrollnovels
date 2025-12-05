<?php
// admin/ajax/delete_story.php - Delete story
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['admin_user'])) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Unauthorized']));
}

$story_id = intval($_GET['id'] ?? 0);
if (!$story_id) {
    exit(json_encode(['ok' => false, 'message' => 'Invalid story ID']));
}

try {
    // Delete all chapters first
    $pdo->prepare("DELETE FROM chapters WHERE story_id = ?")->execute([$story_id]);
    
    // Delete story
    $pdo->prepare("DELETE FROM stories WHERE id = ?")->execute([$story_id]);

    $pdo->prepare("
        INSERT INTO admin_activity_logs (admin_id, action, details, created_at)
        VALUES (?, ?, ?, NOW())
    ")->execute([
        $_SESSION['admin_user']['id'],
        'story_delete',
        json_encode(['story_id' => $story_id])
    ]);

    exit(json_encode(['ok' => true]));
} catch (Exception $e) {
    exit(json_encode(['ok' => false, 'message' => $e->getMessage()]));
}
?>
