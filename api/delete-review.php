<?php
/**
 * api/delete-review.php
 * Handles review deletion (only by review owner or admin)
 */
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$reviewId = (int)($_POST['review_id'] ?? 0);

if (!$reviewId) {
    echo json_encode(['success' => false, 'error' => 'Invalid review ID']);
    exit;
}

try {
    // Verify user owns this review
    $checkStmt = $pdo->prepare("
        SELECT user_id FROM reviews WHERE id = ?
    ");
    $checkStmt->execute([$reviewId]);
    $review = $checkStmt->fetch();

    if (!$review) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Review not found']);
        exit;
    }

    if ($review['user_id'] != $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not authorized to delete this review']);
        exit;
    }

    // Delete the review
    $deleteStmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
    $deleteStmt->execute([$reviewId]);

    echo json_encode([
        'success' => true,
        'message' => 'Review deleted successfully'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
