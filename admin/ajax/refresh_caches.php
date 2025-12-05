<?php
// admin/ajax/refresh_caches.php - Refresh all data caches from MySQL to JSON
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/datasource.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_user'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

try {
    $result = refreshAllCaches();
    
    $pdo->prepare("
        INSERT INTO admin_activity_logs (admin_id, action, created_at)
        VALUES (?, ?, NOW())
    ")->execute([$_SESSION['admin_user']['id'] ?? null, "Refreshed all data caches from MySQL"]);

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
?>
