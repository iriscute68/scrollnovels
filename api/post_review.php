<?php
// api/post_review.php - accept review POSTs (JSON/form-data) and upsert into reviews
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Handle both JSON and form-data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = [];

if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $input = $_POST;
}

$story_id = (int)($input['story_id'] ?? 0);
$rating = (int)($input['rating'] ?? 5);
$content = trim($input['content'] ?? '');

if (!$story_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid story']);
    exit;
}

if (!$content || strlen($content) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Review content required']);
    exit;
}

try {
    // Check if user already has a review for this story
    $checkStmt = $pdo->prepare("SELECT id, rating, content FROM reviews WHERE story_id = ? AND user_id = ? LIMIT 1");
    $checkStmt->execute([$story_id, $_SESSION['user_id']]);
    $existingReview = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $isUpdate = !empty($existingReview);
    
    // Validate rating is between 1 and 5
    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
        exit;
    }
    
    // upsert review (unique key uq_review on story_id,user_id)
    $stmt = $pdo->prepare("INSERT INTO reviews (story_id, user_id, rating, content, created_at) 
        VALUES (?, ?, ?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE 
        rating = VALUES(rating), 
        content = VALUES(content)");
    $ok = $stmt->execute([$story_id, $_SESSION['user_id'], $rating, $content]);

    // return the authored review record
    $stmt = $pdo->prepare("SELECT r.*, u.username, u.profile_image FROM reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.story_id = ? AND r.user_id = ? LIMIT 1");
    $stmt->execute([$story_id, $_SESSION['user_id']]);
    $rev = $stmt->fetch(PDO::FETCH_ASSOC);

    // notify author (if not the reviewer)
    $stmt = $pdo->prepare("SELECT author_id, title FROM stories WHERE id = ?");
    $stmt->execute([$story_id]);
    $story = $stmt->fetch();
    if ($story && $story['author_id'] != $_SESSION['user_id']) {
        $notifMsg = $isUpdate ? "updated their review on your story" : "left a review on your story";
        notify($pdo, $story['author_id'], $_SESSION['user_id'], 'review', $notifMsg . ": " . substr($content, 0, 80), "/pages/book.php?id={$story_id}");
    }

    http_response_code(200);
    echo json_encode(['success' => (bool)$ok, 'review' => $rev, 'isUpdate' => $isUpdate]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}