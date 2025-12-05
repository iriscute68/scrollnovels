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

if ($action === 'publish') {
    $story_id = $data['story_id'] ?? null;
    
    if (!$story_id) {
        echo json_encode(['success' => false, 'error' => 'Story ID required']);
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
        
        // Publish story
        $stmt = $pdo->prepare("UPDATE stories SET published = 1, published_at = NOW() WHERE id = ?");
        $stmt->execute([$story_id]);
        
        echo json_encode(['success' => true, 'message' => 'Story published']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} 
elseif ($action === 'unpublish') {
    $story_id = $data['story_id'] ?? null;
    
    if (!$story_id) {
        echo json_encode(['success' => false, 'error' => 'Story ID required']);
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
        
        // Unpublish story
        $stmt = $pdo->prepare("UPDATE stories SET published = 0, published_at = NULL WHERE id = ?");
        $stmt->execute([$story_id]);
        
        echo json_encode(['success' => true, 'message' => 'Story unpublished']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
else {
    // Default: get stories
    try {
        $stmt = $pdo->query("SELECT id, title, slug FROM stories LIMIT 10");
        $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($stories);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>