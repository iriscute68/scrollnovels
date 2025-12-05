<?php
// pages/api/post_comment.php - Save blog post comment
session_start();
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $post_id = intval($data['post_id'] ?? 0);
    $content = trim($data['content'] ?? '');

    if (!$post_id || !$content) {
        echo json_encode(['success' => false, 'error' => 'Post and content required']);
        exit;
    }

    // Verify post exists
    $check = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
    $check->execute([$post_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Post not found']);
        exit;
    }

    // Save comment
    $stmt = $pdo->prepare("
        INSERT INTO post_comments (post_id, user_id, content, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$post_id, $_SESSION['user_id'], $content]);

    echo json_encode(['success' => true, 'message' => 'Comment posted']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
