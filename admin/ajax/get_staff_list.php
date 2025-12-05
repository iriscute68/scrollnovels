<?php
// admin/ajax/get_staff_list.php
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
    
    $stmt = $pdo->query("SELECT id, username, email, role FROM users WHERE role IN ('admin', 'moderator', 'content_manager') ORDER BY username ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['ok' => true, 'users' => $users]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
