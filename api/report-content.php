<?php
/**
 * API: Report content (posts, replies, stories, comments)
 */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in first']);
    exit;
}

$userId = $_SESSION['user_id'];
$type = $_POST['type'] ?? '';
$contentId = intval($_POST['content_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$details = trim($_POST['details'] ?? '');

$validTypes = ['post', 'reply', 'story', 'chapter', 'comment', 'review', 'user'];
if (!in_array($type, $validTypes) || !$contentId || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request. Please select a reason.']);
    exit;
}

try {
    // Create content_reports table if doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        content_type VARCHAR(50) NOT NULL,
        content_id INT NOT NULL,
        reason VARCHAR(100) NOT NULL,
        details TEXT,
        status ENUM('pending', 'reviewed', 'resolved', 'dismissed') DEFAULT 'pending',
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        admin_notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_report (reporter_id, content_type, content_id),
        INDEX (status),
        INDEX (content_type, content_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Check if user already reported this content
    $stmt = $pdo->prepare("SELECT id FROM content_reports WHERE reporter_id = ? AND content_type = ? AND content_id = ?");
    $stmt->execute([$userId, $type, $contentId]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already reported this content']);
        exit;
    }
    
    // Insert report
    $stmt = $pdo->prepare("INSERT INTO content_reports (reporter_id, content_type, content_id, reason, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $type, $contentId, $reason, $details]);
    
    echo json_encode(['success' => true, 'message' => 'Report submitted successfully']);
    
} catch (Exception $e) {
    error_log('Report content error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
