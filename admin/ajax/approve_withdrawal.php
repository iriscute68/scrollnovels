<?php
// admin/ajax/approve_withdrawal.php
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
    
    $withdrawalId = $_POST['withdrawal_id'] ?? 0;
    $action = $_POST['action'] ?? 'approve'; // approve or reject
    $notes = $_POST['notes'] ?? '';
    
    if (!$withdrawalId) {
        echo json_encode(['error' => 'Missing withdrawal_id']);
        exit;
    }
    
    // Get withdrawal details
    $stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE id = ?");
    $stmt->execute([$withdrawalId]);
    $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$withdrawal) {
        echo json_encode(['error' => 'Withdrawal not found']);
        exit;
    }
    
    if ($action === 'approve') {
        // Update status to approved
        $stmt = $pdo->prepare("UPDATE withdrawals SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $withdrawalId]);
        
        // Log action
        $stmt = $pdo->prepare("INSERT INTO moderation_logs (admin_id, action, user_id, reason, created_at) VALUES (?, 'approve_withdrawal', ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $withdrawal['author_id'], "Withdrawal ID: $withdrawalId Amount: " . $withdrawal['amount']]);
        
        $message = 'Withdrawal approved successfully';
    } else {
        // Reject withdrawal
        $stmt = $pdo->prepare("UPDATE withdrawals SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $notes, $withdrawalId]);
        
        // Refund amount to author balance
        $stmt = $pdo->prepare("UPDATE author_balances SET balance = balance + ? WHERE author_id = ?");
        $stmt->execute([$withdrawal['amount'], $withdrawal['author_id']]);
        
        $message = 'Withdrawal rejected and amount refunded';
    }
    
    echo json_encode(['ok' => true, 'message' => $message]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
