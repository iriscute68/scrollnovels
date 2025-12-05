<?php
// api/save-story-metadata.php - Save story genres, tags, warnings, and fanfiction data

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$storyId = (int)($input['story_id'] ?? 0);
$genres = $input['genres'] ?? [];
$tags = $input['tags'] ?? [];
$warnings = $input['warnings'] ?? [];
$isFanfiction = (int)($input['is_fanfiction'] ?? 0);
$fanficSource = trim($input['fanfic_source'] ?? '');

if (!$storyId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Story ID required']);
    exit;
}

try {
    // Verify story belongs to user
    $stmt = $pdo->prepare("SELECT id FROM stories WHERE id = ? AND author_id = ?");
    $stmt->execute([$storyId, $userId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not authorized']);
        exit;
    }
    
    // Update fanfiction data and warnings
    $stmt = $pdo->prepare("
        UPDATE stories 
        SET is_fanfiction = ?, fanfic_source = ?, content_warnings = ?
        WHERE id = ? AND author_id = ?
    ");
    $stmt->execute([
        $isFanfiction,
        $isFanfiction ? $fanficSource : null,
        json_encode($warnings),
        $storyId,
        $userId
    ]);
    
    // Delete existing genres
    $pdo->prepare("DELETE FROM story_genres WHERE story_id = ?")->execute([$storyId]);
    
    // Insert new genres
    foreach ($genres as $genreId) {
        $pdo->prepare("INSERT INTO story_genres (story_id, genre_id) VALUES (?, ?)")
            ->execute([$storyId, (int)$genreId]);
    }
    
    // Delete existing tags
    $pdo->prepare("DELETE FROM story_story_tags WHERE story_id = ?")->execute([$storyId]);
    
    // Insert new tags
    foreach ($tags as $tagId) {
        $pdo->prepare("INSERT INTO story_story_tags (story_id, tag_id) VALUES (?, ?)")
            ->execute([$storyId, (int)$tagId]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Story metadata saved successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Save metadata error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
