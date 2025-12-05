<?php
// admin/ajax/adjust_balance.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');
session_start();

if (!isApprovedAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    global $pdo;
    
    $authorId = $_POST['author_id'] ?? 0;
    $amount = (float)($_POST['amount'] ?? 0);
    $reason = $_POST['reason'] ?? 'Manual adjustment';
    $type = $_POST['type'] ?? 'add'; // add or subtract
    
    if (!$authorId || $amount <= 0) {
        echo json_encode(['error' => 'Invalid author_id or amount']);
        exit;
    }
    
    // Get or create author balance
    $stmt = $pdo->prepare("SELECT * FROM author_balances WHERE author_id = ?");
    $stmt->execute([$authorId]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$balance) {
        $stmt = $pdo->prepare("INSERT INTO author_balances (author_id, balance, created_at) VALUES (?, 0, NOW())");
        $stmt->execute([$authorId]);
    }
    
    // Update balance
    $operator = ($type === 'add') ? '+' : '-';
    $stmt = $pdo->prepare("UPDATE author_balances SET balance = balance $operator ? WHERE author_id = ?");
    $stmt->execute([$amount, $authorId]);
    
    // Log transaction
    $stmt = $pdo->prepare("INSERT INTO transactions (author_id, type, amount, description, created_at) VALUES (?, 'admin_adjustment', ?, ?, NOW())");
    $stmt->execute([$authorId, ($type === 'add' ? $amount : -$amount), $reason]);
    
    // Log moderation action
    $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, user_id, reason, created_at) VALUES (?, 'adjust_balance', ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $authorId, "$type $amount - $reason"]);
    
    // Get updated balance
    $stmt = $pdo->prepare("SELECT balance FROM author_balances WHERE author_id = ?");
    $stmt->execute([$authorId]);
    $newBalance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ok' => true,
        'message' => "Balance adjusted by $amount",
        'new_balance' => $newBalance['balance'] ?? 0
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
