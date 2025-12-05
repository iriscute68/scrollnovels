<?php
// api/delete-chapter.php - Delete a chapter
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$chapterId = (int)($input['chapter_id'] ?? 0);

if (!$chapterId) {
    echo json_encode(['success' => false, 'error' => 'Invalid chapter']);
    exit;
}

try {
    // Verify the user owns the story this chapter belongs to
    $stmt = $pdo->prepare("
        SELECT c.id FROM chapters c
        INNER JOIN stories s ON c.story_id = s.id
        WHERE c.id = ? AND s.author_id = ?
        LIMIT 1
    ");
    $stmt->execute([$chapterId, $userId]);
    
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Delete the chapter
    $stmt = $pdo->prepare("DELETE FROM chapters WHERE id = ?");
    $stmt->execute([$chapterId]);
    
    echo json_encode(['success' => true, 'message' => 'Chapter deleted successfully']);
} catch (Exception $e) {
    error_log('Delete chapter error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
