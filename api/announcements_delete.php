<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../inc/db.php';

// Check admin auth
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['super_admin', 'moderator', 'admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID required']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('announcements_delete error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
