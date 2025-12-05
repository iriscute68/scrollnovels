<?php
// api/reviews/get-story-reviews.php - Get all reviews for a story with ratings
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/../config/db.php';

$story_id = (int)($_GET['story_id'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

if (!$story_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Story ID required']);
    exit;
}

try {
    // Get reviews with user info
    $stmt = $pdo->prepare("
        SELECT r.id, r.rating, r.review_text, r.created_at, r.updated_at,
               u.id as user_id, u.username, u.profile_image
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.story_id = ?
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$story_id, $limit, $offset]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get average rating and count
    $stats = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews,
               SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
               SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
               SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
               SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
               SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
        FROM reviews
        WHERE story_id = ?
    ");
    $stats->execute([$story_id]);
    $rating_stats = $stats->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $reviews,
        'stats' => $rating_stats,
        'page' => $page,
        'limit' => $limit
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
