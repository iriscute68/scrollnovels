<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    global $pdo;
    
    // Verify webhook signature
    $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
    $body = file_get_contents('php://input');
    
    $paystackKey = getenv('PAYSTACK_SECRET_KEY') ?? '';
    $hash = hash_hmac('sha512', $body, $paystackKey);
    
    if ($hash !== $signature) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
    
    $payload = json_decode($body, true);
    $event = $payload['event'] ?? '';
    $data = $payload['data'] ?? [];
    
    if ($event === 'charge.success') {
        // Payment successful
        $reference = $data['reference'] ?? '';
        $amount = $data['amount'] / 100; // Convert from kobo to naira
        $customFields = $data['metadata']['custom_fields'] ?? [];
        
        // Find author or user
        $authorId = null;
        foreach ($customFields as $field) {
            if ($field['variable_name'] === 'author_id') {
                $authorId = $field['value'];
                break;
            }
        }
        
        if (!$authorId) {
            // Try to find from transaction reference
            $stmt = $pdo->prepare("SELECT author_id FROM transactions WHERE reference_id = ? LIMIT 1");
            $stmt->execute([$reference]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);
            $authorId = $tx['author_id'] ?? null;
        }
        
        if ($authorId) {
            // Get or create author balance
            $stmt = $pdo->prepare("SELECT * FROM author_balances WHERE author_id = ?");
            $stmt->execute([$authorId]);
            $balance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$balance) {
                $stmt = $pdo->prepare("INSERT INTO author_balances (author_id, balance, created_at) VALUES (?, 0, NOW())");
                $stmt->execute([$authorId]);
            }
            
            // Add to balance
            $stmt = $pdo->prepare("UPDATE author_balances SET balance = balance + ?, total_earned = total_earned + ? WHERE author_id = ?");
            $stmt->execute([$amount, $amount, $authorId]);
            
            // Record transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (author_id, type, amount, description, reference_id, status, created_at) VALUES (?, 'paystack_payment', ?, ?, ?, 'completed', NOW())");
            $stmt->execute([$authorId, $amount, "Paystack payment", $reference]);
            
            // Log
            error_log("[PAYSTACK] Payment successful: Author=$authorId, Amount=$amount, Ref=$reference");
        }
    } elseif ($event === 'charge.failed') {
        $reference = $data['reference'] ?? '';
        error_log("[PAYSTACK] Payment failed: $reference");
    } elseif ($event === 'invoice.create') {
        error_log("[PAYSTACK] Invoice created");
    }
    
    // Always return 200 to acknowledge receipt
    http_response_code(200);
    echo json_encode(['status' => 'received']);
    
} catch (Exception $e) {
    error_log("[PAYSTACK ERROR] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
