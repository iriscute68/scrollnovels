<?php
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, card_brand, last_four, created_at 
        FROM user_payment_methods 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $methods = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'methods' => $methods
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
