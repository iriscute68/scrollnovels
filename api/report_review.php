<?php
// api/report_review.php - accept POST JSON { review_id, reason, details }
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$review_id = (int)($payload['review_id'] ?? 0);
$reason = trim($payload['reason'] ?? '');
$details = trim($payload['details'] ?? '');

if (!$review_id || !$reason) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    // Ensure table
    $pdo->exec("CREATE TABLE IF NOT EXISTS review_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        review_id INT NOT NULL,
        reporter_id INT NOT NULL,
        reason VARCHAR(150) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare('INSERT INTO review_reports (review_id, reporter_id, reason, details) VALUES (?, ?, ?, ?)');
    $stmt->execute([$review_id, $_SESSION['user_id'], $reason, $details]);

    // Notify first admin
    $adminId = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
    if ($adminId) {
        require_once dirname(__DIR__) . '/inc/notify.php';
        $noteTitle = 'Review Report (ID ' . $review_id . ')';
        $noteBody = 'Reason: ' . $reason . "\n\n" . ($details ?: 'No details provided');
        notify_user((int)$adminId, $noteTitle, $noteBody, '/admin/reports.php', true);
    }

    echo json_encode(['success' => true, 'message' => 'Report submitted.']);
} catch (Exception $e) {
    http_response_code(500);
    error_log('report_review error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

?>
