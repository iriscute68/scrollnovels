<?php
// api/submit-report.php - Submit story report

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$storyId = (int)($input['story_id'] ?? 0);
$reason = trim($input['reason'] ?? '');
$description = trim($input['description'] ?? '');

if (!$storyId || !$reason) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Story ID and reason required']);
    exit;
}

try {
    // Create story_reports table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS story_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        story_id INT NOT NULL,
        reporter_id INT NOT NULL,
        reason VARCHAR(255) NOT NULL,
        description LONGTEXT,
        status ENUM('pending', 'reviewed', 'dismissed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (status),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Check if user already reported this story
    $stmt = $pdo->prepare("
        SELECT id FROM story_reports 
        WHERE story_id = ? AND reporter_id = ? AND status = 'pending'
        LIMIT 1
    ");
    $stmt->execute([$storyId, $userId]);
    
    if ($stmt->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'You have already reported this story']);
        exit;
    }
    
    // Create report
    $stmt = $pdo->prepare("
        INSERT INTO story_reports (story_id, reporter_id, reason, description, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$storyId, $userId, $reason, $description ?: null]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Report submitted successfully. Our team will review it shortly.',
        'report_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    error_log('Report submission error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
