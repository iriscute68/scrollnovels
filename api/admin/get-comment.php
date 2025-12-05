<?php
// api/admin/get-comment.php - Get comment details for admin view
session_start();
header('Content-Type: application/json');

require_once dirname(dirname(__DIR__)) . '/config/db.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';

// Verify admin access
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !in_array($user['role'], ['admin', 'super_admin', 'moderator'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Comment ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, s.title as story_title
        FROM book_comments c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN stories s ON c.story_id = s.id
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Comment not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'id' => $comment['id'],
        'content' => $comment['content'],
        'username' => $comment['username'] ?? 'Unknown',
        'story_title' => $comment['story_title'] ?? 'Unknown Story',
        'created_at' => $comment['created_at']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
