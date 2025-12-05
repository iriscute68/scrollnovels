<?php
// admin/ajax/feature_story.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    global $pdo;
    
    $storyId = $_POST['story_id'] ?? 0;
    $action = $_POST['action'] ?? 'feature'; // feature or unfeature
    
    if (!$storyId) {
        echo json_encode(['error' => 'Missing story_id']);
        exit;
    }
    
    if ($action === 'feature') {
        // Check if already featured (limit to 10 featured stories)
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM stories WHERE is_featured = 1");
        $stmt->execute();
        $featured = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($featured['count'] >= 10) {
            echo json_encode(['error' => 'Maximum featured stories (10) reached']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE stories SET is_featured = 1, featured_at = NOW() WHERE id = ?");
        $stmt->execute([$storyId]);
        $message = 'Story featured successfully';
    } else {
        // Unfeature story
        $stmt = $pdo->prepare("UPDATE stories SET is_featured = 0, featured_at = NULL WHERE id = ?");
        $stmt->execute([$storyId]);
        $message = 'Story unfeatured successfully';
    }
    
    // Log action
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, reason, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $action . '_story', "Story ID: $storyId"]);
    
    echo json_encode(['ok' => true, 'message' => $message]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
