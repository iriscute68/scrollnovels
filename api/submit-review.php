<?php
/**
 * api/submit-review.php
 * Handles review submission, creation, and updates
 * One review per user per story (UNIQUE constraint)
 */
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$storyId = (int)($_POST['story_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$reviewText = trim($_POST['review_text'] ?? '');

// Validate input
if (!$storyId || $storyId < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid story ID']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
    exit;
}

try {
    // Check if review already exists
    $checkStmt = $pdo->prepare("
        SELECT id FROM reviews 
        WHERE story_id = ? AND user_id = ?
    ");
    $checkStmt->execute([$storyId, $userId]);
    $existingReview = $checkStmt->fetch();

    if ($existingReview) {
        // UPDATE existing review
        $updateStmt = $pdo->prepare("
            UPDATE reviews 
            SET rating = ?, review_text = ?, updated_at = NOW()
            WHERE story_id = ? AND user_id = ?
        ");
        $updateStmt->execute([$rating, $reviewText, $storyId, $userId]);
        
        echo json_encode([
            'success' => true,
            'action' => 'updated',
            'message' => 'Review updated successfully',
            'review_id' => $existingReview['id']
        ]);
    } else {
        // INSERT new review
        $insertStmt = $pdo->prepare("
            INSERT INTO reviews (story_id, user_id, rating, review_text) 
            VALUES (?, ?, ?, ?)
        ");
        $insertStmt->execute([$storyId, $userId, $rating, $reviewText]);
        
        echo json_encode([
            'success' => true,
            'action' => 'created',
            'message' => 'Review submitted successfully',
            'review_id' => $pdo->lastInsertId()
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
