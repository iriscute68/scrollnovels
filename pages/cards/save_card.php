<?php
/**
 * Save Card - Verifies Paystack transaction and stores authorization code
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
    echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    exit;
}

$reference = $_POST['reference'] ?? '';
$reference = preg_replace('/[^a-zA-Z0-9-_]/', '', $reference);

if (!$reference) {
    echo json_encode(['success' => false, 'message' => 'Missing reference']);
    exit;
}

// Verify transaction with Paystack
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.paystack.co/transaction/verify/' . urlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $PAYSTACK_SECRET,
        'Accept: application/json'
    ]
]);

$response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    error_log('Paystack verify curl error: ' . $curl_error);
    echo json_encode(['success' => false, 'message' => 'Network error']);
    exit;
}

$result = json_decode($response, true);

if (!isset($result['status']) || $result['status'] !== true) {
    error_log('Paystack verify failed: ' . print_r($result, true));
    echo json_encode(['success' => false, 'message' => 'Verification failed']);
    exit;
}

$data = $result['data'] ?? [];

if (($data['status'] ?? '') !== 'success') {
    echo json_encode(['success' => false, 'message' => 'Transaction not successful']);
    exit;
}

// Extract authorization
$auth = $data['authorization'] ?? null;
if (!$auth || empty($auth['authorization_code'])) {
    echo json_encode(['success' => false, 'message' => 'No authorization code received']);
    exit;
}

try {
    // Create saved_cards table if not exists
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS saved_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        authorization_code VARCHAR(255) NOT NULL UNIQUE,
        card_last_4 VARCHAR(4),
        card_brand VARCHAR(50),
        card_exp_month INT,
        card_exp_year INT,
        bank VARCHAR(100),
        is_default BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Insert or update saved card
    $stmt = $pdo->prepare("
    INSERT INTO saved_cards 
    (user_id, authorization_code, card_last_4, card_brand, card_exp_month, card_exp_year, bank)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
    card_last_4 = VALUES(card_last_4),
    card_brand = VALUES(card_brand),
    card_exp_month = VALUES(card_exp_month),
    card_exp_year = VALUES(card_exp_year),
    bank = VALUES(bank)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $auth['authorization_code'],
        $auth['last4'] ?? null,
        $auth['card_type'] ?? null,
        $auth['exp_month'] ?? null,
        $auth['exp_year'] ?? null,
        $auth['bank'] ?? null
    ]);

    echo json_encode(['success' => true, 'message' => 'Card saved successfully']);
} catch (Exception $e) {
    error_log('Save card error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error saving card: ' . $e->getMessage()]);
}

?>
