<?php
// api/reviews/delete-review.php - Delete user's review
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

if (!$story_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Story ID required']);
    exit;
}

try {
    $delete = $pdo->prepare("DELETE FROM reviews WHERE story_id = ? AND user_id = ?");
    $delete->execute([$story_id, $user_id]);

    echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
