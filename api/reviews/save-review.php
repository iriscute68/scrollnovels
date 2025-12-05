<?php
// api/reviews/save-review.php - Submit or update a story review
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$story_id = (int)($_POST['story_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$review_text = trim($_POST['review_text'] ?? '');

if (!$story_id || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid story_id or rating']);
    exit;
}

try {
    // Check if review already exists
    $check = $pdo->prepare("SELECT id FROM reviews WHERE story_id = ? AND user_id = ?");
    $check->execute([$story_id, $user_id]);
    $existing = $check->fetch();

    if ($existing) {
        // Update existing review (updated_at auto-updates if column exists with ON UPDATE)
        $update = $pdo->prepare("
            UPDATE reviews 
            SET rating = ?, review_text = ?
            WHERE story_id = ? AND user_id = ?
        ");
        $update->execute([$rating, $review_text ?: null, $story_id, $user_id]);
        $action = 'updated';
    } else {
        // Insert new review
        $insert = $pdo->prepare("
            INSERT INTO reviews (story_id, user_id, rating, review_text)
            VALUES (?, ?, ?, ?)
        ");
        $insert->execute([$story_id, $user_id, $rating, $review_text ?: null]);
        $action = 'created';
    }

    echo json_encode([
        'success' => true,
        'action' => $action,
        'message' => ucfirst($action) . ' review successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
