<?php
// api/reviews/report-review.php - Report an inappropriate review
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$reporter_id = $_SESSION['user_id'];
$review_id = (int)($_POST['review_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if (!$review_id || !$reason) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Review ID and reason required']);
    exit;
}

if (strlen($reason) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Reason must be at least 10 characters']);
    exit;
}

try {
    // Check if user already reported this review
    $check = $pdo->prepare("SELECT id FROM review_reports WHERE review_id = ? AND reporter_id = ?");
    $check->execute([$review_id, $reporter_id]);
    
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You have already reported this review']);
        exit;
    }

    // Insert report
    $insert = $pdo->prepare("
        INSERT INTO review_reports (review_id, reporter_id, reason)
        VALUES (?, ?, ?)
    ");
    $insert->execute([$review_id, $reporter_id, $reason]);

    echo json_encode(['success' => true, 'message' => 'Review reported successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
