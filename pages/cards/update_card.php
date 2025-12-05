<?php
/**
 * Update Card - Set default or other updates
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!csrf_check()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CSRF invalid']);
    exit;
}

$card_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = $_POST['action'] ?? '';

if (!$card_id) {
    echo json_encode(['success' => false, 'message' => 'Missing card ID']);
    exit;
}

try {
    if ($action === 'set_default') {
        // Unset all other default cards for this user
        $pdo->prepare("UPDATE saved_cards SET is_default = 0 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
        
        // Set this as default
        $stmt = $pdo->prepare("UPDATE saved_cards SET is_default = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$card_id, $_SESSION['user_id']]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Default card updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Card not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    error_log('Update card error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating card']);
}

?>
