<?php
// api/report-user.php - Report a user
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$reportedUserId = (int)($input['user_id'] ?? 0);
$reason = trim($input['reason'] ?? '');
$category = trim($input['category'] ?? '');
$details = trim($input['details'] ?? '');

// Valid report categories
$validCategories = [
    'harassment' => 'Harassment',
    'inappropriate' => 'Inappropriate Content',
    'spam' => 'Spam',
    'scam' => 'Scam/Fraud',
    'hate_speech' => 'Hate Speech',
    'impersonation' => 'Impersonation',
    'other' => 'Other'
];

if (!$reportedUserId || $reportedUserId == $userId) {
    echo json_encode(['success' => false, 'error' => 'Invalid user']);
    exit;
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'error' => 'Reason is required']);
    exit;
}

if (empty($category) || !isset($validCategories[$category])) {
    echo json_encode(['success' => false, 'error' => 'Invalid report category']);
    exit;
}

try {
    // Check if already reported today (prevent spam)
    $stmt = $pdo->prepare("
        SELECT 1 FROM user_reports 
        WHERE reporter_id = ? AND reported_id = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        LIMIT 1
    ");
    $stmt->execute([$userId, $reportedUserId]);
    if ($stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'error' => 'You already reported this user today']);
        exit;
    }

    // Create report with category
    $stmt = $pdo->prepare("
        INSERT INTO user_reports (reporter_id, reported_id, reason, category, details)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $reportedUserId, $reason, $category, $details ?: null]);
    echo json_encode(['success' => true, 'message' => 'Report submitted successfully']);
} catch (Exception $e) {
    error_log('Report user error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
