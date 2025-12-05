<?php
// admin/api/create-blog-post.php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/config/db.php';

// Check admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['title']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO blog_posts 
        (title, content, category, tags, status, author_id, views, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
    ");
    
    $success = $stmt->execute([
        $data['title'],
        $data['content'],
        $data['category'] ?? 'general',
        $data['tags'] ?? '',
        $data['status'] ?? 'draft',
        $_SESSION['admin_id']
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to insert']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
