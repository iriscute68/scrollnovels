<?php
// api/webhooks/kofi.php - Ko-fi webhook handler
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/../config/db.php';

// Get the webhook data
$input = file_get_contents('php://input');
parse_str($input, $data);

// Verify webhook token (if you have KOFI_WEBHOOK_TOKEN set)
$token = $data['verification_token'] ?? '';
$kofi_token = getenv('KOFI_WEBHOOK_TOKEN');

if ($kofi_token && $token !== $kofi_token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

if (!isset($data['data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid webhook data']);
    exit;
}

try {
    $payload = json_decode($data['data'], true);
    
    // Create tables if needed
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
    
    $type = $payload['type'] ?? '';
    $message = $payload['message'] ?? [];
    
    // Extract relevant data
    $sender_name = $message['sender_name'] ?? 'Anonymous';
    $amount = floatval($message['amount'] ?? 0);
    $is_public = $message['is_public'] ?? true;
    $message_text = $message['message'] ?? '';
    $timestamp = $message['timestamp'] ?? date('Y-m-d H:i:s');
    $transaction_id = $message['transaction_id'] ?? '';
    
    // Find supporter by Ko-fi email or create as guest
    $supporter_email = $message['from_name'] ?? '';
    $supporter_id = null;
    
    if ($supporter_email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$supporter_email]);
        $user = $stmt->fetch();
        if ($user) {
            $supporter_id = $user['id'];
        }
    }
    
    // Try to extract author ID from message (e.g., "For: @author_username")
    $author_id = null;
    if (preg_match('/@([a-zA-Z0-9_]+)/i', $message_text, $matches)) {
        $username = $matches[1];
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $author = $stmt->fetch();
        if ($author) {
            $author_id = $author['id'];
        }
    }
    
    // If we have both supporter and author, record the support
    if ($supporter_id && $author_id) {
        $stmt = $pdo->prepare("
            INSERT INTO supporters (supporter_id, author_id, tip_amount, kofi_reference, status)
            VALUES (?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE 
                tip_amount = tip_amount + VALUES(tip_amount),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $supporter_id,
            $author_id,
            $amount,
            $transaction_id
        ]);
    }
    
    // Log the donation
    error_log("Ko-fi donation: $sender_name donated \$$amount. Message: $message_text");
    
    echo json_encode(['success' => true, 'message' => 'Webhook processed']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
