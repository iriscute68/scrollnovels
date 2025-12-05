<?php
// admin/api/blog_delete.php - Delete blog post
session_start();
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'No post ID provided']);
        exit;
    }

    // Delete associated comments first
    $pdo->prepare("DELETE FROM post_comments WHERE post_id = ?")->execute([$id]);

    // Delete post
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Blog post deleted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
