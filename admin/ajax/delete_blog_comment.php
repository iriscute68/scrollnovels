<?php
// admin/ajax/delete_blog_comment.php - Delete blog comment
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['admin_user'])) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Unauthorized']));
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    exit(json_encode(['ok' => false, 'message' => 'Invalid ID']));
}

try {
    $stmt = $pdo->prepare("DELETE FROM blog_comments WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->prepare("
        INSERT INTO admin_activity_logs (admin_id, action, details, created_at)
        VALUES (?, ?, ?, NOW())
    ")->execute([
        $_SESSION['admin_user']['id'],
        'blog_comment_delete',
        json_encode(['comment_id' => $id])
    ]);

    exit(json_encode(['ok' => true]));
} catch (Exception $e) {
    exit(json_encode(['ok' => false, 'message' => $e->getMessage()]));
}
?>
