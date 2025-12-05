<?php
// api/paystack-donate.php - Initialize Paystack donation with 80/20 author-platform split

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/paystack.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Login required']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$amount = (float)($input['amount'] ?? 0);
$bookId = (int)($input['book_id'] ?? 0);
$message = trim($input['message'] ?? '');
$saveCard = (bool)($input['save_card'] ?? false);
$paymentMethodId = (int)($input['payment_method_id'] ?? 0);

// Validate
if ($amount < 5) {
    echo json_encode(['success' => false, 'error' => 'Minimum donation is $5']);
    exit;
}

// Book donation always goes to story author (80%)
if (!$bookId) {
    echo json_encode(['success' => false, 'error' => 'Story ID required for donations']);
    exit;
}

try {
    // Get user info
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Get story author
    $stmt = $pdo->prepare("SELECT author_id FROM stories WHERE id = ?");
    $stmt->execute([$bookId]);
    $story = $stmt->fetch();
    
    if (!$story) {
        echo json_encode(['success' => false, 'error' => 'Story not found']);
        exit;
    }
    
    $authorId = $story['author_id'];
    
    // Calculate split: 80% to author, 20% to platform
    $authorAmount = $amount * 0.80;
    $platformAmount = $amount * 0.20;
    
    // Create donation record with payment status
    $stmt = $pdo->prepare("
        INSERT INTO donations (
            donor_id, recipient_id, amount, author_amount, platform_amount,
            type, message, status, story_id, payment_method_id, 
            save_payment_method, created_at
        )
        VALUES (?, ?, ?, ?, ?, 'story', ?, 'pending', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId, $authorId, $amount, $authorAmount, $platformAmount,
        $message ?: null, $bookId, ($paymentMethodId ?: null), $saveCard ? 1 : 0
    ]);
    $donationId = $pdo->lastInsertId();
    
    // Prepare metadata for Paystack with split information
    $metadata = [
        'donation_id' => $donationId,
        'donor_id' => $userId,
        'recipient_id' => $authorId,
        'story_id' => $bookId,
        'author_amount' => $authorAmount,
        'platform_amount' => $platformAmount,
        'split_percentage' => '80-20'
    ];
    
    // Initialize payment with author's Paystack account (80/20 split)
    $response = initializePaystackPayment(
        $user['email'], 
        $amount, 
        $metadata
    );
    
    if (!isset($response['status']) || !$response['status']) {
        echo json_encode([
            'success' => false,
            'error' => $response['message'] ?? 'Failed to initialize payment'
        ]);
        exit;
    }
    
    // Return payment info
    echo json_encode([
        'success' => true,
        'authorization_url' => $response['data']['authorization_url'] ?? null,
        'access_code' => $response['data']['access_code'] ?? null,
        'reference' => $response['data']['reference'] ?? null,
        'donation_id' => $donationId,
        'amount' => $amount,
        'author_amount' => $authorAmount,
        'platform_amount' => $platformAmount
    ]);
    
} catch (Exception $e) {
    error_log('Paystack donate error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>

