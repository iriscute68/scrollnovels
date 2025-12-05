<?php
// admin/api/remove-forum-post.php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/config/db.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing post ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM community_posts WHERE id = ?");
    $success = $stmt->execute([$data['id']]);
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
