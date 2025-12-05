<?php
// lib/payment_gateway.php - Patreon & Coffee payment integrations
require_once __DIR__ . '/../config.php';

class PaymentGateway {
    
    // Patreon Integration
    public static function createPatreonPayment($user_id, $amount, $message = '') {
        global $pdo;
        
        try {
            // Create payment record
            $stmt = $pdo->prepare("
                INSERT INTO payments (user_id, amount, method, status, gateway, metadata, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $metadata = json_encode(['message' => $message, 'platform' => 'patreon']);
            $stmt->execute([$user_id, $amount, 'patreon', 'pending', 'patreon', $metadata]);
            
            $payment_id = $pdo->lastInsertId();
            
            // Generate Patreon pledge link
            $patreon_link = "https://patreon.com/scrollnovels?amount=" . ($amount * 100);
            
            return [
                'ok' => true,
                'payment_id' => $payment_id,
                'redirect_url' => $patreon_link,
                'amount' => $amount
            ];
        } catch (Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // Coffee Support Integration
    public static function createCoffeePayment($user_id, $amount, $message = '') {
        global $pdo;
        
        try {
            // Create payment record
            $stmt = $pdo->prepare("
                INSERT INTO payments (user_id, amount, method, status, gateway, metadata, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $metadata = json_encode(['message' => $message, 'platform' => 'coffee', 'coffee_count' => $amount]);
            $stmt->execute([$user_id, $amount * 5, 'coffee', 'pending', 'coffee', $metadata]);
            
            $payment_id = $pdo->lastInsertId();
            
            // Generate Coffee transaction (would integrate with Buy Me A Coffee API)
            $coffee_data = [
                'supporter_message' => $message,
                'coffees' => $amount,
                'amount_usd' => $amount * 5,
                'return_url' => "https://{$_SERVER['HTTP_HOST']}/ajax/coffee_callback.php?payment_id=" . $payment_id
            ];
            
            return [
                'ok' => true,
                'payment_id' => $payment_id,
                'coffee_data' => $coffee_data,
                'amount' => $amount * 5
            ];
        } catch (Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // Verify payment status
    public static function verifyPayment($payment_id) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Mark payment as completed
    public static function completePayment($payment_id, $transaction_id = null) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET status = 'completed', transaction_id = ?, completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$transaction_id, $payment_id]);
        
        // Get payment details
        $payment = self::verifyPayment($payment_id);
        
        // Add funds to author balance
        $pdo->prepare("
            INSERT INTO author_balances (user_id, amount, source, created_at)
            VALUES (?, ?, 'donation', NOW())
            ON DUPLICATE KEY UPDATE amount = amount + ?
        ")->execute([$payment['user_id'], $payment['amount'], $payment['amount']]);
        
        return true;
    }

    // Get author balance
    public static function getAuthorBalance($user_id) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT COALESCE(amount, 0) as balance FROM author_balances WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['balance'] ?? 0;
    }
}
?>
