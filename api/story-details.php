<?php
/**
 * api/story-details.php - Get story with chapters
 */
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/db.php';

requireLogin();

$story_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$story_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing story_id']);
    exit;
}

try {
    // Verify user owns this story
    $stmt = $pdo->prepare("SELECT * FROM stories WHERE id = ? AND author_id = ?");
    $stmt->execute([$story_id, $user_id]);
    $story = $stmt->fetch();

    if (!$story) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // Fetch chapters
    $stmt = $pdo->prepare("
        SELECT id, chapter_number, title, word_count, status, created_at 
        FROM chapters 
        WHERE story_id = ? 
        ORDER BY sequence ASC
    ");
    $stmt->execute([$story_id]);
    $chapters = $stmt->fetchAll();

    echo json_encode([
        'ok' => true,
        'story' => [
            'id' => $story['id'],
            'title' => $story['title'],
            'description' => $story['description'],
            'status' => $story['status'],
            'views' => $story['views'],
            'cover' => $story['cover']
        ],
        'chapters' => $chapters
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
