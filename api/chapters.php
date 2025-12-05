<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'delete') {
    $chapter_id = $data['chapter_id'] ?? null;
    $story_id = $data['story_id'] ?? null;
    
    if (!$chapter_id || !$story_id) {
        echo json_encode(['success' => false, 'error' => 'Chapter ID and Story ID required']);
        exit;
    }
    
    try {
        // Verify story belongs to user
        $stmt = $pdo->prepare("SELECT id FROM stories WHERE id = ? AND author_id = ?");
        $stmt->execute([$story_id, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Story not found or unauthorized']);
            exit;
        }
        
        // Verify chapter belongs to story
        $stmt = $pdo->prepare("SELECT id FROM chapters WHERE id = ? AND story_id = ?");
        $stmt->execute([$chapter_id, $story_id]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Chapter not found']);
            exit;
        }
        
        // Delete chapter
        $stmt = $pdo->prepare("DELETE FROM chapters WHERE id = ?");
        $stmt->execute([$chapter_id]);
        
        echo json_encode(['success' => true, 'message' => 'Chapter deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
