<?php
/**
 * API: Add Payment Method
 * Purpose: Save a new payment method for the user (e.g., after successful Paystack payment)
 * Method: POST
 * Required: card_brand, last_four, paystack_authorization_code
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$userId = $_SESSION['user_id'];
$cardBrand = trim($input['card_brand'] ?? '');
$lastFour = trim($input['last_four'] ?? '');
$authCode = trim($input['paystack_authorization_code'] ?? '');

// Validate input
if (empty($cardBrand) || empty($lastFour) || empty($authCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    // Check if this card already exists
    $stmt = $pdo->prepare("
        SELECT id FROM user_payment_methods 
        WHERE user_id = ? AND last_four = ? AND paystack_authorization_code = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $lastFour, $authCode]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo json_encode([
            'success' => true,
            'message' => 'Card already saved',
            'method_id' => $existing['id']
        ]);
        exit;
    }

    // Insert new payment method
    $stmt = $pdo->prepare("
        INSERT INTO user_payment_methods (user_id, card_brand, last_four, paystack_authorization_code, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $cardBrand, $lastFour, $authCode]);
    $methodId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Payment method added successfully',
        'method_id' => $methodId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    error_log('Add payment method error: ' . $e->getMessage());
}
?>
