<?php
// api/webhooks/patreon.php - Patreon webhook handler
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/../config/db.php';

// Get the webhook body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verify webhook signature (if you have PATREON_WEBHOOK_SECRET set)
$signature = $_SERVER['HTTP_X_PATREON_SIGNATURE'] ?? '';
$webhook_secret = getenv('PATREON_WEBHOOK_SECRET');

if ($webhook_secret && $signature) {
    $expected_sig = hash_hmac('md5', $input, $webhook_secret);
    if (!hash_equals($expected_sig, $signature)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid signature']);
        exit;
    }
}

if (!$data || !isset($data['data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid webhook data']);
    exit;
}

try {
    // Create tables if needed
    $pdo->exec("CREATE TABLE IF NOT EXISTS patreon_webhooks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id VARCHAR(255) UNIQUE NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        webhook_data LONGTEXT,
        processed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS supporters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supporter_id INT NOT NULL,
        author_id INT NOT NULL,
        tip_amount DECIMAL(10, 2) DEFAULT 0,
        patreon_tier VARCHAR(100),
        kofi_reference VARCHAR(255),
        patreon_pledge_id VARCHAR(255),
        status ENUM('active', 'cancelled', 'pending') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_support (supporter_id, author_id),
        INDEX idx_author (author_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $event_id = $data['data']['id'] ?? '';
    $event_type = $data['data']['type'] ?? '';
    
    // Store webhook for processing
    $stmt = $pdo->prepare("
        INSERT INTO patreon_webhooks (event_id, event_type, webhook_data)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            webhook_data = VALUES(webhook_data),
            created_at = NOW()
    ");
    
    $stmt->execute([
        $event_id,
        $event_type,
        json_encode($data)
    ]);
    
    // Process webhook based on type
    switch ($event_type) {
        case 'pledges:create':
        case 'pledges:update':
            // Handle new or updated pledge
            if (isset($data['data']['relationships']['patron']['data']['id'])) {
                $patron_id = $data['data']['relationships']['patron']['data']['id'];
                // In production, you'd fetch patron details from Patreon API
                // For now, just log it
                error_log('Patreon pledge update: ' . json_encode($data));
            }
            break;
            
        case 'pledges:delete':
            // Handle cancelled pledge
            if (isset($data['data']['relationships']['patron']['data']['id'])) {
                $patron_id = $data['data']['relationships']['patron']['data']['id'];
                // Mark supporter as cancelled
                error_log('Patreon pledge cancelled: ' . json_encode($data));
            }
            break;
    }
    
    // Mark as processed
    $stmt = $pdo->prepare("UPDATE patreon_webhooks SET processed = 1 WHERE event_id = ?");
    $stmt->execute([$event_id]);
    
    echo json_encode(['success' => true, 'message' => 'Webhook processed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
