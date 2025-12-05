<?php
// api/submit-competition-entry.php - Submit story to competition
header('Content-Type: application/json');
session_start();

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);
$competition_id = (int)($data['competition_id'] ?? 0);
$story_id = (int)($data['story_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$competition_id || !$story_id) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'error' => 'Competition ID and Story ID required']));
}

try {
    // Verify competition is active and not at limit
    $stmt = $pdo->prepare("
        SELECT c.id, c.status, c.entry_limit, COUNT(e.id) as entry_count 
        FROM competitions c
        LEFT JOIN competition_entries e ON c.id = e.competition_id
        WHERE c.id = ? AND c.status IN ('active', 'closed')
        GROUP BY c.id
    ");
    $stmt->execute([$competition_id]);
    $competition = $stmt->fetch();
    
    if (!$competition) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'error' => 'Competition not found or not active']));
    }
    
    if ($competition['entry_count'] >= $competition['entry_limit']) {
        http_response_code(400);
        exit(json_encode(['success' => false, 'error' => 'Competition entry limit reached']));
    }
    
    // Verify story exists and belongs to user
    $stmt = $pdo->prepare("SELECT id FROM stories WHERE id = ? AND author_id = ?");
    $stmt->execute([$story_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'error' => 'Story not found or not yours']));
    }
    
    // Submit entry
    $stmt = $pdo->prepare("
           INSERT INTO competition_entries (competition_id, story_id, user_id, status) 
           VALUES (?, ?, ?, 'active')
    ");
    
    $stmt->execute([$competition_id, $story_id, $user_id]);
    $entry_id = $pdo->lastInsertId();
    
    http_response_code(201);
    echo json_encode(['success' => true, 'entry_id' => $entry_id]);
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Story already submitted to this competition']);
    } else {
        error_log('Competition entry error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to submit entry']);
    }
}
?>
