<?php
/**
 * api/report-review.php
 * Handle review reports for moderation
 */
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$reporterId = $_SESSION['user_id'];
$reviewId = (int)($_POST['review_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if (!$reviewId) {
    echo json_encode(['success' => false, 'error' => 'Invalid review ID']);
    exit;
}

if (!$reason || strlen($reason) < 5) {
    echo json_encode(['success' => false, 'error' => 'Please provide a reason (at least 5 characters)']);
    exit;
}

try {
    // Verify review exists
    $checkStmt = $pdo->prepare("SELECT id FROM reviews WHERE id = ?");
    $checkStmt->execute([$reviewId]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Review not found']);
        exit;
    }

    // Check if user already reported this review
    $existingStmt = $pdo->prepare("
        SELECT id FROM review_reports 
        WHERE review_id = ? AND reporter_id = ?
    ");
    $existingStmt->execute([$reviewId, $reporterId]);
    
    if ($existingStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You have already reported this review']);
        exit;
    }

    // Insert report
    $reportStmt = $pdo->prepare("
        INSERT INTO review_reports (review_id, reporter_id, reason) 
        VALUES (?, ?, ?)
    ");
    $reportStmt->execute([$reviewId, $reporterId, $reason]);

    echo json_encode([
        'success' => true,
        'message' => 'Review reported successfully. Our team will review it shortly.'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
