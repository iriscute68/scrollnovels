<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../inc/db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'missing_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, title, summary, message, author_id, is_active, created_at, updated_at FROM announcements WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['success' => true, 'announcement' => $row]);
    } else {
        echo json_encode(['success' => false, 'error' => 'not_found']);
    }
} catch (Exception $e) {
    error_log('announcements_get error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error']);
}
?>
