<?php
/**
 * api/messages/unblock.php - Unblock a previously blocked user
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
$unblock_user_id = (int)($input['user_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$unblock_user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user_id']);
    exit;
}

try {
    // Remove block
    $stmt = $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$user_id, $unblock_user_id]);

    if ($stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Block not found']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'unblocked_user_id' => $unblock_user_id,
        'status' => 'unblocked',
        'timestamp' => date('c')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
