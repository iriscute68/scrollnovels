<?php
/**
 * api/get-review.php
 * Fetch user's review for a story (if exists)
 */
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

$storyId = (int)($_GET['story_id'] ?? 0);
$userId = $_SESSION['user_id'] ?? null;

if (!$storyId) {
    echo json_encode(['success' => false, 'error' => 'Invalid story ID']);
    exit;
}

try {
    if (!$userId) {
        // Not logged in - no review
        echo json_encode(['success' => true, 'review' => null]);
        exit;
    }

    // Get user's review for this story
    $stmt = $pdo->prepare("
        SELECT id, rating, review_text, created_at, updated_at
        FROM reviews
        WHERE story_id = ? AND user_id = ?
    ");
    $stmt->execute([$storyId, $userId]);
    $review = $stmt->fetch();

    echo json_encode(['success' => true, 'review' => $review]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
