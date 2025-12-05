<?php
// admin/ajax/get_authors.php
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
    
    $limit = intval($_GET['limit'] ?? 200);
    
    $stmt = $pdo->query("SELECT id, username, email FROM users WHERE role IN ('author', 'writer') ORDER BY username ASC LIMIT " . $limit);
    $authors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['ok' => true, 'authors' => $authors]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
