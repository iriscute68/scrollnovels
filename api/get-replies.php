<?php
header('Content-Type: application/json');
session_status() === PHP_SESSION_NONE && session_start();
require_once dirname(__DIR__) . '/config/db.php';

$procId = (int)($_GET['proclamation_id'] ?? 0);

if (!$procId) {
    echo json_encode(['success' => false, 'error' => 'Invalid proclamation ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT pr.*, u.username FROM proclamation_replies pr JOIN users u ON pr.user_id = u.id WHERE pr.proclamation_id = ? ORDER BY pr.created_at DESC");
    $stmt->execute([$procId]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'replies' => $replies]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
