<?php
// api/reviews/get-review.php - Get user's review for a story
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/../config/db.php';

$user_id = $_SESSION['user_id'] ?? 0;
$story_id = (int)($_GET['story_id'] ?? 0);

if (!$story_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Story ID required']);
    exit;
}

try {
    if (!$user_id) {
        echo json_encode(['success' => true, 'data' => null]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, rating, review_text, created_at, updated_at
        FROM reviews
        WHERE story_id = ? AND user_id = ?
    ");
    $stmt->execute([$story_id, $user_id]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $review]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
