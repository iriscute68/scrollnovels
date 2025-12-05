<?php
/**
 * Delete Card - Remove a saved card
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

if (!$card_id) {
    echo json_encode(['success' => false, 'message' => 'Missing card ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM saved_cards WHERE id = ? AND user_id = ?");
    $stmt->execute([$card_id, $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Card deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Card not found']);
    }
} catch (Exception $e) {
    error_log('Delete card error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting card']);
}

?>
