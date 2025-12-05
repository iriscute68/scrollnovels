<?php
// admin/ajax/delete_blog_post.php - Delete blog post
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
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->prepare("
        INSERT INTO admin_activity_logs (admin_id, action, details, created_at)
        VALUES (?, ?, ?, NOW())
    ")->execute([
        $_SESSION['admin_user']['id'],
        'blog_post_delete',
        json_encode(['blog_id' => $id])
    ]);

    exit(json_encode(['ok' => true]));
} catch (Exception $e) {
    exit(json_encode(['ok' => false, 'message' => $e->getMessage()]));
}
?>
