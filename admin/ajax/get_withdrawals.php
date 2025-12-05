<?php
// admin/ajax/get_withdrawals.php
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
    
    $status = $_GET['status'] ?? 'pending'; // pending, approved, rejected, paid
    $limit = min((int)($_GET['limit'] ?? 50), 500);
    
    // Get withdrawals with author details
    $stmt = $pdo->prepare("
        SELECT w.*, u.username, u.email, ab.balance 
        FROM withdrawals w 
        JOIN users u ON w.author_id = u.id 
        LEFT JOIN author_balances ab ON ab.author_id = u.id 
        WHERE w.status = ? 
        ORDER BY w.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$status, $limit]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM withdrawals WHERE status = ?");
    $stmt->execute([$status]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'ok' => true,
        'withdrawals' => $withdrawals,
        'summary' => $summary
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
