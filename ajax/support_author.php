<?php
// ajax/support_author.php - Patreon/Coffee payment endpoint
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/payment_gateway.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'message' => 'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $author_id = intval($data['author_id'] ?? 0);
    $amount = floatval($data['amount'] ?? 0);
    $method = $data['method'] ?? 'patreon'; // patreon or coffee
    $message = trim($data['message'] ?? '');

    if (!$author_id || $amount <= 0) {
        exit(json_encode(['ok' => false, 'message' => 'Invalid amount or author']));
    }

    if ($method === 'patreon') {
        $result = PaymentGateway::createPatreonPayment($author_id, $amount, $message);
    } elseif ($method === 'coffee') {
        $result = PaymentGateway::createCoffeePayment($author_id, $amount, $message);
    } else {
        exit(json_encode(['ok' => false, 'message' => 'Invalid payment method']));
    }

    exit(json_encode($result));
} catch (Exception $e) {
    exit(json_encode(['ok' => false, 'message' => $e->getMessage()]));
}
?>
