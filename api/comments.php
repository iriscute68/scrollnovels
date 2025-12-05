<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Login required to post comments']);
        exit;
    }
    
    $comment_text = $data['comment'] ?? null;
    $chapter_id = $data['chapter_id'] ?? null;
    $book_id = $data['book_id'] ?? null;
    
    if (!$comment_text || !$chapter_id) {
        echo json_encode(['success' => false, 'error' => 'Comment and chapter ID required']);
        exit;
    }
    
    try {
        // Get user info
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Insert comment
        $stmt = $pdo->prepare("INSERT INTO blog_comments (blog_post_id, user_id, comment_text, is_approved, created_at) VALUES (?, ?, ?, 1, NOW())");
        $stmt->execute([$chapter_id, $_SESSION['user_id'], $comment_text]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Comment posted successfully',
            'comment_id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
elseif ($method === 'GET') {
    // Get comments for a chapter
    $chapter_id = $_GET['chapter_id'] ?? null;
    
    if (!$chapter_id) {
        echo json_encode(['success' => false, 'error' => 'Chapter ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT bc.id, u.username as user_name, bc.comment_text, bc.created_at FROM blog_comments bc LEFT JOIN users u ON bc.user_id = u.id WHERE bc.blog_post_id = ? AND bc.is_approved = 1 ORDER BY bc.created_at DESC LIMIT 50");
        $stmt->execute([$chapter_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $comments]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>
