<?php
// api/block-user.php - Block or unblock a user
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
$targetUserId = (int)($input['user_id'] ?? 0);

if (!$targetUserId || $targetUserId == $userId) {
    echo json_encode(['success' => false, 'error' => 'Invalid user']);
    exit;
}

try {
    // Check if already blocked
    $stmt = $pdo->prepare("SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1");
    $stmt->execute([$userId, $targetUserId]);
    $isBlocked = (bool)$stmt->fetchColumn();

    if ($isBlocked) {
        // Unblock
        $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?")->execute([$userId, $targetUserId]);
        echo json_encode(['success' => true, 'action' => 'unblocked']);
    } else {
        // Block
        $pdo->prepare("INSERT INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)")->execute([$userId, $targetUserId]);
        echo json_encode(['success' => true, 'action' => 'blocked']);
    }
} catch (Exception $e) {
    error_log('Block user error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
