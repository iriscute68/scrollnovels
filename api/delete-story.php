<?php
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$story_id = (int)($data['story_id'] ?? 0);

if (!$story_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid story ID']);
    exit;
}

try {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT author_id FROM stories WHERE id = ?");
    $stmt->execute([$story_id]);
    $story = $stmt->fetch();
    
    if (!$story || $story['author_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // Delete all chapters first
    $stmt = $pdo->prepare("DELETE FROM chapters WHERE story_id = ?");
    $stmt->execute([$story_id]);
    
    // Delete all reviews
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE story_id = ?");
    $stmt->execute([$story_id]);
    
    // Delete the story
    $stmt = $pdo->prepare("DELETE FROM stories WHERE id = ? AND author_id = ?");
    $stmt->execute([$story_id, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Story deleted successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
