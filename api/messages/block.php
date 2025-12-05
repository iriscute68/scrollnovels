<?php
/**
 * api/messages/block.php - Block a user from messaging
 */
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

requireLogin();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$blocked_user_id = (int)($input['user_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$blocked_user_id || $blocked_user_id == $user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user_id']);
    exit;
}

try {
    // Create blocks table if doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_blocks (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        blocker_id INT UNSIGNED NOT NULL,
        blocked_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_block (blocker_id, blocked_id),
        INDEX idx_blocker (blocker_id),
        INDEX idx_blocked (blocked_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Check if already blocked
    $stmt = $pdo->prepare("SELECT id FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$user_id, $blocked_user_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        http_response_code(400);
        echo json_encode(['error' => 'Already blocked']);
        exit;
    }

    // Add block
    $stmt = $pdo->prepare("INSERT INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $blocked_user_id]);

    echo json_encode([
        'ok' => true,
        'blocked_user_id' => $blocked_user_id,
        'status' => 'blocked',
        'timestamp' => date('c')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
