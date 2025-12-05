<?php
// api/paystack-verify.php - Verify Paystack payment and credit earnings (80/20 split)

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

$reference = trim($_GET['reference'] ?? '');
$donationId = (int)($_GET['donation_id'] ?? 0);

if (!$reference || !$donationId) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // Verify with Paystack
    $paymentData = verifyPaystackPayment($reference);
    
    if (!isset($paymentData['status']) || !$paymentData['status']) {
        // Update donation as failed
        $stmt = $pdo->prepare("
            UPDATE donations 
            SET status = 'failed', paystack_reference = ? 
            WHERE id = ?
        ");
        $stmt->execute([$reference, $donationId]);
        
        echo json_encode([
            'success' => false,
            'error' => $paymentData['message'] ?? 'Payment verification failed'
        ]);
        exit;
    }
    
    // Check payment status
    if ($paymentData['data']['status'] !== 'success') {
        $stmt = $pdo->prepare("
            UPDATE donations 
            SET status = 'failed', paystack_reference = ? 
            WHERE id = ?
        ");
        $stmt->execute([$reference, $donationId]);
        
        echo json_encode([
            'success' => false,
            'error' => 'Payment was not successful',
            'status' => $paymentData['data']['status']
        ]);
        exit;
    }
    
    // Get actual payment amount from Paystack
    $paymentAmount = $paymentData['data']['amount'] / 100; // Convert from kobo
    
    // Get donation details
    $stmt = $pdo->prepare("
        SELECT donor_id, recipient_id, amount, author_amount, platform_amount, 
               type, story_id, payment_method_id, save_payment_method
        FROM donations 
        WHERE id = ?
    ");
    $stmt->execute([$donationId]);
    $donation = $stmt->fetch();
    
    if (!$donation) {
        echo json_encode(['success' => false, 'error' => 'Donation not found']);
        exit;
    }
    
    // CRITICAL: Update donation with confirmation timestamp (ensures earnings only after payment confirmed)
    $stmt = $pdo->prepare("
        UPDATE donations 
        SET status = 'completed', 
            paystack_reference = ?,
            payment_confirmed_at = NOW(),
            can_be_withdrawn = 1,
            author_amount = ?,
            platform_amount = ?
        WHERE id = ?
    ");
    
    $authorAmount = $donation['author_amount'] ?? ($paymentAmount * 0.80);
    $platformAmount = $donation['platform_amount'] ?? ($paymentAmount * 0.20);
    
    $stmt->execute([
        $reference, 
        $authorAmount, 
        $platformAmount, 
        $donationId
    ]);
    
    // CRITICAL: Save payment method if requested (for future donations)
    if ($donation['save_payment_method'] && $donation['payment_method_id'] === null) {
        $paymentMethod = $paymentData['data']['authorization'];
        $cardBrand = $paymentMethod['card_type'] ?? 'unknown';
        $lastFour = substr($paymentMethod['authorization_code'], -4) ?? 'xxxx';
        
        $stmt = $pdo->prepare("
            INSERT INTO user_payment_methods (user_id, card_brand, last_four, paystack_authorization_code, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $donation['donor_id'],
            $cardBrand,
            $lastFour,
            $paymentMethod['authorization_code'] ?? null
        ]);
        
        $paymentMethodId = $pdo->lastInsertId();
        
        // Update donation with saved payment method
        $stmt = $pdo->prepare("UPDATE donations SET payment_method_id = ? WHERE id = ?");
        $stmt->execute([$paymentMethodId, $donationId]);
    }
    
    // CRITICAL: Credit author earnings ONLY after payment_confirmed_at is set
    if ($donation['recipient_id']) {
        // Update author's total earnings and available balance
        $stmt = $pdo->prepare("
            UPDATE users 
            SET total_earnings = total_earnings + ?,
                available_balance = available_balance + ?
            WHERE id = ?
        ");
        $stmt->execute([$authorAmount, $authorAmount, $donation['recipient_id']]);
        
        // Log transaction for tracking
        $stmt = $pdo->prepare("
            INSERT INTO user_transactions (user_id, transaction_type, amount, reference, created_at)
            VALUES (?, 'donation_received', ?, ?, NOW())
        ");
        $stmt->execute([$donation['recipient_id'], $authorAmount, $reference]);
    }
    
    // Platform receives 20%
    $stmt = $pdo->prepare("
        INSERT INTO platform_revenue (amount, source, reference, created_at)
        VALUES (?, 'donation', ?, NOW())
    ");
    $stmt->execute([$platformAmount, $reference]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified and earnings credited!',
        'donation_id' => $donationId,
        'amount' => $paymentAmount,
        'author_earnings' => $authorAmount,
        'platform_revenue' => $platformAmount
    ]);
    
} catch (Exception $e) {
    error_log('Paystack verify error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>

